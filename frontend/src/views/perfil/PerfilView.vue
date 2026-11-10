<template>
  <div class="perfil">
    <div class="encabezado perfil__encabezado">
      <div>
        <h1>Mi perfil</h1>
        <p class="perfil__intro">Consulta tu información, actualiza tu fotografía y administra tu contraseña.</p>
      </div>
    </div>

    <!-- Estado de carga -->
    <div v-if="cargando" class="perfil__esqueleto" aria-busy="true" aria-label="Cargando perfil…">
      <div class="skeleton perfil__esq-hero" />
      <div class="perfil__grid">
        <div class="skeleton perfil__esq-card" />
        <div class="skeleton perfil__esq-card" />
      </div>
    </div>

    <div v-else-if="errorCarga" class="perfil__error">
      <AppIcon name="alerta" :size="22" />
      <p>{{ errorCarga }}</p>
      <AppButton variante="secundaria" tamano="sm" @click="cargar">Reintentar</AppButton>
    </div>

    <template v-else-if="perfil">
      <!-- ===== Tarjeta principal con fotografía ===== -->
      <section class="perfil__hero aparecer" aria-labelledby="perfil-nombre">
        <div class="perfil__foto">
          <div class="perfil__foto-marco" :class="{ 'perfil__foto-marco--previa': previa }">
            <img v-if="previa" :src="previa" alt="Vista previa de la nueva fotografía" class="perfil__foto-previa" />
            <UserAvatar v-else :nombre="perfil.nombre" :foto="perfil.foto_url" :size="132" />
            <div v-if="subiendo" class="perfil__foto-carga" role="status" aria-live="polite">
              <span class="perfil__spinner" aria-hidden="true" />
              <span>{{ progreso }}%</span>
            </div>
          </div>
          <button
            v-if="!previa"
            type="button"
            class="perfil__foto-camara"
            aria-label="Cambiar fotografía"
            title="Cambiar fotografía"
            :disabled="subiendo || eliminando"
            @click="elegirArchivo"
          >
            <AppIcon name="camara" :size="18" />
          </button>
          <span v-if="previa" class="perfil__previa-etiqueta">Vista previa</span>
        </div>

        <div class="perfil__hero-info">
          <h2 id="perfil-nombre">{{ perfil.nombre }}</h2>
          <p class="perfil__hero-correo"><AppIcon name="correo" :size="15" />{{ perfil.email }}</p>
          <div class="perfil__chips">
            <span class="perfil__chip perfil__chip--rol"><AppIcon name="escudo" :size="13" />{{ perfil.rol }}</span>
            <span class="perfil__chip" :class="perfil.estado === 'activo' ? 'perfil__chip--ok' : 'perfil__chip--alerta'">
              <span class="perfil__punto" aria-hidden="true" />Cuenta {{ etiquetaEstado(perfil.estado) }}
            </span>
          </div>

          <input
            ref="refArchivo"
            type="file"
            class="perfil__input-archivo"
            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
            tabindex="-1"
            aria-hidden="true"
            @change="alSeleccionar"
          />

          <div v-if="previa" class="perfil__foto-acciones">
            <AppButton icono="check" :cargando="subiendo" @click="subirFoto">Guardar fotografía</AppButton>
            <AppButton variante="secundaria" :disabled="subiendo" @click="cancelarSeleccion">Cancelar</AppButton>
            <span class="perfil__archivo-info">{{ archivo?.name }} · {{ tamanoLegible(archivo?.size) }}</span>
          </div>
          <div v-else class="perfil__foto-acciones">
            <AppButton icono="camara" variante="secundaria" :disabled="eliminando" @click="elegirArchivo">
              {{ perfil.foto_url ? 'Cambiar fotografía' : 'Subir fotografía' }}
            </AppButton>
            <AppButton v-if="perfil.foto_url" icono="papelera" variante="fantasma" class="perfil__btn-quitar" :cargando="eliminando" @click="confirmarEliminarFoto = true">
              Eliminar fotografía
            </AppButton>
          </div>
          <p v-if="errorFoto" class="perfil__mensaje perfil__mensaje--error" role="alert"><AppIcon name="alerta" :size="15" />{{ errorFoto }}</p>
          <p v-else class="perfil__mensaje">JPG, PNG o WEBP · máximo {{ maxMb }} MB · se recorta en cuadrado.</p>
        </div>
      </section>

      <!-- ===== Información ===== -->
      <div class="perfil__grid">
        <AppCard titulo="Información personal" icono="usuario" class="aparecer">
          <dl class="dl">
            <dt>Nombre completo</dt><dd>{{ perfil.nombre }}</dd>
            <dt>Correo electrónico</dt><dd class="perfil__dato-largo">{{ perfil.email }}</dd>
            <dt>Rol</dt><dd>{{ perfil.rol }}</dd>
            <dt>Estado de la cuenta</dt>
            <dd><AppBadge :tono="perfil.estado === 'activo' ? 'exito' : 'riesgo'">{{ etiquetaEstado(perfil.estado) }}</AppBadge></dd>
          </dl>
        </AppCard>

        <AppCard titulo="Información de la cuenta" icono="info" class="aparecer">
          <dl class="dl">
            <dt>Fecha de registro</dt><dd>{{ fecha(perfil.creado_en) }}</dd>
            <dt>Último acceso</dt><dd>{{ fechaHora(perfil.ultimo_acceso) }}</dd>
            <dt>Tipo de usuario</dt><dd>{{ perfil.rol }}<small v-if="perfil.rol_descripcion" class="perfil__sub">{{ perfil.rol_descripcion }}</small></dd>
            <dt>Permisos del rol</dt><dd>{{ (perfil.permisos || []).length }}</dd>
            <template v-if="perfil.departamentos?.length">
              <dt>Departamentos</dt>
              <dd class="perfil__deps">
                <span v-for="d in perfil.departamentos" :key="d.clave" class="perfil__dep">{{ d.nombre }}</span>
              </dd>
            </template>
          </dl>
          <div class="perfil__resumen">
            <div><strong>{{ perfil.resumen?.activos_responsable ?? 0 }}</strong><span>Activos a tu cargo</span></div>
            <div><strong>{{ perfil.resumen?.incidentes_reportados ?? 0 }}</strong><span>Incidentes reportados</span></div>
          </div>
        </AppCard>
      </div>

      <!-- ===== Edición ===== -->
      <div class="perfil__grid">
        <AppCard titulo="Editar información" subtitulo="Datos que puedes modificar tú mismo" icono="editar" class="aparecer">
          <form class="form" novalidate @submit.prevent="guardarDatos">
            <AppInput
              v-model="formDatos.nombre"
              label="Nombre completo"
              icono="usuario"
              requerido
              maxlength="150"
              autocomplete="name"
              :error="erroresDatos.nombre"
            />
            <AppInput
              :model-value="perfil.email"
              label="Correo electrónico"
              icono="correo"
              type="email"
              disabled
              ayuda="Es tu usuario de acceso; solo un administrador puede cambiarlo."
            />
            <div class="perfil__form-acciones">
              <AppButton type="submit" icono="check" :cargando="guardandoDatos" :disabled="!datosModificados">Guardar cambios</AppButton>
              <AppButton type="button" variante="secundaria" :disabled="!datosModificados || guardandoDatos" @click="restaurarDatos">Descartar</AppButton>
            </div>
          </form>
        </AppCard>

        <AppCard id="cambiar-password" titulo="Cambiar contraseña" subtitulo="Por seguridad, se cerrarán tus otras sesiones" icono="candado" class="aparecer perfil__card-password">
          <form ref="refFormPassword" class="form" novalidate @submit.prevent="cambiarPassword">
            <!-- Campo oculto para que los gestores de contraseñas asocien la cuenta -->
            <input type="email" :value="perfil.email" autocomplete="username" class="perfil__oculto" tabindex="-1" aria-hidden="true" readonly />
            <AppInput
              v-model="formPass.password_actual"
              label="Contraseña actual"
              type="password"
              icono="candado"
              requerido
              autocomplete="current-password"
              :error="erroresPass.password_actual"
            />
            <AppInput
              v-model="formPass.password"
              label="Nueva contraseña"
              type="password"
              icono="candado"
              requerido
              autocomplete="new-password"
              :error="erroresPass.password"
            />
            <ul class="perfil__reglas" aria-label="Requisitos de la nueva contraseña">
              <li v-for="r in reglas" :key="r.texto" :class="{ 'perfil__regla--ok': r.ok }">
                <AppIcon :name="r.ok ? 'check' : 'circulo'" :size="14" />{{ r.texto }}
              </li>
            </ul>
            <AppInput
              v-model="formPass.password_confirmation"
              label="Confirmar nueva contraseña"
              type="password"
              icono="candado"
              requerido
              autocomplete="new-password"
              :error="erroresPass.password_confirmation"
            />
            <div class="perfil__form-acciones">
              <AppButton type="submit" icono="candado" :cargando="guardandoPass">Actualizar contraseña</AppButton>
            </div>
          </form>
        </AppCard>
      </div>
    </template>

    <AppModal :abierto="confirmarEliminarFoto" titulo="Eliminar fotografía" @cerrar="confirmarEliminarFoto = false">
      <div class="perfil__confirmar">
        <UserAvatar :nombre="perfil?.nombre" :size="64" />
        <p>Tu fotografía se eliminará y se mostrará un avatar con tus iniciales en toda la plataforma. ¿Deseas continuar?</p>
      </div>
      <template #footer>
        <AppButton variante="secundaria" @click="confirmarEliminarFoto = false">Cancelar</AppButton>
        <AppButton variante="peligro" icono="papelera" :cargando="eliminando" @click="eliminarFoto">Sí, eliminar</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import perfilApi from '../../api/perfil';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import AppCard from '../../components/ui/AppCard.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import UserAvatar from '../../components/ui/UserAvatar.vue';

