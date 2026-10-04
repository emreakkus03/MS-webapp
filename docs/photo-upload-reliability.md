# Photo upload reliability investigation and repair

Investigation of the repository on 2026-09-25. Findings below describe the **pre-change code**, not a production incident reconstructed from live logs. No production database, R2 bucket, Dropbox account, destructive command, or worker was accessed. All delivery tests use isolated SQLite, fake storage, and mocked HTTP.

## 1. Actual architecture before the repair

```text
routes/web.php GET /dashboard/user
  DashboardController::user() → resources/views/dashboard/user.blade.php
  file selection: preview only, original files live in the page
  finishForm submit → compressInBatches()
  window.addToUploadQueue()
    R2UploadDB v2 / pending (auto-increment primary key, ArrayBuffer bytes)
  window.sendToSW({type: PROCESS_QUEUE})
    public/sw.js message handler → processQueue()
      GET /csrf-token
      POST /r2/upload?sw_bypass=true (multipart photo through Laravel)
        R2Controller::uploadFromSW()
        Storage::disk('r2')->putFileAs(addressFolder, integerID_filename)
      POST /r2/register-upload (separate HTTP request)
        R2Controller::registerUpload()
        R2PendingUpload → r2_pending_uploads
        dispatch MoveToDropboxJob → queue 'uploads'
      deleteItem() from IndexedDB
        MoveToDropboxJob::handle()
        DropboxService: refresh token, discover namespace, obtain token
        Guzzle POST https://content.dropboxapi.com/2/files/upload
        R2 delete → pending row delete → tasks.photo append

Independent of that chain:
  finishForm submit → POST /tasks/{task}/finish → TaskController::finish()
  changes task status and sibling task statuses without checking photos

Recovery:
  routes/console.php hourly r2:retry-all → pending/failed rows older than 5 min
  → MoveToDropboxJob on 'uploads'
```

R2 is **not uploaded directly by the current dashboard**. There is no multipart/chunked R2 protocol in this path. Dropbox upload sessions are also not used by it. The browser sends compressed files in individual HTTP multipart/form-data requests to Laravel; Laravel writes entire objects to R2; the job uses Dropbox's single-file upload endpoint.

`resources/js/app.js`, `bootstrap.js`, `echo.js`, both layout components, dashboard controller, task/schedule views and controllers, middleware, services, jobs, models, migrations, Artisan commands, queue/filesystem settings, and deployment/development scripts were included in the route/call-site review. Repository-wide searches covered IndexedDB, sync, R2, Dropbox, sessions, dispatch, photo/status/completion, deletes/truncates/unlinks, and pending uploads. Unrelated notification/material/deleted-order operations were separated from photo cleanup.

### Original handoff audit

| Transition | Acknowledgement | Network disappears | Source retained? | Retry/duplicates | Early deletion/false success | Races |
|---|---|---|---|---|---|---|
| Selection → local storage | Individual IDB add request succeeds, not transaction commit | Compression CDN can fail to load; closing page loses unsaved selection | Only page memory until submit/compression complete | Filename-based “dedup” discards different images | Per-file errors ignored; form still closes/resets | Read-all then add in separate transactions; other tab/SW can add meanwhile |
| IDB → worker | postMessage has no receipt | No guaranteed future execution | IDB remains if committed | Background Sync, online event, minute interval; SW timers unreliable | Sync resolves despite remaining failures, misleading browser scheduler | Processing flag set after `await getAll`; simultaneous processors possible |
| Worker → Laravel/R2 | `path` returned; new object size within 1024 bytes, or `exists()` | Ambiguous response causes replay | Browser normally retains at this step | Integer ID reused across devices; non-atomic cache flag | Another photo with same key accepted; mismatch object deleted before Dropbox | Cache `has`/`put`, 60-second expiration, no owner token |
| R2 → registration | Any HTTP `reg.ok`, including redirected login HTML | Unregistered R2 orphan or DB row without queued job | R2 remains, but browser can delete on false registration success | No unique constraint; duplicates; already-queued doesn't prove dispatch | No R2 existence/content validation; no durable completed receipt | Query-then-create, immediate dispatch, upload/register separate requests |
| R2 → Dropbox | HTTP 200 only | Job catches error and normally returns successfully | R2 usually remains on failure | Laravel retry bypassed; hourly command may retry; autorename duplicates uncertain success | Missing stream skipped; final log claims all batches succeeded | 300-second job vs 90-second reservation; manual retry overlaps |
| Dropbox → bookkeeping/cleanup | HTTP 200 before deletion | Cleanup/database failure leaves incomplete tracking | R2 and row deleted before task reference saved | No receipt to recognize previous success | File may exist in Dropbox but be absent in app; R2 fallback already removed | All rows with same R2 path deleted, stale task photo list overwrite |
| Task completion | HTTP 200 on status update alone | Status or photos can be committed independently | No intentional IDB clear, but form memory reset even after save failures | Lost response can advance `open → in behandeling → finished` on retry | Completion says nothing about safe photo handoff | Bulk sibling status updates, ongoing uploads, page reset |

