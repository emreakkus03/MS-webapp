import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js'; // ✅ BELANGRIJK: nodig voor Reverb

window.Pusher = Pusher;

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT || 6001,
    wssPort: import.meta.env.VITE_REVERB_PORT || 6001,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
});

// ✅ Testkanaal voor debug
window.Echo.channel('test-channel')
    .listen('TestEvent', (e) => {
        console.log('✅ Ontvangen via Reverb:', e.message);
    });