const auth = useAuthStore();
const toast = useToastStore();
const route = useRoute();

const perfil = ref(null);
const cargando = ref(true);
const errorCarga = ref('');

// Mismas reglas que el backend (SubirFotoPerfilRequest); el servidor vuelve a validar.
const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];
const EXTENSIONES = /\.(jpe?g|png|webp)$/i;
const maxKb = computed(() => perfil.value?.foto_max_kb ?? 2048);
const maxMb = computed(() => Math.round(maxKb.value / 1024));

function aplicarPerfil(datos) {
    perfil.value = datos;
    auth.sincronizarPerfil(datos); // refleja nombre/foto en menú superior, dashboard, etc.
    formDatos.nombre = datos.nombre;
}

async function cargar() {
    cargando.value = true;
    errorCarga.value = '';
    try {
        const { data } = await perfilApi.obtener();
        aplicarPerfil(data.data);
    } catch (e) {
        errorCarga.value = e.message || 'No se pudo cargar tu perfil.';
    } finally {
        cargando.value = false;
    }
}

// ---------- Fotografía ----------
const refArchivo = ref(null);
const archivo = ref(null);
const previa = ref('');
const subiendo = ref(false);
const progreso = ref(0);
const eliminando = ref(false);
const errorFoto = ref('');
const confirmarEliminarFoto = ref(false);

