const SW_VERSION = 'v8-durable-photo-receipts';
importScripts('/js/photo-queue.js?v=8');

// No automatic skipWaiting: do not replace an uploading worker mid-flight.
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
async function notify(message) {
    const clients = await self.clients.matchAll({ includeUncontrolled: true });
    clients.forEach(client => client.postMessage(message));
}
async function processQueue() {
    const result = await PhotoQueue.process(notify);
    // A failed sync must reject so the browser knows delivery is incomplete.
    if (result.remaining) throw new Error('Photos remain in the durable local queue.');
}
self.addEventListener('message', event => {
    if (event.origin !== self.location.origin || !event.data) return;
    const { type } = event.data;
    if (['FORCE_PROCESS', 'PROCESS_QUEUE'].includes(type)) {
        event.waitUntil(processQueue());
    } else if (type === 'ADD_UPLOAD') {
        event.waitUntil((async () => {
            await PhotoQueue.add(event.data);
            await notify({ type: 'QUEUED', file: event.data.name });
            if (self.registration.sync) await self.registration.sync.register('sync-r2-uploads');
            await processQueue();
        })());
    } else if (type === 'SKIP_WAITING') {
        event.waitUntil(self.skipWaiting());
    }
});
self.addEventListener('sync', event => {
    if (event.tag === 'sync-r2-uploads') event.waitUntil(processQueue());
});
// Network uploads are not intercepted and disguised as successful persistence.