### Every photo-related deletion before the repair

- `sw.js::deleteItem`: after upload returned a path and registration returned any 2xx; registration did not check R2 or JSON. Request success was used instead of transaction completion for local deletion.
- `R2Controller::uploadFromSW`: deletes R2 on size mismatch, before Dropbox acknowledgement.
- `MoveToDropboxJob::handle`: deletes R2 and **all** rows with matching `r2_path` after Dropbox HTTP 200, before task reference commit. No metadata/size/hash validation.
- `UploadToDropboxJob::handle`: deletes the local source after the HTTP call, before testing status and before saving the task reference.
- `ClearR2Bucket::handle`: truncates the whole pending table, then deletes every R2 object. The admin controller invokes it with `--force`.
- Database task → pending foreign key: cascades on task deletion; team/address deletion can cascade through tasks.
- `R2DownloadAll`: unlinks the previous ZIP before making a replacement; uses basename for entries, allowing collisions. `r2:publish-zip` actually copies the ZIP to public, despite its “move” name; it does not remove R2 objects.
- `resources/views/debug/indexeddb.blade.php`: explicit debug deletion controls. Its route is commented out; not reachable from the normal dashboard.
- Warehouse temporary files concern a separate spreadsheet upload, not task photos. No automatic IDB task-completion clear or R2 lifecycle policy is defined in the repository. External bucket policies cannot be inferred.

## 2. Root causes and severity

| Severity | File / function | Failure mechanism |
|---|---|---|
| Critical | `dashboard/user.blade.php::addToUploadQueue`, `sw.js::addItem` | Different photos with the same filename/task/folder are discarded as duplicates. Ignored storage errors plus form reset lose photos never committed locally. |
| Critical | `R2Controller::uploadFromSW` | Device-local integers form globally shared object keys. `exists()` accepts another tablet's object without comparing contents; both callers can delete their local copies while only one image exists remotely. |
| Critical | `R2Controller::registerUpload`, `sw.js::processQueue` | Registration never checks R2 and the worker accepts any 2xx, including a login redirect. Can delete the only actual copy from IDB after an invalid handoff. |
| Critical | `ClearR2Bucket::handle`, `R2ManagementController::clearBucket` | Deletes unconfirmed objects and truncates recovery metadata, with no Dropbox verification. |
| High | `MoveToDropboxJob::handle` | Catches Dropbox errors, including 429, and returns; skips missing streams. Queue reports success and normal retries/failed_jobs don't record these failures. |
| High | `MoveToDropboxJob::handle` | Deletes R2/receipts before durable bookkeeping; parallel stale `tasks.photo` writes hide successfully delivered photos. Autorename on replay creates uncontrolled duplicates. |
| High | `R2Controller` registration/lock logic | Non-atomic locks and registration; duplicate records; dispatch failure after DB insert can subsequently be acknowledged as “already queued.” |
| High | `sw.js` message/queue/sync handlers | No waitUntil for messages, guard set too late, swallowed failures, reliance on timers and Background Sync; no durable attempts/error/lease metadata. |
| High | `TaskController::finish`, dashboard submit | Finishes before safe handoff, ignores expected count, and advances status again on replay. Unacknowledged photos do not gate completion. |
| High | original pending-table foreign key | Task/team/address deletion erases the destination metadata needed by retries. Active job also refuses to deliver after task deletion. |
| High | legacy `UploadToDropboxJob` | Local source removed before checking success and committing the reference. File/stream/task errors return instead of retrying. |
| Medium | `config/queue.php`, `.laravel.cloud.yaml`, `composer.json` | 90-second default retry_after vs 300-second job; no explicit `uploads` selection in supplied worker configuration/development command. Production worker arguments are unknown, so wrong queue selection is a deployment risk, not a verified incident. |
| Medium | `tasks.photo`, task/schedule rendering and preview | Comma-separated paths split folder names containing commas; Perceel 1 previews lack the namespace actually used. Photos may be present remotely but appear missing. |
| Medium | `RetryAllR2Pending`, schedule, R2 manager | Only pending/failed are retried; processing/cleanup states ignored; hourly lag; no recovery of unregistered objects; repeated dispatches can overlap. |
| Medium | `R2DownloadAll` | Replaces prior backup before success; basename collisions can make a recovery ZIP incomplete. |
| Medium | dashboard module import | Compression CDN failure prevents the whole module, including the finish handler, from loading. |
| Low / legacy | Dropbox upload-session helpers | No persisted cursor/session recovery, no incorrect-offset reconciliation, missing consistent business namespace/user headers on append/finish, no 429/401 retry there. No active dashboard call chain reaches these helpers. |

