const SW_VERSION = 'v9-timeout-fix'; // 👈 Versie omhoog om update te forceren
const API = self.location.origin;

const DB_NAME = "R2UploadDB";
const STORE = "pending";

// 🛑 LOCKING MECHANISME
let queueRunningPromise = null;

// --- IndexedDB Helpers ---
function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: "id", autoIncrement: true });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e);
    });
}

async function getAll() {
    try {
        const db = await openDB();
        return new Promise((resolve) => {
            const req = db.transaction(STORE).objectStore(STORE).getAll();
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => resolve([]); // Fallback leeg array
        });
    } catch (e) {
        return [];
    }
}

async function deleteItem(id) {
    try {
        const db = await openDB();
        db.transaction(STORE, "readwrite").objectStore(STORE).delete(id);
    } catch (e) { console.error("DB Delete error", e); }
}

async function addItem(data) {
    const currentItems = await getAll();
    const exists = currentItems.find(item => item.name === data.name);
    if (exists) return; 

    const db = await openDB();
    // Blob check voor Safari/iOS quirks
    let blobData = (data.blob instanceof Blob) ? await data.blob.arrayBuffer() : data.blob;

    const clean = {
        name: data.name,
        fileType: data.fileType,
        task_id: data.task_id,
        namespace_id: data.namespace_id || "",
        root_path: data.root_path || "", 
        csrf: data.csrf,
        blob: blobData
    };

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, "readwrite");
        const store = tx.objectStore(STORE);
        const req = store.add(clean);
        req.onsuccess = () => resolve();
        req.onerror = (e) => reject(e);
    });
}

// --- Lifecycle ---
self.addEventListener("install", () => self.skipWaiting());

// 👇 FIX 1: Auto-start bij activeren (na refresh)
self.addEventListener("activate", (event) => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            processQueue() // Direct checken of er nog iets ligt!
        ])
    );
});

// --- Message Handler ---
self.addEventListener("message", async (event) => {
    if (event.origin !== self.location.origin) return;
    
    if (event.data?.type === "FORCE_PROCESS") {
        console.log("⚡ Force Process commando ontvangen");
        // We roepen hem gewoon aan. De lock functie handelt dubbel werk af.
        await processQueue();
    }
    
    if (event.data?.type === "ADD_UPLOAD") {
        await addItem(event.data);
        // Probeer sync, anders direct verwerken
        if ("sync" in self.registration) {
            try { await self.registration.sync.register("sync-r2-uploads"); } 
            catch (e) { processQueue(); }
        } else {
            processQueue();
        }
        sendToClients({ type: "QUEUED", file: event.data.name });
    }
});

async function sendToClients(msg) {
    const allClients = await self.clients.matchAll({ includeUncontrolled: true });
    for (const client of allClients) client.postMessage(msg);
}

self.addEventListener("sync", async (event) => {
    if (event.tag === "sync-r2-uploads") await processQueue();
});

