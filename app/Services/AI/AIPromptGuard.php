<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Str;

/**
 * Defensa en profundidad contra manipulación del asistente (OWASP LLM01
 * Prompt Injection, LLM02 Sensitive Information Disclosure, LLM06 Excessive
 * Agency). Se ejecuta en Laravel, ANTES y DESPUÉS de hablar con el modelo:
 *
 *  - Entrada: detecta intentos de cambiar la identidad, anular restricciones,
 *    extraer instrucciones internas o credenciales, ejecutar SQL/comandos o
 *    realizar acciones administrativas por conversación.
 *  - Datos: redacta contraseñas, tokens y claves que el usuario pegue antes
 *    de enviarlos al proveedor.
 *  - Documentos (RAG): marca fragmentos que contienen instrucciones dirigidas
 *    a la IA para no usarlos como contexto.
 *  - Salida: bloquea respuestas que filtren la instrucción de sistema
 *    (canario) o algo con forma de clave.
 *
 * No es una garantía absoluta: es una capa más, junto al prompt de sistema,
 * la clasificación de dominio y la autorización del backend.
 */
final class AIPromptGuard
{
    public const MANIPULACION = 'INTENTO_MANIPULACION';
    public const ACCION_NO_PERMITIDA = 'ACCION_NO_PERMITIDA';

    /** Cambiar identidad, anular reglas o extraer instrucciones/credenciales. */
    private const PATRONES_MANIPULACION = [
        '/\b(ignora|ignorar|ignores|olvida|olvidar|olvides|omite|omitir|descarta|descartar|desobedece|salta|saltate|anula|anular|deshabilita|desactiva)\b.{0,50}\b(instrucciones|reglas|restricciones|indicaciones|politicas|limites|lo anterior|todo lo anterior|tu programacion|tu configuracion|filtros)\b/u',
        '/\b(ignore|disregard|forget|override|bypass)\b.{0,30}\b(previous|prior|above|all|your|the)\b.{0,30}\b(instructions?|rules|prompt|restrictions|guidelines)\b/u',
        '/\b(a partir de ahora|desde ahora|ahora|de ahora en adelante)\b.{0,15}\b(eres|seras|vas a ser|te llamas|actuaras|actua|responderas como|funcionaras como)\b/u',
        '/\b(you are now|from now on you|act as|pretend to be|roleplay as|you are no longer)\b/u',
        '/\b(actua|actuar|comportate|finge|fingir|simula|simular|haz de cuenta|hazte pasar)\b.{0,25}\b(chatgpt|gpt|gemini|claude|copilot|otro asistente|asistente general|asistente de proposito general|ia sin restricciones|ia libre|sin restricciones|sin filtros|dan)\b/u',
        '/\b(jailbreak|modo desarrollador|developer mode|modo dios|god mode|do anything now|dan mode|modo (sin restricciones|sin filtros|sin censura|libre)|(responde|contesta|habla) sin (restricciones|filtros|censura|limites))\b/u',
        // Pedir SUS instrucciones (no preguntar qué es un prompt injection, tema legítimo).
        '/\b(revela|revelar|muestra|muestrame|mostrar|dime|imprime|repite|repiteme|ensename|comparte|copia|escribe|cual es|cuales son|reveal|show|print|repeat|tell me)\b.{0,30}\b(tu prompt|tus prompts|el prompt (del sistema|de sistema|inicial|original|interno|oculto)|tu system prompt|el system prompt|instrucciones (del sistema|de sistema|internas|iniciales|ocultas|originales|secretas)|tus instrucciones|tus reglas|mensaje (del|de) sistema|configuracion interna|reglas internas|your (system )?prompt|your instructions|initial instructions)\b/u',
        // Pedir credenciales DEL sistema (no preguntar cómo proteger API keys, tema legítimo).
        '/\b(dame|dime|darme|muestra|muestrame|revela|comparte|imprime|pasame|envia|enviame|escribe|cual es|cuales son|lee|leeme|give me|show|reveal|print)\b.{0,25}\b(tu|tus|la|el|las|los|su|sus|esta|esa|your|the)\b.{0,12}\b(api[ _-]?key|apikey|clave (de )?(la )?api|token de (acceso|api|sanctum)|credenciales|contrasena de la base de datos|variables de entorno|archivo \.env)\b/u',
        '/\b(gemini_api_key|app_key=|db_password|db_root_password|mail_password)\b/u',
    ];