## 3. Active versus legacy code

- **Active dashboard job: `MoveToDropboxJob`.** Proven by `/dashboard/user` → Blade `PROCESS_QUEUE` → SW `/r2/upload` + `/r2/register-upload` → `R2Controller::registerUpload` dispatch with `onQueue('uploads')`.
- **Legacy but reachable: `UploadToDropboxJob`.** `/tasks/{task}/upload-temp` → `TaskController::uploadTemp` → local `temp/uploads` → `Bus::batch` of `UploadToDropboxJob`, queue `uploads`. No repository frontend caller. Existing queued payloads and the route are preserved; source deletion was hardened.
- `/tasks/{id}/upload-photo` accepts already-uploaded R2 paths and dispatches Move jobs; no current frontend caller. Now uses the same verified registration logic.
- `/r2/presigned-url`, `/r2/upload-urls`, `/r2/check-file`: older direct-to-R2 support, still routed; current dashboard does not call them. Presigned URLs can still create unregistered objects if an external/old client fails before registration. Such objects are preserved, not auto-deleted.
- `/dropbox/start-session` → `TaskController::startDropboxSession` → `DropboxService::startUploadSession`; returns session/token to a legacy client. `appendToSession`, `finishUploadSession`, `uploadStreamFast` have no repository callers. No reason to rewrite session handling to repair this dashboard.
- `/dropbox/upload-adres-photos` points to an absent `uploadAdresPhotos` method. No frontend caller; existing broken legacy route was not removed.
- Unused namespace helper in Move job and obsolete frontend success function were removed while changing those functions; unrelated features and routes were retained.

## 4. Files changed

