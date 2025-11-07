import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js'; // ✅ nodig omdat laravel-echo dit verwacht, ook bij Reverb

window.Pusher = Pusher;
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT || 80,
    wssPort: import.meta.env.VITE_REVERB_PORT || 443,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

// ✅ Verbinding testen
window.Echo.connector.socket.onopen = () => {
    console.log('🔌 Verbonden met Reverb WebSocket-server!');
};
window.Echo.connector.socket.onerror = (err) => {
    console.error('❌ WebSocket fout:', err);
};
window.Echo.connector.socket.onclose = () => {
    console.warn('⚠️ Verbinding met Reverb verbroken');
};

// ✅ Testkanaal
window.Echo.channel('test-channel')
    .listen('TestEvent', (e) => {
        console.log('✅ Ontvangen via Reverb:', e.message);
    });
