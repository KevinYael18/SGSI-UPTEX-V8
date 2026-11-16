<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IaChatRequest;
use App\Models\IaConversacion;
use App\Models\IaMensaje;
use App\Services\AI\SGSIIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Asistente conversacional. El historial es PRIVADO: todas las consultas se
 * filtran por el usuario autenticado y una conversación ajena responde 404
 * (no se revela que existe). No hay IDs secuenciales en las rutas.
 */
class IaConversacionController extends Controller
{
    public function __construct(private readonly SGSIIntelligenceService $asistente)
    {
    }

    #[OA\Get(path: '/api/v1/ia/conversaciones', tags: ['IA'], summary: 'Conversaciones del usuario autenticado')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('ia.usar');

        $conversaciones = IaConversacion::query()
            ->where('usuario_id', $request->user()->id)
            ->withCount('mensajes')
            ->orderByDesc('updated_at')
            ->limit((int) config('sgsi.ia.limites.conversaciones_por_usuario'))
            ->get()
            ->map(fn (IaConversacion $c) => [
                'uuid' => $c->uuid,
                'titulo' => $c->titulo,
                'mensajes' => $c->mensajes_count,
                'actualizada_en' => $c->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $conversaciones]);
    }

    #[OA\Get(path: '/api/v1/ia/conversaciones/{uuid}', tags: ['IA'], summary: 'Mensajes de una conversación propia')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('ia.usar');
        $conversacion = $this->asistente->conversacionPropia($request->user(), $uuid);

        return response()->json(['data' => [
            'uuid' => $conversacion->uuid,
            'titulo' => $conversacion->titulo,
            'mensajes' => $conversacion->mensajes()->orderBy('id')->get()->map(fn (IaMensaje $m) => $this->mensaje($m)),
        ]]);
    }

    #[OA\Post(
        path: '/api/v1/ia/chat',
        tags: ['IA'],
        summary: 'Envía una pregunta a SGSI Intelligence Assistant',
        responses: [
            new OA\Response(response: 200, description: 'Respuesta (o rechazo por estar fuera de dominio)'),
            new OA\Response(response: 429, description: 'IA_LIMITE_CONSUMO o límite de frecuencia'),
            new OA\Response(response: 503, description: 'IA_NO_DISPONIBLE'),
        ]
    )]
    public function chat(IaChatRequest $request): JsonResponse
    {
        $resultado = $this->asistente->conversar(
            $request->user(),
            $request->string('mensaje')->toString(),
            $request->input('conversacion_uuid'),
        );

        return response()->json(['data' => [
            'conversacion' => ['uuid' => $resultado['conversacion']->uuid, 'titulo' => $resultado['conversacion']->titulo],
            'pregunta' => $this->mensaje($resultado['pregunta']),
            'respuesta' => $this->mensaje($resultado['respuesta']),
        ]]);
    }

    #[OA\Delete(path: '/api/v1/ia/conversaciones/{uuid}', tags: ['IA'], summary: 'Elimina una conversación propia')]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('ia.usar');
        $this->asistente->eliminarConversacion($request->user(), $uuid);

        return response()->json(null, 204);
    }

    private function mensaje(IaMensaje $m): array
    {
        return [
            'id' => $m->id,
            'rol' => $m->rol,
            'contenido' => $m->contenido,
            'categoria' => $m->categoria,
            'estado' => $m->estado,
            'fuentes' => $m->fuentes ?? [],
            'creado_en' => $m->created_at?->toIso8601String(),
        ];
    }
}
