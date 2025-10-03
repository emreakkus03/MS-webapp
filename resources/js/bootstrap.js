import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';   // Reverb gebruikt zelfde protocol

window.Pusher = Pusher;

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo configuratie voor Reverb
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'localkey',
    wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: false,
    enabledTransports: ['ws'],
});

// ✅ Testkanaal voor debug
window.Echo.channel('test-channel')
    .listen('TestEvent', (e) => {
        console.log('Ontvangen via Reverb:', e.message);
    });
