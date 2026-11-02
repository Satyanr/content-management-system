# Windows Player v1 — Step 8

Source/build terpisah di `players/windows/`. Tidak mengubah Laravel, API schema v1, Vite, migration, atau seeder. Tidak ada Android Player, Monitoring, commit atau push.

## Menjalankan dan membangun

Target Windows 10/11 x64. Lingkungan aktual: Windows 10.0.26200 x64, Node 24.12.0, npm 11.6.2. Versi dikunci: Electron 45.2.1, electron-builder 26.18.0, AJV 8.20.0 dan ajv-formats 3.0.1. Dependensi tidak ditambahkan ke package.json CMS.

```powershell
cd D:\Program\content-management-system\players\windows
npm.cmd ci
npm.cmd start
npm.cmd test
npm.cmd run test:e2e
npm.cmd run pack
npm.cmd run dist
```

Output lokal yang sudah dibangun:

- `players/windows/dist/win-unpacked/CMS Signage Player.exe`.
- `players/windows/dist/CMS-Signage-Player-1.0.0-x64-Setup.exe`.

Installer NSIS x64 berukuran **121421233 bytes**, SHA256 **B9836BEF0BD78DA1F0C6666A9E0568720FD4285F312FA2B33BA9F9DF5706267C**. Build ulang dapat menghasilkan hash berbeda. Artifact/node_modules/test-results diabaikan Git. Installer/app **unsigned**: tidak tersedia sertifikat signing, tidak ada sertifikat palsu. Icon masih default Electron; lihat `players/windows/build/README.md` untuk persiapan branded release.

Installer assisted per-user menyediakan uninstall dan Start Menu shortcut. Desktop shortcut off secara default dan bisa diatur sebelum build. Data dipertahankan saat upgrade/uninstall (`deleteAppDataOnUninstall:false`). Installer berhasil dibangun tetapi belum diinstal/uninstall pada pengujian ini. Auto-start setelah Windows login diaktifkan melalui operator settings pada aplikasi terpasang, default off; development tidak mengubah registry login.

## Struktur aplikasi

| Lokasi | Fungsi |
| --- | --- |
| src/main/index.js | Window, single instance, display/kiosk/topmost/autostart, protocol, IPC, power blocker/lifecycle |
| src/main/runtime.js | Pairing, heartbeat, sync/retry, operator state, revocation dan integrity recovery |
| src/main/protocol.js | Allowlist UI assets dan stream media lokal dengan Range |
| src/preload/index.cjs | Bridge terbatas tanpa token, arbitrary invoke, shell atau filesystem |
| src/services/api/ | HTTP origin-bound, schema validation dan server clock |
| src/services/security/ | Async safeStorage vault, atomic writes, scrypt PIN dan sanitized rotating logs |
| src/services/cache/ | SHA256 blobs, active/previous pointers, crash recovery dan GC |
| src/services/sync/ | Dua worker download, resume dan revision confirmation |
| src/playback/ | Adapter Device layout dan bounded decoder metadata queue |
| src/renderer/ | Setup, playback canvas, connection/expiry state dan operator panel |
| scripts/prepare-shared.js | Copy source playback Studio dan JSON Schema Step 7 unchanged |
| tests/ | Unit/integration dengan mock API dan Playwright Electron Windows |

Pre-start/test/build menyalin `resources/js/studio-playback.js`, `studio-playback-renderer.js`, `studio-geometry.js` serta `docs/player-api-v1.schema.json` ke app. Generated copies tidak dilacak Git, tetapi masuk packaged application. Build dijalankan dari repository agar sumber bersama tersedia. Jangan mengedit copy; ubah sumber dengan regression tests jika diperlukan pada tahap berikutnya.

## Pairing dan keamanan credential

Di CMS pilih company workspace, buat Device Pending, lalu Generate Activation Code. Masukkan **origin HTTPS** CMS dan kode sekali pakai di Player. URL tidak boleh mengandung userinfo, subpath, query atau fragment. CMS yang dipasang pada subpath belum didukung; gunakan origin/reverse proxy khusus.

Untuk CMS lokal, jalankan `php artisan serve --host=127.0.0.1 --port=8000`, lalu Player development:

```powershell
npm.cmd start -- --allow-localhost-http
```

Isi `http://127.0.0.1:8000`. HTTP dibatasi localhost/127.0.0.1/::1, hanya pada unpackaged app dengan flag eksplisit. Packaged app tetap menolak HTTP walaupun diberi flag. Tidak ada bypass sertifikat TLS.

