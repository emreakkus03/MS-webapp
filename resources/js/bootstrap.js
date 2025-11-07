import axios from 'axios';
import Echo from 'laravel-echo';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ✅ Dynamische setup voor Reverb (werkt lokaal én in Laravel Cloud)
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'localkey',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 443,
    scheme: import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http'),
    forceTLS: window.location.protocol === 'https:',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

// ✅ Testkanaal
window.Echo.channel('test-channel')
    .listen('TestEvent', (e) => {
        console.log('✅ Ontvangen via Reverb:', e.message);
    });

// ✅ Verbinding logs voor debug
window.Echo.connector.socket.onopen = () => {
    console.log('🔌 Verbonden met Reverb WebSocket-server!');
};
window.Echo.connector.socket.onerror = (err) => {
    console.error('❌ WebSocket fout:', err);
};
window.Echo.connector.socket.onclose = () => {
    console.warn('⚠️ Verbinding met Reverb verbroken');
};
