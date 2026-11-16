<?php

declare(strict_types=1);

/**
 * SGSI UPTex — Configuración propia del sistema (no forma parte del
 * esqueleto de Laravel; el Dockerfile la copia a config/sgsi.php).
 *
 * Todos los valores provienen del .env: no se escriben datos institucionales
 * ni credenciales en el código. Si una variable está vacía, la plantilla de
 * correo simplemente omite ese dato (no se inventa información).
 */
return [
    'correo' => [
        // Nombre institucional mostrado en encabezado y pie de los correos.
        'institucion' => env('INSTITUCION_NOMBRE', 'Universidad Politécnica de Texcoco'),

        // Sitio web institucional OFICIAL (opcional). Solo se muestra en el pie
        // del correo si se define, p. ej. INSTITUCION_SITIO_WEB=https://...
        'sitio_web' => env('INSTITUCION_SITIO_WEB', ''),
    ],

    'inventario' => [
        // Un antivirus cuya última actualización sea más antigua que estos días
        // se considera desactualizado en tableros y reportes.
        'dias_antivirus_desactualizado' => (int) env('INVENTARIO_DIAS_ANTIVIRUS', 7),
        // Tamaño máximo del archivo de importación (KB) y filas por importación.
        'importacion_max_kb' => (int) env('INVENTARIO_IMPORTACION_MAX_KB', 4096),
        'importacion_max_filas' => (int) env('INVENTARIO_IMPORTACION_MAX_FILAS', 1000),
    ],

    // SGSI Intelligence Assistant (proveedor interno: Google Gemini).
    // Va aquí (no en config/services.php) porque services.php lo genera el
    // esqueleto de Laravel en el build y no se versiona en este repositorio.
    // La API key vive SOLO en el .env del servidor; nunca llega al navegador.
    'ia' => [
        'nombre' => 'SGSI Intelligence Assistant',

        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            // Modelo principal y modelos de respaldo (separados por coma). Si el
            // principal responde 503 «alta demanda», 429 o no contesta a tiempo,
            // se intenta con el siguiente: es la misma conexión, no una segunda.
            'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
            'fallback_models' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-3.5-flash-lite,gemini-flash-lite-latest'))))),
            // Modelo ligero para clasificar solicitudes ambiguas (respuesta JSON corta).
            'classifier_model' => env('GEMINI_CLASSIFIER_MODEL', 'gemini-3.5-flash-lite'),
            // Embeddings para la base de conocimiento (RAG).
            'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
            'embedding_dims' => (int) env('GEMINI_EMBEDDING_DIMS', 768),
            'timeout' => (int) env('GEMINI_TIMEOUT', 25),
            // Tiempo máximo total por solicitud sumando modelo principal y respaldos.
            'presupuesto_segundos' => (int) env('GEMINI_PRESUPUESTO_SEGUNDOS', 50),
            // Razonamiento interno de los modelos 3.x: "low" responde en ~2 s;
            // sin él, gemini-3.5-flash tardaba ~23 s (más que el timeout).
            // Vacío = no se envía (para modelos que no admiten esta opción).
            'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
        ],

        // Control de consumo (protege la cuota y el costo del proveedor).
        'limites' => [
            'tokens_diarios_usuario' => (int) env('IA_TOKENS_DIARIOS_USUARIO', 150000),
            'tokens_diarios_global' => (int) env('IA_TOKENS_DIARIOS_GLOBAL', 2000000),
            'max_caracteres_mensaje' => (int) env('IA_MAX_CARACTERES_MENSAJE', 2000),
            'max_tokens_respuesta' => (int) env('IA_MAX_TOKENS_RESPUESTA', 2048),
            'mensajes_historial' => (int) env('IA_MENSAJES_HISTORIAL', 10),
            'conversaciones_por_usuario' => (int) env('IA_CONVERSACIONES_POR_USUARIO', 100),
        ],

        // Clasificador de dominio: las solicitudes ambiguas (sin señales claras
        // a favor ni en contra) se envían a un clasificador ligero con salida
        // estructurada. Si se desactiva, lo ambiguo se rechaza (conservador).
        'clasificador_llm' => filter_var(env('IA_CLASIFICADOR_LLM', true), FILTER_VALIDATE_BOOL),

        // Base de conocimiento (RAG).
        'rag' => [
            'max_archivo_kb' => (int) env('IA_RAG_MAX_ARCHIVO_KB', 10240),
            'tamano_fragmento' => (int) env('IA_RAG_TAMANO_FRAGMENTO', 1200),
            'solapamiento' => (int) env('IA_RAG_SOLAPAMIENTO', 200),
            'fragmentos_contexto' => (int) env('IA_RAG_FRAGMENTOS_CONTEXTO', 5),
            'similitud_minima' => (float) env('IA_RAG_SIMILITUD_MINIMA', 0.55),
            'max_caracteres_documento' => (int) env('IA_RAG_MAX_CARACTERES', 400000),
        ],
    ],
];
