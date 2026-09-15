<!DOCTYPE html>
<html>
<head><title>IndexedDB debug</title></head>
<body>
    <h1>IndexedDB inhoud</h1>
    <div id="output">Bezig met laden...</div>

    <script>
    (async function () {
        const output = document.getElementById('output');
        output.innerHTML = '';

        function isBlobLike(val) {
            return val instanceof Blob || (val && val.constructor && val.constructor.name === 'Blob');
        }

        // Zoekt recursief naar Blobs/Files in een record (ook in geneste objecten)
        function findBlobs(value, path = []) {
            let found = [];
            if (isBlobLike(value)) {
                found.push({ path, blob: value });
            } else if (Array.isArray(value)) {
                value.forEach((v, i) => found.push(...findBlobs(v, [...path, i])));
            } else if (value && typeof value === 'object') {
                for (const key in value) {
                    found.push(...findBlobs(value[key], [...path, key]));
                }
            }
            return found;
        }

        try {
            const dbs = await indexedDB.databases();

            for (const dbInfo of dbs) {
                const db = await new Promise((resolve, reject) => {
                    const req = indexedDB.open(dbInfo.name);
                    req.onsuccess = () => resolve(req.result);
                    req.onerror = () => reject(req.error);
                });

                const dbHeader = document.createElement('h2');
                dbHeader.textContent = 'Database: ' + dbInfo.name;
                output.appendChild(dbHeader);

                for (const storeName of db.objectStoreNames) {
                    const storeHeader = document.createElement('h3');
                    storeHeader.textContent = 'Store: ' + storeName;
                    output.appendChild(storeHeader);

                    const tx = db.transaction(storeName, 'readonly');
                    const items = await new Promise((resolve, reject) => {
                        const req = tx.objectStore(storeName).getAll();
                        req.onsuccess = () => resolve(req.result);
                        req.onerror = () => reject(req.error);
                    });

                    items.forEach((item, index) => {
                        const blobs = findBlobs(item);

                        const wrapper = document.createElement('div');
                        wrapper.style.border = '1px solid #ccc';
                        wrapper.style.margin = '8px 0';
                        wrapper.style.padding = '8px';

                        const label = document.createElement('div');
                        label.textContent = 'Record ' + index;
                        label.style.fontWeight = 'bold';
                        wrapper.appendChild(label);

                        if (blobs.length > 0) {
                            blobs.forEach(({ path, blob }) => {
                                const btn = document.createElement('button');
                                const sizeMB = (blob.size / 1024 / 1024).toFixed(2);
                                const type = blob.type || 'onbekend type';
                                btn.textContent = `Download bestand (${type}, ${sizeMB} MB) - veld: ${path.join('.') || 'root'}`;
                                btn.style.display = 'block';
                                btn.style.margin = '4px 0';
                                btn.onclick = () => {
                                    const url = URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.href = url;
                                    // probeer een zinnige extensie te kiezen op basis van het mimetype
                                    const ext = (blob.type.split('/')[1] || 'bin').split(';')[0];
                                    a.download = `${dbInfo.name}_${storeName}_${index}.${ext}`;
                                    document.body.appendChild(a);
                                    a.click();
                                    a.remove();
                                    setTimeout(() => URL.revokeObjectURL(url), 10000);
                                };
                                wrapper.appendChild(btn);

                                // Als het een video is: meteen ook een preview tonen
                                if (blob.type.startsWith('video/')) {
                                    const video = document.createElement('video');
                                    video.controls = true;
                                    video.style.maxWidth = '100%';
                                    video.src = URL.createObjectURL(blob);
                                    wrapper.appendChild(video);
                                }
                            });
                        }

                        // Rest van het record (zonder de blobs) als JSON tonen
                        const pre = document.createElement('pre');
                        pre.style.whiteSpace = 'pre-wrap';
                        pre.style.fontSize = '12px';
                        pre.textContent = JSON.stringify(item, (key, val) => {
                            if (isBlobLike(val)) return '[Blob: zie downloadknop hierboven]';
                            return val;
                        }, 2);
                        wrapper.appendChild(pre);

                        output.appendChild(wrapper);
                    });
                }
                db.close();
            }
        } catch (e) {
            output.textContent = 'Fout: ' + e.message;
        }
    })();
    </script>
</body>
</html>