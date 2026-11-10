# SGSI UPTex — Frontend (Vue 3)

SPA Vue 3 (Composition API) + Pinia + Vue Router + Axios, consumiendo `/api/v1` vía Sanctum (cookie + CSRF, sin tokens en localStorage).

## Sistema de diseño — "Grid de Riesgo"

Documentado en detalle en `src/assets/styles/tokens.css`. Resumen:

- **Color:** paleta institucional propia (`#14508C` azul profundo + `#0EA5A4` teal de señal), con rojo/ámbar reservados **exclusivamente** como semántica de riesgo (crítico/medio), nunca como color de marca — así un badge "crítico" siempre significa lo mismo en toda la app.
- **Tipografía:** Sora (encabezados, con moderación) + Inter (UI/cuerpo) + JetBrains Mono (códigos de control ISO, UUIDs, timestamps) — refuerza que es una herramienta técnica de auditoría, no un sitio de contenido.
- **Firma visual:** la cuadrícula probabilidad×impacto de la matriz de riesgo 5×5 (`RiskMatrix.vue`) se reutiliza como motivo visual en el login y en los estados vacíos (`EmptyState.vue`) — conecta la identidad visual al concepto real del producto (ISO 27005), no es una textura decorativa genérica.
- **Modo oscuro/claro:** variables CSS (`[data-theme='dark']`), persistido en `localStorage`, alternable desde el topbar.

## Estructura