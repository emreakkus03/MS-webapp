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

        // Duck-typing: alles wat zich als Blob gedraagt (Blob, File, of een object met .size/.type/.arrayBuffer)
        function isBlobLike(val) {
            if (!val) return false;
            if (val instanceof Blob) return true;
            if (val instanceof File) return true;
            if (typeof val === 'object' &&
                typeof val.size === 'number' &&
                typeof val.arrayBuffer === 'function') {
                return true;
            }
            return false;
        }

        function isArrayBufferLike(val) {
            return val instanceof ArrayBuffer ||
                (val && val.buffer instanceof ArrayBuffer) || // TypedArray zoals Uint8Array
                (val && val.constructor && val.constructor.name === 'ArrayBuffer');
        }

        function describeType(val) {
            if (val === null) return 'null';
            if (val === undefined) return 'undefined';
            if (isBlobLike(val)) return `Blob-achtig (constructor: ${val.constructor?.name}, size: ${val.size}, type: "${val.type}")`;
            if (isArrayBufferLike(val)) return `ArrayBuffer-achtig (constructor: ${val.constructor?.name}, byteLength: ${val.byteLength ?? val.buffer?.byteLength})`;
            if (typeof val === 'string') return `string (lengte ${val.length})`;
            if (Array.isArray(val)) return `array (${val.length} items)`;
            if (typeof val === 'object') return `object (constructor: ${val.constructor?.name || 'onbekend'})`;
            return typeof val;
        }

        // Zoekt recursief naar Blobs/ArrayBuffers in een record
        function findBinary(value, path = []) {
            let found = [];
            if (isBlobLike(value)) {
                found.push({ path, kind: 'blob', data: value });
            } else if (isArrayBufferLike(value)) {
                found.push({ path, kind: 'arraybuffer', data: value });
            } else if (Array.isArray(value)) {
                value.forEach((v, i) => found.push(...findBinary(v, [...path, i])));
            } else if (value && typeof value === 'object') {
                for (const key in value) {
                    found.push(...findBinary(value[key], [...path, key]));
                }
            }
            return found;
        }

        function makeDownloadButton(blob, filename, labelPrefix) {
            const btn = document.createElement('button');
            const sizeMB = (blob.size / 1024 / 1024).toFixed(2);
            btn.textContent = `${labelPrefix} (${blob.type || 'onbekend type'}, ${sizeMB} MB)`;
            btn.style.display = 'block';
            btn.style.margin = '4px 0';
            btn.style.padding = '6px';
            btn.onclick = () => {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 10000);
            };
            return btn;
        }

        function makeShareButton(blob, filename) {
            const btn = document.createElement('button');
            btn.textContent = `Delen / opslaan via Android`;
            btn.style.display = 'block';
            btn.style.margin = '4px 0';
            btn.style.padding = '6px';
            btn.onclick = async () => {
                try {
                    const file = new File([blob], filename, { type: blob.type || 'application/octet-stream' });
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: filename });
                    } else {
                        alert('Delen van dit bestandstype wordt niet ondersteund. Probeer "Open in tab".');
                    }
                } catch (e) {
                    alert('Fout bij delen: ' + e.message);
                }
            };
            return btn;
        }

        function makeOpenButton(blob) {
            const btn = document.createElement('button');
            btn.textContent = 'Open in nieuw tabblad';
            btn.style.display = 'block';
            btn.style.margin = '4px 0';
            btn.style.padding = '6px';
            btn.onclick = () => {
                const url = URL.createObjectURL(blob);
                window.open(url, '_blank');
            };
            return btn;
        }

        function makeDeleteRecordButton(dbName, storeName, key, wrapper) {
            const btn = document.createElement('button');
            btn.textContent = '🗑 Verwijder dit record';
            btn.style.display = 'block';
            btn.style.margin = '8px 0';
            btn.style.padding = '6px';
            btn.style.background = '#fdd';
            btn.onclick = async () => {
                if (!confirm('Weet je zeker dat je dit record wilt verwijderen? Dit kan niet ongedaan gemaakt worden.')) return;
                try {
                    const db = await new Promise((resolve, reject) => {
                        const req = indexedDB.open(dbName);
                        req.onsuccess = () => resolve(req.result);
                        req.onerror = () => reject(req.error);
                    });
                    await new Promise((resolve, reject) => {
                        const tx = db.transaction(storeName, 'readwrite');
                        tx.objectStore(storeName).delete(key);
                        tx.oncomplete = () => resolve();
                        tx.onerror = () => reject(tx.error);
                    });
                    db.close();
                    wrapper.style.opacity = '0.4';
                    wrapper.style.textDecoration = 'line-through';
                    btn.textContent = '✅ Verwijderd';
                    btn.disabled = true;
                } catch (e) {
                    alert('Fout bij verwijderen: ' + e.message);
                }
            };
            return btn;
        }

        function makeClearStoreButton(dbName, storeName, container) {
            const btn = document.createElement('button');
            btn.textContent = `🗑 Leeg hele store "${storeName}"`;
            btn.style.display = 'block';
            btn.style.margin = '12px 0';
            btn.style.padding = '8px';
            btn.style.background = '#f88';
            btn.style.fontWeight = 'bold';
            btn.onclick = async () => {
                if (!confirm(`Weet je zeker dat je ALLE records in store "${storeName}" wilt verwijderen? Dit kan niet ongedaan gemaakt worden.`)) return;
                try {
                    const db = await new Promise((resolve, reject) => {
                        const req = indexedDB.open(dbName);
                        req.onsuccess = () => resolve(req.result);
                        req.onerror = () => reject(req.error);
                    });
                    await new Promise((resolve, reject) => {
                        const tx = db.transaction(storeName, 'readwrite');
                        tx.objectStore(storeName).clear();
                        tx.oncomplete = () => resolve();
                        tx.onerror = () => reject(tx.error);
                    });
                    db.close();
                    container.innerHTML = '<em>Store geleegd. Herlaad de pagina om te verversen.</em>';
                } catch (e) {
                    alert('Fout bij legen: ' + e.message);
                }
            };
            return btn;
        }

        try {
            const dbs = await indexedDB.databases();

            if (!dbs || dbs.length === 0) {
                output.textContent = 'Geen databases gevonden via indexedDB.databases(). Vul evt. de naam handmatig in de code in.';
                return;
            }

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
                    const store = tx.objectStore(storeName);
                    const items = await new Promise((resolve, reject) => {
                        const req = store.getAll();
                        req.onsuccess = () => resolve(req.result);
                        req.onerror = () => reject(req.error);
                    });
                    const keys = await new Promise((resolve, reject) => {
                        const req = store.getAllKeys();
                        req.onsuccess = () => resolve(req.result);
                        req.onerror = () => reject(req.error);
                    });

                    // Knop om in één keer de hele store leeg te maken
                    output.appendChild(makeClearStoreButton(dbInfo.name, storeName, output));

                    items.forEach((item, index) => {
                        const recordKey = keys[index];
                        const wrapper = document.createElement('div');
                        wrapper.style.border = '1px solid #ccc';
                        wrapper.style.margin = '8px 0';
                        wrapper.style.padding = '8px';

                        const label = document.createElement('div');
                        label.textContent = 'Record ' + index;
                        label.style.fontWeight = 'bold';
                        wrapper.appendChild(label);

                        // Debug: toon het type van elk top-level veld
                        if (item && typeof item === 'object' && !isBlobLike(item)) {
                            const debugList = document.createElement('ul');
                            debugList.style.fontSize = '12px';
                            debugList.style.color = '#555';
                            for (const key in item) {
                                const li = document.createElement('li');
                                li.textContent = `${key}: ${describeType(item[key])}`;
                                debugList.appendChild(li);
                            }
                            wrapper.appendChild(debugList);
                        } else {
                            const debugLine = document.createElement('div');
                            debugLine.style.fontSize = '12px';
                            debugLine.style.color = '#555';
                            debugLine.textContent = 'Record zelf is: ' + describeType(item);
                            wrapper.appendChild(debugLine);
                        }

                        const binaries = findBinary(item);

                        binaries.forEach(({ path, kind, data }) => {
                            let blob;
                            let mimeGuess = 'video/quicktime'; // .mov fallback

                            if (kind === 'blob') {
                                blob = data;
                            } else if (kind === 'arraybuffer') {
                                // ArrayBuffer/TypedArray omzetten naar Blob, met gok voor mimetype
                                const buf = data.buffer instanceof ArrayBuffer ? data.buffer : data;
                                blob = new Blob([buf], { type: mimeGuess });
                            }

                            if (blob) {
                                const ext = (blob.type.split('/')[1] || 'mov').split(';')[0];
                                const filename = `${dbInfo.name}_${storeName}_${index}_${path.join('-') || 'root'}.${ext}`;

                                const fieldLabel = document.createElement('div');
                                fieldLabel.style.marginTop = '6px';
                                fieldLabel.textContent = `Gevonden binair veld: ${path.join('.') || 'root'} (${kind})`;
                                wrapper.appendChild(fieldLabel);

                                wrapper.appendChild(makeDownloadButton(blob, filename, 'Download bestand'));
                                wrapper.appendChild(makeShareButton(blob, filename));
                                wrapper.appendChild(makeOpenButton(blob));

                                if (blob.type.startsWith('video/') || kind === 'arraybuffer') {
                                    const video = document.createElement('video');
                                    video.controls = true;
                                    video.style.maxWidth = '100%';
                                    video.src = URL.createObjectURL(blob);
                                    wrapper.appendChild(video);
                                }
                            }
                        });

                        if (binaries.length === 0) {
                            const noBin = document.createElement('div');
                            noBin.style.color = 'red';
                            noBin.textContent = 'Geen binair veld gevonden in dit record (zie types hierboven).';
                            wrapper.appendChild(noBin);
                        }

                        // Rest van het record als JSON tonen (binaire velden vervangen door placeholder)
                        const pre = document.createElement('pre');
                        pre.style.whiteSpace = 'pre-wrap';
                        pre.style.fontSize = '11px';
                        try {
                            pre.textContent = JSON.stringify(item, (key, val) => {
                                if (isBlobLike(val)) return '[Blob]';
                                if (isArrayBufferLike(val)) return '[ArrayBuffer]';
                                return val;
                            }, 2);
                        } catch (e) {
                            pre.textContent = '(kon record niet als JSON tonen: ' + e.message + ')';
                        }
                        wrapper.appendChild(pre);

                        // Verwijderknop voor dit specifieke record
                        wrapper.appendChild(makeDeleteRecordButton(dbInfo.name, storeName, recordKey, wrapper));

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