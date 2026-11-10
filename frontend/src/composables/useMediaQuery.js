import { ref, onMounted, onBeforeUnmount } from 'vue';

/** Ref reactivo que indica si se cumple una media query (solo presentación). */
export function useMediaQuery(consulta) {
    const coincide = ref(typeof window !== 'undefined' && window.matchMedia ? window.matchMedia(consulta).matches : true);
    let mql = null;
    const actualizar = (e) => { coincide.value = e.matches; };

    onMounted(() => {
        if (!window.matchMedia) return;
        mql = window.matchMedia(consulta);
        coincide.value = mql.matches;
        mql.addEventListener ? mql.addEventListener('change', actualizar) : mql.addListener(actualizar);
    });
    onBeforeUnmount(() => {
        if (!mql) return;
        mql.removeEventListener ? mql.removeEventListener('change', actualizar) : mql.removeListener(actualizar);
    });

    return coincide;
}