| File | Purpose |
|---|---|
| `public/js/photo-queue.js` (new) | Shared v2 IDB access, UUID identities, committed transactions, atomic expiring leases, backoff, strict durable receipts, original drafts, byte immutability once attempted, foreground fallback. |
| `public/sw.js` | Reuse queue; attach work synchronously to waitUntil; reject incomplete sync; remove false-success interception and timer assumptions; v8, no automatic skipWaiting. |
| `resources/views/dashboard/user.blade.php` | Persist selected originals as drafts, restore/export local copies, optional compression, retryable finish manifest, completion after acknowledgements, nonblocking upload status/recovery. |
| `app/Http/Controllers/R2Controller.php` | Receipt before R2, stable identity, atomic lock, exact content verification, combined acknowledgement, verified legacy registration, safe logs. |
| `app/Services/PhotoDelivery.php` (new) | Common outbox dispatch, R2 validation, logging context, guarded/retryable cleanup. |
| `app/Services/DropboxPhotoUploader.php` (new) | Validate spooled bytes against expected R2 hash/size, deterministic Dropbox upload, validate returned metadata/content hash, token refresh and 429 delay. |
| `app/Services/DropboxPhotoRetry.php` (new) | Carry Dropbox Retry-After to queue release. |
| `app/Services/DropboxService.php` | Explicit access-token renewal and bounded token/namespace requests. |
| `app/Jobs/MoveToDropboxJob.php` | Per-object lock, singular work units (fan out old batches), durable done receipt, task row locking, retries, cleanup-only replay, delivery after task deletion. |
| `app/Jobs/UploadToDropboxJob.php` | Preserve old local job compatibility, verify contents and save reference before local deletion, retry missing/error cases. |
| `app/Models/R2PendingUpload.php` | Receipt/retry fields and timestamp casts. |
| `database/migrations/2026_09_25_000001_make_photo_delivery_recoverable.php` (new) | Nullable unique UUID and verification/attempt/receipt timestamps; keep existing rows; remove cascading receipt deletion; idempotent task-finish receipts. No old migration edited. |
| `app/Http/Controllers/TaskController.php` | Require confirmed IDs for finish, lock task and save replay receipt, don't propagate status to known unconfirmed sibling tasks, verified alternate R2 registration, receipt-aware preview namespace. |
| `app/Console/Commands/RetryAllR2Pending.php` | Retry all stale unfinished delivery/cleanup states, including receiving/processing and missing-source records. |
| `app/Console/Commands/ClearR2Bucket.php` | Preserve route/command, but only remove positively confirmed deliveries and never truncate receipts. |
| `app/Console/Commands/R2DownloadAll.php` | Timestamped backups, full object keys in ZIP, preserve old backup alias until replacement is complete. |
| `app/Http/Controllers/R2ManagementController.php` | Show all unresolved receipts, clarify safe cleanup result. |
| `resources/views/admin/r2-manager.blade.php` | Correct cleanup label; show upload ID, attempts, and last error. |
| `resources/views/tasks/_rows.blade.php` | Decode escaped path delimiters consistently with other existing readers. |
| `routes/console.php` | Run recovery every five minutes; stale eligibility uses 15 minutes, beyond the object lock. |
| `config/queue.php` | Reservation defaults 660 seconds, longer than 300-second job and 600-second lock; overrides still require deployment validation. |
| `config/filesystems.php` | Bound R2 HTTP connect/request time and SDK retries. |
| `composer.json` | Local development listener consumes uploads plus default with matching timeout/retries. |
| `package.json`, `package-lock.json` | Add fake-indexeddb development dependency and `test:photos`. |
| `phpunit.xml` | Force tests onto in-memory SQLite, never the configured application database. |
| `tests/Feature/PhotoDeliveryTest.php` (new) | Delivery, duplicate, failure, cleanup, corruption, completion, namespace, batch and recovery tests. |
| `tests/js/photo-queue.test.mjs` (new) | IDB transaction/concurrency/restart/receipt/worker tests. |
| `README.md` | Correct recovery command guidance and link this audit. |
| `docs/photo-upload-reliability.md` (new) | This audit, lifecycle, limitations, test coverage and deployment procedure. |

## 5. New lifecycle and deletion boundaries

