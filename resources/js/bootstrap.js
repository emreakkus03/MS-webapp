import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// ✅ Globaal beschikbaar maken
window.Pusher = Pusher;

// Axios setup
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ✅ Laravel Echo configuratie voor Pusher
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
});

// ✅ Testkanaal (optioneel)
window.Echo.channel('test-channel')
    .listen('TestEvent', (e) => {
        console.log('✅ Ontvangen via Pusher:', e.message);
    });
