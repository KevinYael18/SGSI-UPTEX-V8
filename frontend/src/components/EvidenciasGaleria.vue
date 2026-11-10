<template>
  <div>
    <ul v-if="evidencias.length" class="galeria">
      <li v-for="ev in evidencias" :key="ev.uuid">
        <button
          type="button"
          class="galeria__item"
          :class="{ 'galeria__item--faltante': ev.archivo_disponible === false }"
          @click="abrir(ev)"
        >
          <span class="galeria__vista">
            <img v-if="esImagen(ev) && ev.archivo_disponible !== false" :src="url(ev)" alt="" loading="lazy" />
            <span v-else class="galeria__icono">
              <AppIcon :name="ev.archivo_disponible === false ? 'alerta' : 'documentos'" :size="26" />
              <span class="galeria__tipo">{{ etiquetaTipo(ev) }}</span>
            </span>
            <span v-if="esVideo(ev) && ev.archivo_disponible !== false" class="galeria__play" aria-hidden="true">▶</span>
          </span>
          <span class="galeria__info">
            <span class="galeria__nombre">{{ etiquetaTipo(ev) }}<template v-if="ev.tamano_bytes"> · {{ tamano(ev.tamano_bytes) }}</template></span>
            <span v-if="ev.archivo_disponible === false" class="galeria__aviso">Archivo no disponible</span>
            <span v-else-if="ev.fecha_subida" class="galeria__fecha">{{ new Date(ev.fecha_subida).toLocaleDateString('es-MX') }}</span>
          </span>
        </button>
      </li>
    </ul>
    <p v-else class="vacio">Sin evidencias adjuntas.</p>

    <AppModal :abierto="!!actual" ancho :titulo="actual ? `Evidencia · ${etiquetaTipo(actual)}` : ''" @cerrar="actual = null">
      <template v-if="actual">
        <div v-if="actual.archivo_disponible === false || fallo" class="visor__faltante">
          <AppIcon name="alerta" :size="32" />
          <p><strong>El archivo de esta evidencia no está en el almacenamiento.</strong></p>
          <p>El registro existe (huella SHA-256 <span class="mono">{{ actual.hash_sha256.slice(0, 16) }}…</span>),
            pero el archivo no se encontró. Suele pasar al restaurar solo la base de datos sin la carpeta de archivos.</p>
        </div>
        <div v-else class="visor">
          <img v-if="esImagen(actual)" :src="url(actual)" alt="Evidencia" class="visor__imagen" @error="fallo = true" />
          <video v-else-if="esVideo(actual)" :src="url(actual)" controls playsinline preload="metadata" class="visor__video" @error="fallo = true" />
          <iframe v-else-if="esPdf(actual)" :src="url(actual)" title="Evidencia PDF" class="visor__pdf" />
          <p v-else>Este tipo de archivo no tiene vista previa. Usa “Descargar”.</p>
        </div>
        <p class="visor__hash">SHA-256: <span class="mono">{{ actual.hash_sha256 }}</span></p>
      </template>
      <template #footer>
        <AppButton variante="secundaria" @click="actual = null">Cerrar</AppButton>
        <template v-if="actual && actual.archivo_disponible !== false && !fallo">
          <a :href="url(actual)" target="_blank" rel="noopener" class="visor__accion">
            <AppIcon name="externo" :size="15" /> Abrir en pestaña
          </a>
          <a :href="url(actual) + '?descargar=1'" class="visor__accion visor__accion--primaria">
            <AppIcon name="descargar" :size="15" /> Descargar
          </a>
        </template>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import http from '../api/http';
import AppIcon from './ui/AppIcon.vue';
import AppModal from './ui/AppModal.vue';
import AppButton from './ui/AppButton.vue';

// Galería de evidencias con visor integrado (imagen, video MP4 y PDF).
defineProps({ evidencias: { type: Array, default: () => [] } });

const actual = ref(null);
const fallo = ref(false);

