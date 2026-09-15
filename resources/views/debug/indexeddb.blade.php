<!DOCTYPE html>
<html>
<head><title>IndexedDB debug</title></head>
<body>
    <h1>IndexedDB inhoud</h1>
    <pre id="output">Bezig met laden...</pre>

    <script>
    (async function () {
        const output = document.getElementById('output');
        try {
            const dbs = await indexedDB.databases();
            const result = {};

            for (const dbInfo of dbs) {
                const db = await new Promise((resolve, reject) => {
                    const req = indexedDB.open(dbInfo.name);
                    req.onsuccess = () => resolve(req.result);
                    req.onerror = () => reject(req.error);
                });

                result[dbInfo.name] = {};

                for (const storeName of db.objectStoreNames) {
                    const tx = db.transaction(storeName, 'readonly');
                    const items = await new Promise((resolve, reject) => {
                        const req = tx.objectStore(storeName).getAll();
                        req.onsuccess = () => resolve(req.result);
                        req.onerror = () => reject(req.error);
                    });
                    result[dbInfo.name][storeName] = items;
                }
                db.close();
            }

            output.textContent = JSON.stringify(result, null, 2);
        } catch (e) {
            output.textContent = 'Fout: ' + e.message;
        }
    })();
    </script>
</body>
</html>