1. Selecting a photo commits its original Blob under a permanent UUID. New keys are UUID strings accepted by the existing auto-increment-capable store; old numeric keys remain readable. Old pending records acquire a UUID atomically when read, before they can be included in a finish manifest. The database stays **R2UploadDB v2 / pending**, without destructive upgrade. Drafts await a destination/submission and survive refresh.
2. Submit awaits local saves. Optional compression replaces bytes only before any upload attempt. The original upload filename remains stable. Local save failures stop completion; photos remain in the input and successfully committed drafts remain recoverable.
3. A localStorage finish manifest records request UUID, task, destination, photo IDs, damage and note. Drafts are bound to the destination. Restoring the dashboard resumes incomplete binding/transfer/finish. Earlier locally queued photos for the same task are included.
4. Shared queue atomically claims a photo for 240 seconds and persists attempts/error/backoff. Foreground and SW use the same algorithm. HTTP requests timeout after 120 seconds. Errors keep bytes and retry later; lease expiry handles terminated contexts.
5. Laravel holds an owner-aware 600-second shared cache lock. It creates the durable `receiving` receipt before writing R2. SHA-256, exact object size and complete read verification precede `confirmed_at`/`pending`. Existing IDs cannot change task/destination/bytes. Only then does HTTP JSON return `success:true, persisted:true, upload_id, path`.
6. **IDB deletion happens only after that exact matching acknowledgement**, and only if the context still owns the record. Deletion resolves at transaction completion. Queue dispatch is attempted; failed dispatch cannot erase the committed outbox, which the scheduler retries.
7. Task finish checks every declared UUID has `confirmed_at`, rejects other known unconfirmed task receipts, and writes a finish-request receipt atomically with task status. Replayed HTTP responses cannot advance status again. Dropbox need not be finished. The UI says server handoff and Dropbox delivery are separate stages.
8. Move job locks the same R2 key, verifies its bytes, selects the existing Perceel namespace convention, and uploads to a deterministic filename with overwrite and autorename disabled. Legacy keys receive a stable task/namespace/key-derived prefix to avoid overwriting unrelated historical filenames. Expected size/SHA protects against a **truncated second R2 stream**, before Dropbox even receives it.
9. Dropbox must return HTTP 200 **and** matching ID/present metadata, full byte count, content hash and path. Dropbox's [documented block-hash algorithm](https://www.dropbox.com/developers/reference/content-hash) is used. Only then are the task reference and `done`/`completed_at`/destination receipt committed together. Commas and percent characters are escaped for existing CSV path readers.
10. **R2 deletion occurs after the durable Dropbox receipt and task-reference transaction.** It refuses deletion if another unresolved legacy row shares the object. A failed delete keeps the done receipt and retries cleanup without uploading again. If deletion succeeded but its response/cleaned timestamp was lost, absence of the R2 object plus the completed receipt permits marking cleanup complete.
11. **Database receipts are not automatically deleted.** Task deletion no longer cascades through recovery metadata; the saved destination suffices to finish delivery without the task. Temporary spool files are closed after an attempt; R2 remains their durable upstream source. Legacy local jobs delete their source only after validated Dropbox metadata and committed task reference.

## 6. Failure and recovery behavior