// --- SAFE QUEUE PROCESSOR (Met Timeout Fix) ---
async function processQueue() {
    // Check of we al draaien
    if (queueRunningPromise) {
        console.log("SW: 🔒 Queue draait al (Lock actief).");
        return queueRunningPromise;
    }

    queueRunningPromise = (async () => {
        console.log("SW: 🚀 Start verwerking queue...");
        let done = 0; 
        
        while (true) {
            // Check internet
            if (!self.navigator.onLine) {
                console.log("SW: 📵 Offline. Pauze.");
                break; 
            }

            const items = await getAll();
            if (items.length === 0) break; // Klaar!

            const item = items[0]; 
            
            // Progressie berekening
            const totalInQueue = items.length;
            const realTotal = done + totalInQueue;
            const currentNum = done + 1;

            try {
                sendToClients({ 
                    type: "PROGRESS", 
                    current: currentNum, 
                    total: realTotal, 
                    name: item.name 
                });

                const form = new FormData();
                form.append("photos[]", new Blob([item.blob], { type: item.fileType }), item.name);
                form.append("namespace_id", item.namespace_id || ""); 
                form.append("root_path", item.root_path || ""); 
                form.append("_token", item.csrf);

                const uploadUrl = new URL(`${API}/tasks/${item.task_id}/upload-temp`);
                uploadUrl.searchParams.append("sw_bypass", "true");

                // 👇 FIX 2: TIMEOUT TOEVOEGEN
                // Als de server na 60 seconden niet antwoordt, breken we af.
                // Dit voorkomt dat de lock 'voor altijd' blijft hangen.
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000); 

                const res = await fetch(uploadUrl.toString(), {
                    method: "POST",
                    headers: { 
                        "X-CSRF-TOKEN": item.csrf,
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    },
                    credentials: 'include',
                    body: form,
                    signal: controller.signal // Koppel de abort controller
                });

                clearTimeout(timeoutId); // Timeout wissen als het gelukt is

                if (res.status === 429) throw new Error("RATE_LIMIT");

                const textData = await res.text();
                let json;
                try { json = JSON.parse(textData); } 
                catch (e) { throw new Error(`Server Error (Geen JSON): ${res.status}`); }

                if (!res.ok) throw new Error(json.message || `Server fout: ${res.status}`);

                console.log(`SW: ✅ Upload OK: ${item.name}`);
                await deleteItem(item.id);
                
                done++;
                sendToClients({ type: "UPLOADED", name: item.name });
                
                await new Promise(r => setTimeout(r, 200)); // Korte adempauze

            } catch (err) {
                console.warn(`SW: ❌ Fout bij '${item.name}':`, err.message);
                
                // Bij een abort (timeout) of netwerkfout -> pauzeer even
                if (err.name === 'AbortError' || ["RATE_LIMIT", "Failed to fetch", "NetworkError"].some(s => err.message.includes(s)) || !self.navigator.onLine) {
                    console.log("SW: 🛑 Pauze door netwerkfout of timeout.");
                    break; // Breek de loop, de lock gaat eraf, volgende keer beter.
                }
                
                // Bij een 'harde' fout (bijv. 500 error) wachten we iets langer en proberen opnieuw
                await new Promise(r => setTimeout(r, 2000));
            }
        }
        
        console.log("SW: 🏁 Queue loop gestopt.");
        
        // Als alles op is, stuur complete
        const remaining = await getAll();
        if (remaining.length === 0) {
            sendToClients({ type: "COMPLETE" });
        }
        
    })();

    // Wacht tot de promise klaar is en geef de lock vrij
    await queueRunningPromise;
    queueRunningPromise = null;
    console.log("SW: 🔓 Lock vrijgegeven.");
}

// --- Fetch Interceptor ---
self.addEventListener("fetch", (event) => {
    const url = new URL(event.request.url);
    if (url.searchParams.get("sw_bypass") === "true") return;

    if (url.pathname.includes('/upload-temp') && event.request.method === "POST") {
        event.respondWith(saveToQueueAndRespond(event.request));
    }
});

async function saveToQueueAndRespond(request) {
    try {
        const formData = await request.clone().formData();
        const file = formData.get("photos[]") || formData.get("file"); 

        await addItem({
            name: file.name,
            fileType: file.type,
            blob: file, 
            task_id: request.url.split('/tasks/')[1].split('/')[0],
            namespace_id: formData.get("namespace_id"),
            root_path: formData.get("root_path"),
            csrf: formData.get("_token")
        });

        // Trigger queue direct
        if ("sync" in self.registration) {
            self.registration.sync.register("sync-r2-uploads").catch(() => processQueue());
        } else {
            processQueue();
        }

        return new Response(JSON.stringify({ success: true, queued: true }), { 
            status: 200, headers: { "Content-Type": "application/json" } 
        });
    } catch (e) {
        return new Response(JSON.stringify({ error: "Storage failed" }), { status: 500 });
    }
}