const esImagen = (ev) => ev.mime_type?.startsWith('image/');
const esVideo = (ev) => ev.mime_type?.startsWith('video/');
const esPdf = (ev) => ev.mime_type === 'application/pdf';
const url = (ev) => `${http.defaults.baseURL}${ev.url_descarga}`;

function etiquetaTipo(ev) {
    if (esImagen(ev)) return 'Imagen ' + ev.mime_type.split('/')[1].toUpperCase();
    if (esVideo(ev)) return 'Video ' + ev.mime_type.split('/')[1].toUpperCase();
    if (esPdf(ev)) return 'Documento PDF';
    return ev.mime_type;
}

function tamano(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}

function abrir(ev) {
    fallo.value = false;
    actual.value = ev;
}
</script>

<style scoped>
.galeria { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--space-3); }
.galeria__item {
    width: 100%; display: flex; flex-direction: column; text-align: left; padding: 0; cursor: pointer; overflow: hidden;
    border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--bg-subtle); color: var(--text);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
}
.galeria__item:hover { border-color: var(--c-primary); box-shadow: var(--shadow-md); transform: translateY(-1px); }
.galeria__item:focus-visible { outline: 2px solid var(--c-primary); outline-offset: 2px; }
.galeria__item--faltante { border-style: dashed; }
.galeria__item--faltante:hover { border-color: var(--c-warning, #b7791f); }
.galeria__vista { position: relative; display: grid; place-items: center; width: 100%; aspect-ratio: 4 / 3; overflow: hidden; background: var(--surface); border-bottom: 1px solid var(--border); }
.galeria__vista img { width: 100%; height: 100%; object-fit: cover; }
.galeria__icono { display: flex; flex-direction: column; align-items: center; gap: 6px; color: var(--text-muted); }
.galeria__item--faltante .galeria__icono { color: var(--c-warning, #b7791f); }
.galeria__tipo { font-size: var(--text-xs); font-weight: 600; }
.galeria__play {
    position: absolute; display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%;
    background: rgba(0, 0, 0, 0.55); color: #fff; font-size: 15px; padding-left: 3px;
}
.galeria__info { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; min-width: 0; }
.galeria__nombre { font-size: var(--text-xs); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.galeria__fecha { font-size: var(--text-xs); color: var(--text-muted); }
.galeria__aviso { font-size: var(--text-xs); color: var(--c-warning, #b7791f); font-weight: 600; }
.vacio { color: var(--text-muted); margin: 0; }

.visor { display: grid; place-items: center; min-height: 240px; background: var(--bg-subtle); border-radius: var(--radius-md); overflow: hidden; }
.visor__imagen { max-width: 100%; max-height: 68vh; object-fit: contain; display: block; }
.visor__video { width: 100%; max-height: 68vh; background: #000; display: block; }
.visor__pdf { width: 100%; height: 68vh; border: 0; background: #fff; display: block; }
.visor__faltante {
    display: flex; flex-direction: column; align-items: center; text-align: center; gap: var(--space-2);
    padding: var(--space-6, 32px) var(--space-4); color: var(--text-muted);
    border: 1px dashed var(--border-strong); border-radius: var(--radius-md);
}
.visor__faltante :deep(svg) { color: var(--c-warning, #b7791f); }
.visor__faltante p { margin: 0; max-width: 520px; }
.visor__faltante strong { color: var(--text); }
.visor__hash { margin: var(--space-3) 0 0; font-size: var(--text-xs); color: var(--text-muted); word-break: break-all; }
.visor__accion {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: var(--radius-md);
    border: 1px solid var(--border); background: var(--surface); color: var(--text); font-weight: 600; font-size: var(--text-sm);
}
.visor__accion:hover { text-decoration: none; border-color: var(--c-primary); }
.visor__accion--primaria { background: var(--c-primary); border-color: var(--c-primary); color: #fff; }
.visor__accion--primaria:hover { filter: brightness(1.08); }
@media (max-width: 640px) {
    .galeria { grid-template-columns: repeat(2, 1fr); }
    .visor__pdf { height: 60dvh; }
}
</style>