function elegirArchivo() {
    errorFoto.value = '';
    refArchivo.value?.click();
}

function limpiarPrevia() {
    if (previa.value) URL.revokeObjectURL(previa.value);
    previa.value = '';
    archivo.value = null;
    if (refArchivo.value) refArchivo.value.value = '';
}

function leerDimensiones(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve({ ancho: img.naturalWidth, alto: img.naturalHeight });
        img.onerror = () => reject(new Error('imagen-invalida'));
        img.src = url;
    });
}

async function alSeleccionar(e) {
    const elegido = e.target.files?.[0];
    if (!elegido) return;
    limpiarPrevia();
    errorFoto.value = '';

    if (!TIPOS_PERMITIDOS.includes(elegido.type) || !EXTENSIONES.test(elegido.name)) {
        errorFoto.value = 'Formato no permitido. Usa una imagen JPG, JPEG, PNG o WEBP.';
        return;
    }
    if (elegido.size > maxKb.value * 1024) {
        errorFoto.value = `La imagen pesa ${tamanoLegible(elegido.size)}; el máximo es ${maxMb.value} MB.`;
        return;
    }

    const url = URL.createObjectURL(elegido);
    try {
        const { ancho, alto } = await leerDimensiones(url);
        if (ancho < 64 || alto < 64 || ancho > 4096 || alto > 4096) {
            URL.revokeObjectURL(url);
            errorFoto.value = `La imagen mide ${ancho}×${alto} px; debe estar entre 64×64 y 4096×4096 px.`;
            return;
        }
    } catch {
        URL.revokeObjectURL(url);
        errorFoto.value = 'No se pudo leer la imagen. Puede estar dañada.';
        return;
    }

    archivo.value = elegido;
    previa.value = url;
}

function cancelarSeleccion() {
    limpiarPrevia();
    errorFoto.value = '';
}

