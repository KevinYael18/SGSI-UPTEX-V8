import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import './assets/styles/base.css';

import { useAuthStore } from './stores/auth';

const app = createApp(App).use(createPinia()).use(router);

// Si el backend responde CUENTA_SUSPENDIDA en cualquier petición, la sesión ya
// no es válida: se limpia el usuario en memoria y se muestra el aviso en el login.
window.addEventListener('sgsi:cuenta-suspendida', (evento) => {
    const auth = useAuthStore();
    auth.usuario = null;
    sessionStorage.setItem('sgsi_aviso_login', evento.detail || 'Tu cuenta está suspendida.');
    if (router.currentRoute.value.name !== 'login') router.replace({ name: 'login' });
});

app.mount('#app');