Main process mengirim activation dan menyimpan envelope origin/publicId/token sebagai ciphertext `credentials.bin`. Menggunakan Electron `isAsyncEncryptionAvailable`, `encryptStringAsync` dan `decryptStringAsync`; decrypt membaca `.result`, dan `.shouldReEncrypt` menangani key rotation. Jika encryption tidak tersedia, operasi gagal tanpa plaintext fallback. Windows memakai DPAPI melalui Electron; aplikasi lain yang berjalan sebagai user Windows sama bukan batas perlindungannya. Lihat [safeStorage resmi](https://www.electronjs.org/docs/latest/api/safe-storage).

Token tidak masuk renderer, localStorage, settings JSON, IPC status atau logs. Renderer hanya menerima identity publik dan layout yang diadaptasi. Authorization hanya menuju origin CMS yang divalidasi; seluruh redirect ditolak. Server URL download harus persis `/api/device/media/{id}`, bukan URL arbitrary. Pairing gagal tidak mengganti identity lama. Jika kode sudah dikonsumsi server tetapi vault gagal ditulis, admin perlu membuat kode re-pair baru.

## Sync dan cache offline

1. Startup mendekripsi credential dan memulihkan package hanya jika UUID/schema/referensi dan seluruh size/SHA256 lolos.
2. Manifest memakai schema header 1 dan If-None-Match bila tersedia. Startup meminta manifest penuh; 304 mengambil clock dari header X-Server-Time-Utc.
3. AJV menggunakan schema Step 7. Validasi semantik juga menolak UUID berbeda, duplicate IDs, referensi media rusak, URL tidak tepat, duration mode invalid, overlap/gap implisit, media unavailable, waktu invalid dan horizon >168 jam.
4. Staging tidak mengganti pointer aktif. Media dinamai hash, bukan filename/path server. Media IDs yang memiliki hash sama memakai satu blob/download.
5. Maksimum dua download aktif. .part dilanjutkan memakai Range dan strong If-Range. ETag, revision, Content-Range, size dan SHA256 diperiksa. Respons 200 saat resume menyebabkan partial ditruncate. Interrupted/cancelled/corrupt file tidak dipromosikan.
6. Semua file selesai, manifest diminta kembali. Revision berubah/409 membuat staging belum siap dan diulang setelah backoff; bytes yang masih cocok dapat dipakai ulang.
7. Snapshot di-fsync dahulu, previous pointer disimpan, lalu active pointer diganti atomic rename. Crash sebelum commit mempertahankan paket lama; pointer rusak dapat fallback ke previous yang masih lengkap. Ini diuji dengan simulasi state crash, bukan power-loss fisik.

Normal userData mengikuti Electron, umumnya `%APPDATA%/cms-signage-player`:

```text
credentials.bin          encrypted identity + token
settings.json            controls + salted PIN hash, no token
blocked.json             remembered revocation
clock.json               server UTC / wall checkpoint
sync-metadata.json       last successful sync
content/active.json      committed revision pointer
content/previous.json    previous complete revision
content/packages/        revision-named manifest snapshots
content/blobs/           SHA256 blobs and SHA256.part
logs/player.log[.1,.2]   rotating sanitized logs
```

Blob cache budget default 10 GiB; disk checks mensyaratkan ruang untuk bytes yang hilang plus 256 MiB headroom. GC mempertahankan active, previous dan staged hashes; hanya file cache milik Player yang tak dirujuk dibersihkan. Bila retensi paket lama dan baru melebihi budget, sync ditolak, bukan menghapus paket aktif. Budget saat ini di constructor ContentStore, belum ada UI policy disk. Manifest dibatasi 16 MiB, 500 layouts, 10000 media/segments, ditambah batas layout/item. Log memakai tiga file sekitar 1 MiB per file.

Integrity diperiksa saat startup, sebelum promote dan setiap 15 menit. Korupsi menonaktifkan package, menghapus ETag lokal dan menjadwalkan perbaikan. Manifest/file baru yang tidak lengkap tidak aktif; paket lama tetap boleh digunakan selama lengkap dan validity-nya masih berlaku. Tidak ada media CMS existing yang dipindahkan/dihapus.

## Clock, Scheduler dan playback

Server UTC diproyeksikan dengan monotonic performance.now dan estimasi setengah RTT. Jam Windows bukan satu-satunya sumber kebenaran saat proses hidup. Setelah reboot, monotonic continuity hilang: clock dipulihkan dari checkpoint UTC dan selisih wall time. Rollback >5 detik dari checkpoint ditolak sampai online; loncatan wall time ke depan dapat menyebabkan expiry lebih awal. Offline cold restart tetap membutuhkan clock OS yang cukup wajar, bukan trusted hardware time.

Main membaca interval setengah terbuka `[startUtc,endUtc)` dari schedulePlan setiap detik. Tidak menghitung recurrence/timezone/priority sendiri. Pergantian segment tidak menunggu polling; ketelitian timer sekitar satu detik bergantung OS. Gap menjadi idle, melewati validUntilUtc menjadi expired. Online event dan resume sleep menjadwalkan sync ulang dengan debounce; outage memakai backoff+jitter, bukan loop tanpa batas. Tidak ada network request per frame.

Adapter mengubah mediaId menjadi `signage-media://cache/<sha256>`. Engine Studio tetap memakai satu clock: Page order/duration, simultaneous Windows, urutan item/loop Window, Auto/manual video, image duration, fit modes, z-index dan visibility. Canvas memakai uniform scaling dan letterbox hitam tanpa mengubah pixel geometry. Audio mute/volume diatur operator.

Revision yang hanya berubah karena horizon/metadata paket tidak merestart playback jika current Schedule ID dan layout/source sama. Pergantian konten/Schedule membersihkan RAF, listeners dan video sources. Decoder error memberi fallback Window tanpa restart aplikasi. Probe metadata untuk Auto duration yang belum diketahui dibatasi dua decoder; manual/known duration tidak memerlukan probe tambahan. Codec aktual bergantung Chromium/Electron, driver dan hardware—ekstensi MP4 bukan jaminan H.265/VP9/AV1 didukung.

## Operator dan lifecycle Windows

**Ctrl+Alt+O** membuka/menutup panel. Panel menampilkan Device identity, CMS, online/offline, current Schedule/Playlist, revision, last sync, cache size, version dan sanitized error. Ada display selection, kiosk/topmost, auto-start, mute/volume, manual sync, restart dan re-pair. Monitor hilang memakai primary; monitor/resolution changes menyesuaikan bounds. V1 hanya satu output.

PIN opsional 6–12 digit memakai salted scrypt. Unlock berlaku lima menit; lima gagal diblokir satu menit. Saat PIN aktif, settings/manual sync/restart/re-pair memerlukan unlock. Read-only status tetap terlihat. Kiosk/PIN bukan batas keamanan OS; user yang menguasai profile lokal dapat mengubah file data.

Single-instance lock melindungi cache dari dua proses. Power blocker mencegah display sleep ketika scheduled playback aktif, dilepas ketika idle/expired/revoked/suspend/exit. Renderer crash dicoba reload maksimal tiga kali dalam lima menit; tidak ada restart loop tanpa batas. Shutdown membersihkan timers/sync/decoder. Heartbeat dan sync terpisah, sync tidak overlap. Retry exponential+jitter maksimum sekitar 15–18 menit; Retry-After dapat memperpanjang sampai satu hari.

401 menghapus token dari client in-memory, membatalkan sync/request berikutnya, menghentikan playback dan menyimpan blocked marker. Restart masih memerlukan aktivasi ulang. Ketika benar-benar offline, revocation baru **tidak bisa diketahui**; package lengkap boleh berjalan hanya sampai validity expiry. Setelah 401 diketahui, bytes cache fisik boleh tetap ada, tetapi protocol dan playback diblokir. Re-pair mengganti encrypted identity dan membersihkan blocked marker. 406/validation/429/5xx menampilkan kode aman dan tidak mengaktifkan paket rusak; retry tetap dibatasi backoff.

## Security dan deployment

Window: contextIsolation true, sandbox true, nodeIntegration false, webSecurity true. CSP melarang remote script/network/frame/object/form; inline style hanya diperlukan geometry. Popup/webview/navigasi dan permissions ditolak. Session hanya mengizinkan custom schemes lokal. UI protocol memiliki allowlist file UI/playback, tanpa akses main/preload/services/credential. Media protocol menerima satu hash tepat dari package aktif, memeriksa file/size/realpath dan menyediakan Range. Traversal/query/arbitrary file:// ditolak. IPC memeriksa webContents, top frame, URL UI persis, ukuran input dan allowlist keys/types. Tidak ada arbitrary invoke/fetch/shell/eval/paths dalam bridge. Lihat [security Electron](https://www.electronjs.org/docs/latest/tutorial/security), [protocol Electron](https://www.electronjs.org/docs/latest/api/protocol) dan [NSIS configuration](https://www.electron.build/nsis/).

Logger hanya menerima kode pendek, tanpa raw message/stack/header/body/URL/token. Data development E2E dipisahkan lewat `--test-data-dir`, hanya unpackaged; `--smoke-test` juga hanya unpackaged dan menyembunyikan window. Production tidak memiliki HTTP development bypass. Jangan membuat endpoint Player melewati HTTPS/private media rules Step 7.

Audit npm aktual: **0 advisory runtime** (`npm audit --omit=dev`). Full tree masih memiliki **15 moderate advisories transitive pada toolchain electron-builder**, tanpa high/critical pada hasil audit. AJV runtime sudah diperbarui ke 8.20.0. Tidak dilakukan force downgrade electron-builder atau override major minimatch yang merusak compatibility. Ulang audit/update toolchain sebelum release; packaging hanya source lokal tepercaya. Code signing/auto-update belum dikonfigurasi.

## Hasil pengujian aktual

| Pemeriksaan | Hasil |
| --- | --- |
| Player unit/integration | **24/24 lulus** |
| Electron E2E Windows tersembunyi | **2/2 lulus**: sandbox/display bounds; real DPAPI pairing, cached PNG playback, process restart persistence dan revocation |
| Existing JavaScript CMS | **38/38 lulus** |
| Full PHP regression | **156/162 lulus, 965 assertions**; enam kegagalan Auth/Profile lama |
| Vite CMS build | Lulus |
| Windows NSIS x64 installer | Berhasil dibangun, unsigned |
| JS syntax / git diff --check | Lulus |

Unit/integration mencakup pairing valid/expired, unavailable secure storage, schema/UUID/referensi, cross-origin redirect leakage, clock restore, checksum download/Range resume/200 reset/interruption/cancel, changed revision, atomic recovery/corrupt cache, offline gaps/expiry, shared playback clock/concurrent Windows, metadata decoder bounds, operator PIN, GC budget, outage recovery, concurrency dua download, duplicate-content IDs dan log redaction. Existing 38 JS tests memeriksa video seeking/cleanup, Auto/manual duration, Page transitions dan geometry. E2E menggunakan Electron/safeStorage Windows sebenarnya dengan **mock CMS**, bukan database/credential CMS nyata.

Run awal menemukan bahwa hidden window tidak masuk OS fullscreen sampai ditampilkan; test sekarang jujur memeriksa security dan selected-display bounds. Hasil decrypt async ternyata object `.result`, bukan string; sudah diperbaiki dan restart test lulus setelahnya.

Enam kegagalan PHP yang sama dengan baseline: Authentication login, EmailVerification, PasswordConfirmation dan Registration merujuk route `dashboard` yang tidak terdefinisi; Authentication navigation `/dashboard` 404; Profile `/profile` 404. Tidak ada source PHP yang diubah.

Regression dari root CMS:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest
node --test tests/js/*.test.mjs
npm.cmd run build
git diff --check
```

## Acceptance manual yang belum selesai

1. Install/uninstall dan upgrade pada Windows 10/11 bersih; verifikasi shortcut/data retention. Installer telah dibangun, belum dijalankan.
2. Pair ke **CMS Laravel sebenarnya melalui HTTPS**; uji multi-Page/Window, heartbeat, manifest dan download. E2E saat ini memakai mock API, sementara backend memiliki suite terpisah.
3. Fullscreen/kiosk yang terlihat, monitor kedua/hotplug, DPI, topmost dan physical sleep prevention. Hidden E2E tidak membuktikan visible OS fullscreen/multi-monitor.
4. Codec video/audio nyata, Auto/manual, mute/volume dan long soak 24–72 jam. PNG playback nyata lulus; video hardware decoding/audio belum diuji.
5. Cabut jaringan, ubah jadwal saat staging, cold reboot/power-loss Windows, login dan autostart. Restart **proses aplikasi** lulus, cold restart OS belum diuji.
6. Revoke online/offline, invalid credentials, PIN, disk-full dan clock rollback. Revocation offline baru diketahui setelah komunikasi server.

Clean uninstall: revoke credential di CMS, tutup/uninstall aplikasi melalui Windows Apps, lalu hapus hanya userData Player yang sudah dikonfirmasi bila benar-benar ingin menghilangkan cache/PIN/credential. Jangan menghapus profile lain/folder project. Task ini tidak melakukan uninstall atau penghapusan local data pengguna.

## Persiapan Android

API schema, UTC schedulePlan, checksum staging contract dan layout semantics dapat dipakai Android. Secure storage, lifecycle/autostart/kiosk, local protocol dan codec membutuhkan adapter Android terpisah. Tidak diimplementasikan pada Step 8; capability negotiation, remote monitoring dan independent multi-output juga belum dibuat.