async function subirFoto() {
    if (!archivo.value) return;
    subiendo.value = true;
    progreso.value = 0;
    errorFoto.value = '';
    try {
        const { data } = await perfilApi.subirFoto(archivo.value, (p) => { progreso.value = p; });
        aplicarPerfil(data.data);
        limpiarPrevia();
        toast.exito('Fotografía de perfil actualizada.');
    } catch (e) {
        errorFoto.value = primerError(e, 'foto') || e.message;
        toast.error('No se pudo actualizar la fotografía.');
    } finally {
        subiendo.value = false;
    }
}

async function eliminarFoto() {
    eliminando.value = true;
    try {
        const { data } = await perfilApi.eliminarFoto();
        aplicarPerfil(data.data);
        confirmarEliminarFoto.value = false;
        toast.exito('Fotografía eliminada. Se mostrarán tus iniciales.');
    } catch (e) {
        toast.error(e.message);
    } finally {
        eliminando.value = false;
    }
}

// ---------- Datos personales ----------
const formDatos = reactive({ nombre: '' });
const erroresDatos = reactive({ nombre: '' });
const guardandoDatos = ref(false);
const datosModificados = computed(() => perfil.value && formDatos.nombre.trim().replace(/\s+/g, ' ') !== perfil.value.nombre);

function restaurarDatos() {
    formDatos.nombre = perfil.value?.nombre ?? '';
    erroresDatos.nombre = '';
}

async function guardarDatos() {
    erroresDatos.nombre = '';
    const nombre = formDatos.nombre.trim().replace(/\s+/g, ' ');
    if (nombre.length < 3) { erroresDatos.nombre = 'El nombre debe tener al menos 3 caracteres.'; return; }
    if (nombre.length > 150) { erroresDatos.nombre = 'El nombre no puede exceder 150 caracteres.'; return; }

    guardandoDatos.value = true;
    try {
        const { data } = await perfilApi.actualizar({ nombre });
        aplicarPerfil(data.data);
        toast.exito('Información actualizada.');
    } catch (e) {
        erroresDatos.nombre = primerError(e, 'nombre');
        if (!erroresDatos.nombre) toast.error(e.message);
    } finally {
        guardandoDatos.value = false;
    }
}

// ---------- Contraseña ----------
const refFormPassword = ref(null);
const formPass = reactive({ password_actual: '', password: '', password_confirmation: '' });
const erroresPass = reactive({ password_actual: '', password: '', password_confirmation: '' });
const guardandoPass = ref(false);

const reglas = computed(() => {
    const p = formPass.password;
    return [
        { texto: 'Mínimo 12 caracteres', ok: p.length >= 12 },
        { texto: 'Mayúsculas y minúsculas', ok: /\p{Ll}/u.test(p) && /\p{Lu}/u.test(p) },
        { texto: 'Al menos un número', ok: /\p{N}/u.test(p) },
        { texto: 'Distinta de la actual', ok: p.length > 0 && p !== formPass.password_actual },
    ];
});

function limpiarErroresPass() {
    for (const k of Object.keys(erroresPass)) erroresPass[k] = '';
}

async function cambiarPassword() {
    limpiarErroresPass();
    if (!formPass.password_actual) erroresPass.password_actual = 'Escribe tu contraseña actual.';
    if (!reglas.value.every((r) => r.ok)) erroresPass.password = 'La nueva contraseña no cumple todos los requisitos.';
    if (formPass.password_confirmation !== formPass.password) erroresPass.password_confirmation = 'La confirmación no coincide con la nueva contraseña.';
    if (Object.values(erroresPass).some(Boolean)) return;

    guardandoPass.value = true;
    try {
        const { data } = await perfilApi.cambiarPassword(formPass);
        formPass.password_actual = '';
        formPass.password = '';
        formPass.password_confirmation = '';
        toast.exito(data.mensaje || 'Contraseña actualizada correctamente.');
    } catch (e) {
        erroresPass.password_actual = primerError(e, 'password_actual');
        erroresPass.password = primerError(e, 'password');
        erroresPass.password_confirmation = primerError(e, 'password_confirmation');
        if (!Object.values(erroresPass).some(Boolean)) toast.error(e.message);
    } finally {
        guardandoPass.value = false;
    }
}