| Failure | Behavior now |
|---|---|
| Mobile network absent/interrupted | Local Blob stays; attempts/error/backoff persist. Offline requests don't count as success. Online/visible/page interval/worker sync retry. |
| Response lost after R2/database commit | Replay keeps same UUID/bytes and gets the existing receipt, including after Dropbox cleanup. No new R2 object or Dropbox filename. |
| Browser/tab closes or restarts | Committed photos and finish manifests survive. Reopen the dashboard in the same origin/profile and sign in. Drafts restore on reopening their task; all local entries can be downloaded from the recovery panel even when their old task isn't in today's list. |
| Service Worker stopped/throttled | Lease expires; foreground queue resumes. waitUntil is used correctly but does not imply guaranteed browser execution. [MDN lifecycle reference](https://developer.mozilla.org/en-US/docs/Web/API/ExtendableEvent/waitUntil). |
| R2 temporarily fails | No persistence acknowledgement. Browser retains bytes; receipt remains receiving/failed. Interrupted post-PUT confirmation can be recovered by worker verification. |
| Queue dispatch/worker unavailable | Confirmed R2 plus durable row remain. Outbox retry selects rows idle for 15 minutes; five-minute scheduler dispatches them on uploads. |
| Worker terminates mid-photo | Reservation and cache lock eventually expire. New job uses same destination. If done receipt exists, only cleanup runs. |
| Dropbox temporary error | R2 and row remain; exception reaches Laravel. Five attempts/backoff; exhausted failures remain available to scheduled retry, without a fixed permanent-loss cutoff. |
| Dropbox 429 | Worker releases for at least Retry-After (and at least 30s), retaining source. Other queued photos can run. |
| Dropbox 401 | Refresh token once, rewind complete stream, retry; persistent authorization failure keeps source and enters normal recovery. |
| One photo fails among 30 | Other independently queued photos continue. Only failed source remains pending/failed; no batch-wide deletion. |
| Dropbox success but DB commit fails | R2 retained; retry uses same deterministic destination. |
| Dropbox success but cleanup fails | Completed row persists, cleanup retries without another Dropbox upload. |

`r2:retry-all` shares job locking with automatic/legacy processing. `r2:clear` now performs **confirmed-only cleanup**, never blanket deletion, including with `--force`. No destructive command was executed. `r2:download-all` remains a best-effort export of a moving bucket, not a transactional snapshot; pause workers for a complete consistent backup. `r2:publish-zip` still copies to public and may expose photos to anyone with the URL; it is not run automatically.

## 7. Tests and production deployment

Automated coverage maps to the requested scenarios:

| Scenario | Verification |
|---|---|
| 1 Normal success | PHP receipt/content/cleanup test; JS acknowledgement test |
| 2 Internet fails before R2 | JS offline/network failure, PHP R2 write failure |
| 3 Response lost after receive | PHP replay before/after cleanup; JS refresh with unchanged UUID |
| 4 Same photo twice | PHP unique receipt/content-conflict; JS UUIDs and competing contexts |
| 5 Refresh with pending | JS shared IDB new context, readable drafts/ArrayBuffers |
| 6 Finish during uploads | PHP missing-ID/other-unconfirmed gate, idempotent finish, no Dropbox wait |
| 7 R2 success, Dropbox failure | PHP source/receipt retained and subsequent success |
| 8 Dropbox 429 | PHP Retry-After release, source retained |
| 9 Worker restart | PHP stale processing/outbox recovery; JS expired terminated-worker lease; real SIGKILL/device OS behavior still needs staging test |
| 10 Duplicate job | PHP one Dropbox call and one task reference |
| 11 Cleanup failure | PHP injected failure after durable completed receipt |
| 12 Cleanup retry | PHP cleanup-only replay, no second Dropbox call |
| 13 20–30 photos | PHP 30 receipts/references and JS 30 queued Blobs; real MySQL/process concurrency still needs staging test |
| 14 One photo fails | PHP 29 successes/one retained source; JS one remaining failed record |
| Additional | Invalid HTTP 200 metadata, equal-size R2 corruption, truncated second R2 read, transaction abort, 401 rewind, missing R2 registration, queue dispatch failure, task deletion, comma/percent/non-ASCII paths, Perceel 1 routing |

Commands used locally:

```sh
php vendor/bin/phpunit tests/Feature/PhotoDeliveryTest.php
npm run test:photos
npm run build
```

Results: **23 photo PHP tests / 133 assertions passed; 12 JS tests passed; Vite build and PHP/inline-JS syntax checks passed.** The full pre-existing suite retains one unrelated failure: `tests/Feature/ExampleTest.php` expects `/` to return 200, but the existing root route redirects to login (302). This failure was reproduced before implementation. PHP 8.5 also reports two existing deprecated PDO constants in `config/database.php`; application requires PHP ^8.3. No external delivery occurred during tests.

### Deployment procedure — operator actions, not executed here

1. Back up the application database and inventory/export remaining R2 objects. Keep existing browser site data. Deploy backend/migration before enabling the new client scripts. Briefly stop old workers during transition so old job code cannot delete new receipts. Existing job payloads are compatible with the new classes.
2. Run `php artisan migrate --force` in production. This adds nullable fields/unique upload UUIDs and finish receipts; it does not rewrite existing pending data. It intentionally removes the task foreign key to preserve recovery metadata after task deletion. Do not roll it back while v8 code is in use. Re-adding the original cascading FK is deliberately not part of rollback.
3. Configure a **durable asynchronous** queue connection; do not use sync/null for photo processing. Ensure actual production workers explicitly consume `uploads` (and default as appropriate). The repository's `.laravel.cloud.yaml` does not establish that selection; confirm it in Laravel Cloud's worker settings.
4. Use a **shared atomic cache store** across all web/worker instances (database or Redis); process-local array/file stores across separate hosts cannot provide the intended locking. Configure database/Redis/Beanstalk retry_after **660 seconds**, or SQS visibility equivalent. Existing environment overrides must be changed explicitly; editing defaults does not override deployed variables. Worker timeout **300 seconds**, lock **600**, graceful shutdown allowance at least **300**. Example worker: `php artisan queue:work --queue=uploads,default --timeout=300 --tries=5`.
5. Refresh configuration/views according to your deployment process (`php artisan config:cache`, `php artisan view:clear`; route cache does not need changing because route names/URLs are unchanged). **Do not blindly clear the shared cache during active uploads**: that removes locks. Restart workers with `php artisan queue:restart` after deploying/configuring the new code.
6. Ensure the scheduler runs each minute. Standard cron `schedule:run` needs no separate restart; restart an existing long-lived `schedule:work` process to load the changed schedule. Confirm r2:retry-all dispatches onto the consumed queue. Old failed_jobs may be retried operationally; new outbox recovery also covers retained rows, safely under the same locks.
7. Build/deploy assets (`npm ci`, `npm run build`) including **both** `public/sw.js` and `public/js/photo-queue.js`. SW is v8; shared script is versioned `?v=8`; registration bypasses update cache. No CacheStorage purge or IndexedDB version/reset is required. Close/reopen all app tabs when idle to activate the waiting worker; reload alone may leave an older worker alive. Never clear tablet site data to force an update. Old HTML cannot provide the new finish manifest and will receive validation failure; reopening loads the compatible client. Legacy numeric pending photos remain readable, though a mixed old/new worker rollout can replay an old object under a new UUID; inspect duplicates during transition.
8. Validate on staging with the actual tablet/browser, mobile throttling, offline/online, process termination, expired authentication, real R2/Dropbox metadata, and several workers using the production database/cache. Watch pending age, failed_jobs, receiving/failed/done-without-cleaned rows, and the named photo events. Dashboard counts are work status, not proof of final Dropbox delivery.

## 8. Remaining risks and limits

- No browser can promise preservation before the initial IndexedDB transaction commits, after site data is erased/evicted, in ephemeral/private browsing, on device loss, or when the user never returns to the origin. Storage persistence is requested but may be denied. Keep camera originals until safe server receipt; eliminating this class needs device-managed storage or an independent backup/client. Offline reload of the entire application is **not** implemented: IDB survives, but the online dashboard must load again to resume.
- Background Sync is optional and OS-controlled. Eventual retry requires an active supported browser or reopening the dashboard, authentication, working server scheduler/workers, and restored network/services. A browser process can be killed despite waitUntil.
- Local finish manifests are separate from photo IDB storage. If localStorage fails or is cleared, photos remain recoverable but automatic task completion may need resubmission. Corrupt manifests fail visibly; they are not deleted. Drafts for deleted/old tasks can be downloaded from the local recovery panel; uploading them may require an operator to reassign the destination/task.
- We cannot reconstruct photos already discarded by filename deduplication or overwritten by historical integer-key collisions. Previously orphaned R2 objects have no trustworthy task/namespace metadata; preserve and reconcile them manually with logs/users. New ingress avoids this gap by creating the row first. Legacy presigned clients still require explicit registration and should be retired or migrated after confirming their external usage.
- Existing legacy local upload jobs remain dependent on the local disk being shared/persistent across worker hosts; no local-file inventory/outbox migration was performed. They now obey confirmation-before-deletion, but operators must retain their volumes and failed jobs. Their session-upload helpers and broken unused route were not redesigned.
- Deterministic overwrite means a retry replaces the same upload's destination content. UUID collision is extraordinarily unlikely; deliberate external edits to those filenames are outside the delivery protocol. External Dropbox deletion after confirmation, R2 lifecycle expiration, credential misuse, database loss, and storage-account failure require backups/versioning/retention policies beyond application retries. No external lifecycle or backup policy was inspected.
- Receipts are retained indefinitely for idempotency/audit. Plan capacity monitoring and an explicit archival policy rather than silently deleting deduplication history. Missing-source rows remain visible and retryable; repairing a permanently missing source may require restoring from a camera/R2/Dropbox backup.
- The row-lock/cache-lock behavior is covered structurally and by mocked contention, not by live multi-host MySQL stress. Confirm deployed visibility/shutdown/cache settings; do not infer them from repository defaults.
- Legacy comma-separated data already split incorrectly cannot be reliably reconstructed automatically. New writes escape delimiters; existing CSV consumers are retained. Path lengths remain constrained by existing database columns; overlong destinations fail and preserve the local photo rather than truncate it.
- Some existing work-status propagation across tasks at the same address remains. Known server-side unconfirmed receipts are excluded; one device cannot discover unsent drafts on another device. No task status alone proves every camera selection across all devices has reached Dropbox. The durable delivery receipt and unresolved-upload view are the delivery authority.