    /** Acciones que nunca se ejecutan por conversación (Laravel controla la ejecución). */
    private const PATRONES_ACCION = [
        '/\b(ejecuta|ejecutar|corre|correr|lanza|run|execute)\b.{0,20}\b(comando|comandos|sql|query|consulta sql|script|shell|bash|terminal|codigo en el servidor)\b/u',
        '/\b(drop (table|database)|delete from|truncate table|update [a-z_]+ set|insert into|alter table|grant all|select \* from)\b/u',
        '/\b(otorga|otorgame|asigna|asigname|dame|darme|cambia|cambiame|eleva|elevar|concede|concedeme)\b.{0,30}\b(rol de administrador|permisos? de administrador|acceso de administrador|privilegios|permisos de (admin|administracion)|rol administrador)\b/u',
        '/\b(elimina|eliminar|borra|borrar|suprime|destruye|purga)\b.{0,25}\b(todos|todas|los|las)\b.{0,15}\b(activos|riesgos|incidentes|usuarios|registros|auditorias|documentos|evidencias|logs|bitacora|bitacoras)\b/u',
        '/\b(conversaciones|historial|mensajes|chats|preguntas)\b.{0,20}\b(de otro|de otros|de otra|de otras|de los demas|de todos los usuarios|de cualquier usuario|del usuario|de la usuaria|ajenas?|ajenos?)\b/u',
        '/\b(contrasena|contrasenas|password|passwords|hash)\b.{0,25}\b(de los usuarios|de otro usuario|del administrador|de todos|de cada usuario|de la cuenta de)\b/u',
    ];

    /** Instrucciones dirigidas a la IA escondidas en documentos (RAG). */
    private const PATRONES_DOCUMENTO = [
        '/\b(ignora|ignore|olvida|forget|disregard)\b.{0,50}\b(instrucciones|instructions|reglas|rules|prompt)\b/u',
        '/\b(eres|you are|actua como|act as)\b.{0,30}\b(asistente|assistant|ia|ai|modelo|model)\b.{0,40}\b(sin|without|no)\b.{0,15}\b(restricciones|restrictions|limites|limits|reglas|rules)\b/u',
        '/\b(system prompt|instruccion(es)? (de|del) sistema|asistente,? (debes|deberas)|assistant,? (you must|you should)|nota para (la ia|el modelo|el asistente)|note to (the )?(ai|model|assistant))\b/u',
    ];

    /** Secretos que el usuario podría pegar por error: se redactan antes de salir del servidor. */
    private const PATRONES_SECRETOS = [
        '/AIza[0-9A-Za-z_\-]{20,}/' => '[clave redactada]',
        '/\b(sk|pk|rk)-[A-Za-z0-9_\-]{16,}\b/' => '[clave redactada]',
        '/\bgh[pousr]_[A-Za-z0-9]{20,}\b/' => '[token redactado]',
        '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/' => '[token redactado]',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----/' => '[llave privada redactada]',
        '/\b(contrase(ñ|n)a|password|passwd|pwd|clave|token|secret|api[_ -]?key)\s*[:=]\s*\S+/iu' => '$1: [redactado]',
        '/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/' => '[hash redactado]',
    ];

    /** Texto en minúsculas, sin acentos ni espacios repetidos: base de todas las reglas. */
    public static function normalizar(string $texto): string
    {
        $texto = Str::lower(Str::ascii($texto));

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /** Limpia caracteres de control y de formato invisibles (usados para ocultar instrucciones). */
    public function limpiarEntrada(string $texto, int $max): string
    {
        $texto = (string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', $texto);
        $texto = (string) preg_replace('/[^\P{C}\n\t]/u', '', $texto);

        return trim(mb_substr($texto, 0, $max));
    }

    /** @return array{tipo: string, motivo: string}|null */
    public function detectarManipulacion(string $texto): ?array
    {
        $normal = self::normalizar($texto);

        foreach (self::PATRONES_MANIPULACION as $patron) {
            if (preg_match($patron, $normal)) {
                return ['tipo' => self::MANIPULACION, 'motivo' => 'Intento de modificar la identidad, las restricciones o de obtener información interna.'];
            }
        }
        foreach (self::PATRONES_ACCION as $patron) {
            if (preg_match($patron, $normal)) {
                return ['tipo' => self::ACCION_NO_PERMITIDA, 'motivo' => 'Solicitud de una acción administrativa, de datos ajenos o de ejecución que el asistente no puede realizar.'];
            }
        }

        return null;
    }

    public function fragmentoSospechoso(string $texto): bool
    {
        $normal = self::normalizar($texto);
        foreach ([...self::PATRONES_DOCUMENTO, ...self::PATRONES_MANIPULACION] as $patron) {
            if (preg_match($patron, $normal)) {
                return true;
            }
        }

        return false;
    }

    public function redactarSecretos(string $texto): string
    {
        foreach (self::PATRONES_SECRETOS as $patron => $reemplazo) {
            $texto = (string) preg_replace($patron, $reemplazo, $texto);
        }

        return $texto;
    }

    /** Marca única de la instrucción de sistema: si aparece en la salida, hubo filtración. */
    public static function canario(): string
    {
        return 'SGSI-' . substr(hash('sha256', 'sgsi-canario|' . config('app.key')), 0, 12);
    }

    /**
     * Revisa la respuesta del modelo antes de mostrarla. Devuelve null si debe
     * descartarse por completo (filtración de instrucciones o credenciales).
     */
    public function validarSalida(string $texto): ?string
    {
        if (str_contains($texto, self::canario())) {
            return null;
        }
        if (preg_match('/AIza[0-9A-Za-z_\-]{20,}/', $texto)) {
            return null;
        }
        $key = (string) config('sgsi.ia.gemini.key');
        if ($key !== '' && str_contains($texto, $key)) {
            return null;
        }

        return $this->redactarSecretos($texto);
    }
}