// ---------- Utilidades ----------
function primerError(e, campo) {
    const d = e?.details?.[campo];
    return Array.isArray(d) ? d[0] : (typeof d === 'string' ? d : '');
}
function etiquetaEstado(estado) {
    return { activo: 'activa', suspendido: 'suspendida' }[estado] || estado || '—';
}
function fecha(iso) {
    return iso ? new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) : 'No disponible';
}
function fechaHora(iso) {
    return iso ? new Date(iso).toLocaleString('es-MX', { dateStyle: 'long', timeStyle: 'short' }) : 'Sin registro';
}
function tamanoLegible(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

// Enlace directo «Cambiar contraseña» desde el menú superior (#cambiar-password).
async function irASeccion() {
    if (route.hash !== '#cambiar-password' || !perfil.value) return;
    await nextTick();
    const el = document.getElementById('cambiar-password');
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    el.classList.add('perfil__resaltar');
    setTimeout(() => el.classList.remove('perfil__resaltar'), 1600);
    // Pequeño retraso: en una navegación solo-de-hash el navegador reasigna el
    // foco al fragmento después de este punto; así el campo conserva el foco.
    setTimeout(() => {
        refFormPassword.value?.querySelector('input[autocomplete="current-password"]')?.focus({ preventScroll: true });
    }, 80);
}
watch(() => route.hash, irASeccion);

onMounted(async () => {
    await cargar();
    irASeccion();
});
onBeforeUnmount(limpiarPrevia);
</script>

<style scoped>
.perfil { max-width: 1180px; margin: 0 auto; }
.perfil__intro { margin: 4px 0 0; color: var(--text-muted); font-size: var(--text-sm); }

/* ---- Tarjeta principal ---- */
.perfil__hero {
    position: relative; overflow: hidden; isolation: isolate;
    --banda: 112px;
    display: flex; align-items: flex-start; gap: var(--space-6); flex-wrap: wrap;
    /* La foto (140 px) arranca a 48 px: queda montada sobre la banda verde. */
    padding: 48px var(--space-6) var(--space-6); margin-bottom: var(--space-5);
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);
}
.perfil__hero::before {
    content: ''; position: absolute; inset: 0 0 auto 0; height: var(--banda); z-index: -1;
    background:
        radial-gradient(circle at 100% 0%, rgba(122, 181, 29, 0.35), transparent 45%),
        var(--brand-gradient);
}
.perfil__hero::after { content: ''; position: absolute; left: 0; right: 0; top: var(--banda); height: 4px; background: var(--brand-stripe); }

.perfil__foto { position: relative; z-index: 1; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.perfil__foto-marco {
    position: relative; width: 140px; height: 140px; border-radius: 50%; padding: 4px; display: grid; place-items: center;
    background: var(--surface); box-shadow: var(--shadow-md);
}
.perfil__foto-marco--previa { box-shadow: 0 0 0 3px var(--c-primary), var(--shadow-md); }
.perfil__foto-previa { width: 132px; height: 132px; border-radius: 50%; object-fit: cover; display: block; }
.perfil__foto-carga {
    position: absolute; inset: 4px; border-radius: 50%; display: grid; place-content: center; justify-items: center; gap: 6px;
    background: rgba(0, 0, 0, 0.55); color: #fff; font-weight: 700; font-size: var(--text-sm);
}
.perfil__spinner {
    width: 28px; height: 28px; border-radius: 50%; border: 3px solid rgba(255, 255, 255, 0.35); border-top-color: #fff;
    animation: perfil-giro 0.8s linear infinite;
}
@keyframes perfil-giro { to { transform: rotate(360deg); } }
.perfil__foto-camara {
    position: absolute; right: 4px; top: 100px; width: 40px; height: 40px; border-radius: 50%;
    display: grid; place-items: center; cursor: pointer;
    background: var(--c-primary); color: #fff; border: 3px solid var(--surface); box-shadow: var(--shadow-md);
    transition: transform var(--transition-fast), filter var(--transition-fast);
}
.perfil__foto-camara:hover:not(:disabled) { transform: scale(1.08); filter: brightness(1.08); }
.perfil__foto-camara:focus-visible { outline: 2px solid var(--c-primary); outline-offset: 2px; }
.perfil__foto-camara:disabled { opacity: 0.6; cursor: not-allowed; }
.perfil__previa-etiqueta {
    font-size: var(--text-xs); font-weight: 700; color: var(--c-primary); background: var(--c-primary-soft);
    padding: 2px 10px; border-radius: 999px;
}

/* El texto empieza 20 px por debajo de la banda (banda − padding superior + 20). */
.perfil__hero-info { flex: 1; min-width: min(100%, 280px); padding-top: calc(var(--banda) - 48px + 20px); }
.perfil__hero-info h2 { margin: 0 0 4px; font-size: clamp(1.25rem, 1rem + 1vw, 1.6rem); overflow-wrap: anywhere; }
.perfil__hero-correo { display: flex; align-items: center; gap: 6px; margin: 0 0 10px; color: var(--text-muted); font-size: var(--text-sm); overflow-wrap: anywhere; }
.perfil__chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: var(--space-4); }
.perfil__chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px;
    font-size: var(--text-xs); font-weight: 600; border: 1px solid var(--border); background: var(--bg-subtle); color: var(--text-muted);
}
.perfil__chip--rol { background: var(--c-primary-soft); border-color: transparent; color: var(--c-primary); }
.perfil__chip--ok { color: var(--c-success); background: var(--c-success-soft); border-color: transparent; }
.perfil__chip--alerta { color: var(--c-risk); background: var(--c-risk-soft); border-color: transparent; }
.perfil__punto { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }

.perfil__input-archivo { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.perfil__oculto { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.perfil__foto-acciones { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); }
.perfil__btn-quitar { color: var(--c-risk); }
.perfil__archivo-info { font-size: var(--text-xs); color: var(--text-muted); overflow-wrap: anywhere; }
.perfil__mensaje { display: flex; align-items: center; gap: 6px; margin: var(--space-2) 0 0; font-size: var(--text-xs); color: var(--text-muted); }
.perfil__mensaje--error { color: var(--c-risk); font-weight: 600; }

/* ---- Rejillas y tarjetas ---- */
.perfil__grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr));
    gap: var(--space-5); margin-bottom: var(--space-5); align-items: start;
}
.perfil__sub { display: block; color: var(--text-muted); font-weight: 400; font-size: var(--text-xs); margin-top: 2px; }
.perfil__dato-largo { overflow-wrap: anywhere; }
.perfil__deps { display: flex; flex-wrap: wrap; gap: 6px; }
.perfil__dep { padding: 2px 8px; border-radius: 6px; background: var(--bg-subtle); border: 1px solid var(--border); font-size: var(--text-xs); }
.perfil__resumen { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-top: var(--space-4); }
.perfil__resumen div {
    display: flex; flex-direction: column; gap: 2px; padding: var(--space-3); border-radius: var(--radius-md);
    background: var(--bg-subtle); border: 1px solid var(--border);
}
.perfil__resumen strong { font-family: var(--font-display); font-size: 1.5rem; color: var(--c-primary); line-height: 1.1; }
.perfil__resumen span { font-size: var(--text-xs); color: var(--text-muted); }

.form { display: flex; flex-direction: column; gap: var(--space-4); }
.perfil__form-acciones { display: flex; flex-wrap: wrap; gap: var(--space-2); }

.perfil__reglas {
    list-style: none; margin: calc(var(--space-2) * -1) 0 0; padding: 0;
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 12px; font-size: var(--text-xs); color: var(--text-muted);
}
.perfil__reglas li { display: flex; align-items: center; gap: 6px; transition: color var(--transition-fast); }
.perfil__regla--ok { color: var(--c-success); font-weight: 600; }

.perfil__card-password { scroll-margin-top: calc(var(--topbar-height) + 16px); transition: box-shadow var(--transition-base); }
.perfil__resaltar { box-shadow: 0 0 0 3px var(--c-primary), var(--shadow-md) !important; }

.perfil__confirmar { display: flex; align-items: center; gap: var(--space-4); }
.perfil__confirmar p { margin: 0; }

.perfil__error {
    display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap; padding: var(--space-5);
    background: var(--c-risk-soft); color: var(--c-risk); border-radius: var(--radius-lg);
}
.perfil__error p { margin: 0; flex: 1; }
.perfil__esq-hero { height: 220px; border-radius: var(--radius-xl); margin-bottom: var(--space-5); }
.perfil__esq-card { height: 260px; border-radius: var(--radius-lg); }

/* ---- Celulares ---- */
@media (max-width: 640px) {
    .perfil__hero { --banda: 84px; flex-direction: column; align-items: center; text-align: center; padding: 24px var(--space-4) var(--space-5); gap: var(--space-3); }
    .perfil__hero-info { padding-top: 0; width: 100%; }
    .perfil__hero-correo, .perfil__chips, .perfil__foto-acciones, .perfil__mensaje { justify-content: center; }
    .perfil__foto-acciones :deep(.app-btn) { flex: 1 1 100%; min-height: 46px; }
    .perfil__form-acciones :deep(.app-btn) { flex: 1 1 100%; min-height: 46px; }
    .perfil__reglas { grid-template-columns: 1fr; }
    .perfil__confirmar { flex-direction: column; text-align: center; }
}
</style>
