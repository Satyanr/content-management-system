# Player API v1 — Step 7

Backend API untuk Windows/Android Player. Tidak membuat aplikasi Player, assignment Group, cron playback, atau perubahan Scheduler/Studio UI. Tidak ada migration database baru, dependency baru, pemindahan media, commit atau push.

## Endpoint dan autentikasi

| Method / URL | Fungsi | Batas per Device per menit |
| --- | --- | --- |
| POST `/api/device/activate` | Pairing Step 6, credential hanya dikembalikan sekali | 5 per IP dan hash kode |
| POST `/api/device/heartbeat` | Health report Step 6 | 10, ingress 120 per IP |
| GET `/api/device/manifest` | Paket layout dan jadwal offline | 30 |
| GET / HEAD `/api/device/media/{media}` | Download file yang termasuk paket | 600 |

Player endpoints memakai `Authorization: Bearer <device-credential>`, bukan session admin. Ingress manifest/media dibatasi 1200/menit per IP, terpisah dari heartbeat. `AuthenticateDevice` hanya memverifikasi credential/state; `DeviceEndpointLimit` mengatur limiter per endpoint setelah identitas terverifikasi. Manifest dan Range tidak menghabiskan bucket heartbeat.

Gunakan HTTPS. Jangan mengirim token, kode pairing, company/device ID melalui URL. Query string ditolak seluruhnya. GET/HEAD tidak memerlukan Content-Type atau body; body kosong, `{}` dan `[]` diterima, body berisi data ditolak. POST tetap memerlukan JSON, maksimum 8192 byte.

`X-Player-Schema-Version: 1` opsional (default 1). Versi lain menghasilkan 406 `unsupported_schema_version`, setelah autentikasi. `schemaVersion: 1` adalah kontrak eksplisit. Penambahan field kompatibel boleh dilakukan pada v1; client harus mengabaikan field tak dikenal. Mengubah arti, tipe, unit, aturan playback atau menghapus field memerlukan versi baru. Tidak ada fallback versi diam-diam.

Company dan UUID Device diambil dari credential. Tidak ada parameter impersonation Device. Jadwal masih company-wide, sehingga semua Device aktif company yang sama mempunyai cakupan jadwal yang sama, dengan identitas/resolusi output masing-masing. Group belum memengaruhi resolver.

## Manifest

Schema: `docs/player-api-v1.schema.json` (JSON Schema 2020-12). Semua waktu jadwal menggunakan UTC ISO 8601; duration dalam detik, geometry dalam piksel output asli.

Contoh response normal tanpa Schedule (UUID/revision ilustrasi, bukan rahasia):

```json
{
  "schemaVersion": 1,
  "revision": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "serverTimeUtc": "2026-11-02T03:15:00+00:00",
  "state": "no_schedule",
  "device": {
    "publicId": "00000000-0000-4000-8000-000000000001",
    "name": "Lobby", "platform": "windows", "playerVersion": "0.1.0",
    "capabilities": { "reported": false, "mediaTypes": ["image", "video"] }
  },
  "output": { "width": 1920, "height": 1080, "orientation": "landscape" },
  "currentSchedule": null,
  "currentPlaylistId": null,
  "nextTransitionUtc": null,
  "planStartUtc": "2026-11-02T03:00:00+00:00",
  "validUntilUtc": "2026-11-05T03:00:00+00:00",
  "schedulePlan": [{
    "startUtc": "2026-11-02T03:00:00+00:00",
    "endUtc": "2026-11-05T03:00:00+00:00",
    "state": "no_schedule", "schedule": null
  }],
  "playlists": [], "media": [], "pollIntervalSeconds": 60
}
```

Saat scheduled, `currentSchedule` berisi ID/name/priority/timezone/type, playlistId dan aturan once/weekly; `currentPlaylistId` menunjuk `playlists[].id`. `schedulePlan` berisi interval winner/gap yang berurutan, tanpa overlap, setengah terbuka `[startUtc,endUtc)`. No Schedule adalah state sukses 200, dengan schedule/playlist null; media dan layout mendatang tetap dapat tersedia. Tidak ada fallback Playlist.

`ScheduleResolver::timeline()` adalah satu-satunya pembentuk plan; pemilihan winner tetap priority terbesar, lalu Schedule ID terkecil. Recurrence, timezone, overnight, DST, active company/schedule/playlist dan scope company mengikuti Step 5. Tidak ada resolver alternatif. `nextTransitionUtc` adalah perubahan winner/gap berikutnya di dalam horizon; null berarti tidak ada perubahan yang diketahui dalam horizon, bukan janji jadwal tanpa batas.

`PLAYER_HORIZON_HOURS` default 72, dibatasi 1–168. Plan diikat ke awal jam UTC saat request agar polling dalam jam yang sama stabil. Contoh request 03:15 menghasilkan plan mulai 03:00 selama 72 jam; sisa masa berlaku saat diterima adalah 71 jam 45 menit. Pakai `validUntilUtc` sebagai batas absolut, bukan menambahkan 72 jam dari clock client. Anchor bergeser setiap jam dan revision ikut berubah. Tidak ada horizon client-controlled. Polling rekomendasi 60 detik; refresh juga menjelang transisi/validity expiry.

`device.capabilities.reported=false` berarti belum ada capability negotiation Player. `mediaTypes` menyatakan tipe protokol saat ini, bukan jaminan codec/hardware perangkat. Device platform/version/resolusi berasal dari provision/heartbeat; output width/height bisa null pada data lama. Canvas tiap Playlist tetap mempunyai ukuran sendiri. Player berikutnya harus menangani output yang belum diketahui dan kompatibilitas codec. PDF belum termasuk playback Playlist v1, mengikuti Studio existing.

Layout mempertahankan nama/ID, width/height/background, totalDuration, Pages (sort_order lalu ID), duration/background Page, Zones (z_index lalu ID), x/y/width/height, visibility, keep_aspect_ratio, fit_mode, serta items (sort_order lalu ID). Item berisi mediaId/title/type, duration, durationMode, knownDuration dan available. Image default 10 detik; video null duration berarti Auto, knownDuration bisa null. Manual video duration tetap disimpan. Clock playback, looping item dalam Window, concurrent Windows dan pergantian Page mengikuti Playback Step 4; implementasinya di Player berikutnya. Field editor/internal seperti editor_request_id, storage path, credential dan session URL admin tidak dikirim.

`media` adalah daftar deduplikasi seluruh image/video aktif yang dirujuk layout winner dalam horizon, termasuk jadwal mendatang. Entri berisi id/type/mimeType/size, checksum `{algorithm:"sha256",value:"..."}`, available dan downloadUrl relatif `/api/device/media/{id}`. Ukuran/hash dihitung dari bytes file, bukan hanya kolom size atau updated_at. File hilang ditandai unavailable dengan size/checksum null; layout tetap mempertahankan placeholder. Jangan mengaktifkan paket yang membutuhkan file unavailable.

## Revision, ETag dan perubahan saat sinkronisasi

Revision SHA-256 memakai JSON kanonis (key object diurutkan, urutan array semantik dipertahankan). Input mencakup identitas/output relevan, current winner, plan/anchor/validity, aturan Schedule eligible termasuk kandidat yang tertutup prioritas lain, layouts, item durations/order, serta size/checksum bytes media. Tidak memakai updated_at sebagai satu-satunya sumber. Edit geometry dengan timestamp tetap dan overwrite file dengan panjang sama tetap mengubah revision.

`serverTimeUtc` dikecualikan dari hash. Manifest memakai **weak ETag** `W/"<revision>"` karena clock response dapat berubah sementara konten semantik sama. Kirim `If-None-Match` untuk mendapatkan 304 tanpa body. `X-Server-Time-Utc` dan `X-Content-Revision` tetap tersedia pada 304. Transisi waktu ke winner/gap baru mengubah revision, walau DB tidak berubah. Auth/state/scope selalu diperiksa sebelum 304. Tidak ada shared/public cache: response memiliki `Cache-Control: private, no-store` dan Vary Authorization/schema. Player menyimpan cache aplikasi sendiri; no-store mencegah penyimpanan oleh cache HTTP umum.

Manifest dibangun dalam transaction pembacaan, menggunakan isolation koneksi DB existing (default MySQL repeatable-read). Tidak mengubah isolation global. Referensi Playlist yang hilang saat pembacaan ditolak 409. Tidak ada snapshot grant yang mempertahankan akses ke jadwal/media yang sudah dikeluarkan dari cakupan.

Setiap download/Range mengevaluasi ulang plan menggunakan credential saat itu. Kirim header **`X-Content-Revision: <manifest.revision>`** pada seluruh download paket. Jika revision berubah, server membalas 409 `sync_revision_changed`; jika media sudah tidak termasuk plan, 404. Download tanpa header tetap memerlukan scope terbaru, tetapi tidak mengikat versi paket. Request yang sudah diotorisasi dan mulai streaming boleh selesai; revocation/perubahan selanjutnya berlaku pada request berikutnya. Server tidak dapat mencabut bytes yang sudah disimpan offline oleh Player.

## Download dan HTTP Range

GET/HEAD memeriksa same-company, active asset, referensi dalam plan winner, file tersedia, dan lokasi di bawah root local/public yang diizinkan. Legacy disk public dapat dibaca **melalui endpoint terautentikasi ini**, tanpa membuka URL public storage. Disk nonlokal belum didukung. Request tidak menerima path file. Filename dibersihkan; MIME dibatasi image/video atau octet-stream, Content-Disposition attachment, nosniff.

- GET normal: 200, Content-Length, Accept-Ranges bytes, strong ETag dari SHA256, X-Content-SHA256 dan X-Content-Revision.
- Single range `bytes=0-1023`, `bytes=1024-` dan `bytes=-1024`: 206, Content-Range dan panjang bagian yang dikirim. End melewati EOF dipotong.
- Range malformed, multi-range, start di luar EOF, suffix nol, atau end sebelum start: 416 dengan `Content-Range: bytes */<size>`.
- HEAD: 200 beserta panjang/hash/header file, tanpa body. Range diabaikan pada HEAD sesuai semantik Range GET.
- `If-None-Match` matching: 304 setelah scope diotorisasi ulang. Matching didahulukan dari Range.
- `If-Range` harus cocok dengan strong file ETag untuk melanjutkan Range. Mismatch/weak/date validator menghasilkan full 200. Date validators belum digunakan.

Stream dikirim dalam chunk maksimum 1 MiB dan ditutup pada akhir response. Hashing menggunakan stream, tanpa menampung seluruh video dalam memory. Satu handle file yang sama dipakai untuk hash dan streaming; perubahan bytes antara pemeriksaan manifest dan open download menghasilkan 409. Penggantian file sebaiknya memakai upload path baru/atomic rename; mutasi in-place selama streaming tidak dijamin konsisten dan checksum Player wajib mendeteksinya.

Tradeoff fondasi: checksum exact dibaca ulang setiap build manifest (termasuk validasi revision sebelum download), sehingga biaya I/O proporsional total bytes paket, meski jumlah query tetap konstan. Ini menghindari checksum stale untuk legacy file yang dapat berubah tanpa updated_at. Uji query bukan benchmark video besar. Sebelum deployment dengan paket besar/perangkat banyak, ukur I/O/latensi dan perkenalkan immutable file versions dengan checksum terverifikasi saat ingest/cache yang diinvalisasi secara eksplisit; jangan meng-cache berdasarkan updated_at/mtime saja.

## Strategi cache offline Player

1. Aktivasi sekali, simpan credential di Windows protected storage/Android Keystore. Jangan menaruhnya di log, URL atau file cache konten.
2. Ambil manifest (atau 304 dengan ETag). Validasi schema, scope UUID, waktu server, horizon dan integritas referensi layout/media.
3. Buat staging directory berdasarkan revision. Reuse file cache hanya jika SHA256 dan size identik; unduh sisanya dengan Bearer, X-Content-Revision, dan optional Range + strong If-Range.
4. Verifikasi seluruh size/SHA256 setelah download, termasuk hasil resume. Jangan mengaktifkan paket ketika masih ada file pending/missing atau checksum gagal.
5. Jika 409/404 selama sinkronisasi, buang rencana staging sebagai paket siap, ambil manifest baru dan gunakan ulang hanya bytes yang checksum-nya tetap cocok. Retry 429 mengikuti Retry-After.
6. Lakukan pengecekan manifest terakhir sebelum activation; bila revision berubah, ulangi rencana. Setelah seluruh file lolos, simpan manifest dan ganti pointer paket aktif secara atomik. Simpan paket aktif lama sampai paket baru siap. Tidak ada server-side publish atau sync-ready ACK pada tahap ini.
7. Jalankan schedulePlan berdasarkan UTC yang diselaraskan dengan server dan monotonic clock. Gunakan interval gaps secara eksplisit. Saat offline melewati validUntilUtc, berhenti menebak schedule dan tampilkan state offline/no schedule yang ditentukan Player. Jangan menganggap jadwal terakhir berlaku selamanya.

## Error dan tracing

```json
{"message":"Player request could not be completed.","error":{"code":"media_not_found"},"requestId":"generated-request-uuid"}
```

| Status/state | Code | Perilaku |
| --- | --- | --- |
| 200 | state `no_schedule` | Kondisi normal, bukan error |
| 401 | `invalid_token` | Token invalid/revoked, Device hilang/tidak aktif; tidak ada info secret |
| 401 | `device_disabled` | Secret valid tetapi status disabled; normal UI disable juga revoke sehingga bisa menghasilkan invalid_token |
| 401 | `company_inactive` | Secret valid untuk company nonaktif |
| 404 | `media_not_found` | Foreign/forged/inactive/out-of-plan ID, respons sama |
| 404 | `media_unavailable` | Asset authorized tetapi file tidak dapat dibuka |
| 406 | `unsupported_schema_version` | Versi header selain 1 |
| 409 | `sync_revision_changed` | Paket/file berubah; refresh manifest |
| 416 | `invalid_range` | Range tidak didukung/tidak valid |
| 422 | `validation_failed` / `invalid_request` | Input/identity/query/body ditolak |
| 429 | `rate_limited` | Retry-After tersedia |
| 500 | `request_failed` | Pesan generik, tanpa stack trace/SQL/path bahkan saat app.debug=true |

Semua response Device menyertakan `X-Request-ID` UUID buatan server, termasuk 304. Error JSON juga menyertakan requestId. ID dimasukkan ke konteks logging sementara selama request, kemudian dibersihkan; token/kode tidak dicatat. Aktifkan redaksi Authorization/body pada reverse proxy/APM eksternal. Trace internal server hanya untuk operator, tidak dikembalikan ke Player.

## Pengujian PowerShell

Contoh menggunakan credential uji yang diperoleh dari workflow Step 6; jangan menyalin credential asli ke source atau perintah dengan argumen token literal:

```powershell
$cmsBase = 'https://cms.example.test'
$secretInput = Read-Host 'Device credential' -AsSecureString
$deviceToken = [System.Net.NetworkCredential]::new('', $secretInput).Password
$deviceHeaders = @{ Authorization="Bearer $deviceToken"; 'X-Player-Schema-Version'='1' }
$manifestResponse = Invoke-WebRequest -Uri "$cmsBase/api/device/manifest" -Headers $deviceHeaders
$package = $manifestResponse.Content | ConvertFrom-Json
$deviceHeaders['If-None-Match'] = $manifestResponse.Headers['ETag']
# PowerShell 7: inspect status 304 without treating it as a terminating error.
Invoke-WebRequest -Uri "$cmsBase/api/device/manifest" -Headers $deviceHeaders -SkipHttpErrorCheck | Select-Object StatusCode
$deviceHeaders.Remove('If-None-Match')
$deviceHeaders['X-Content-Revision'] = $package.revision
$mediaEntry = $package.media | Where-Object available | Select-Object -First 1
if ($mediaEntry) {
    Invoke-WebRequest -Method Head -Uri "$cmsBase$($mediaEntry.downloadUrl)" -Headers $deviceHeaders | Select-Object StatusCode, Headers
    $deviceHeaders['Range'] = 'bytes=0-3'
    Invoke-WebRequest -Uri "$cmsBase$($mediaEntry.downloadUrl)" -Headers $deviceHeaders -OutFile './player-part.bin'
    $deviceHeaders.Remove('Range')
    Invoke-WebRequest -Uri "$cmsBase$($mediaEntry.downloadUrl)" -Headers $deviceHeaders -OutFile './player-staging.bin'
    Get-FileHash './player-staging.bin' -Algorithm SHA256
    # Compare hash and byte length to mediaEntry before atomic promotion.
}
Remove-Variable deviceToken, secretInput, deviceHeaders
```

Ulangi dengan token revoked, media company lain, media same-company yang tidak dirujuk, expired plan, dan perubahan schedule setelah manifest. Harapkan 401/404/409; jangan pernah mendapat raw storage URL. Untuk Windows PowerShell 5.1 tangani 304 melalui catch status, karena SkipHttpErrorCheck hanya tersedia pada PowerShell modern.

## Deployment dan file berubah

Baru: `PlayerController`, `DeviceApiContext`, `DeviceEndpointLimit`, `DeviceManifestService`, `DeviceMediaFile`, `PlaylistLayout`, `config/player.php`, `tests/Feature/Devices/PlayerApiTest.php`, dokumentasi/schema ini.

Integrasi: `AuthenticateDevice`, `DeviceRequestGuard`, `DeviceLifecycle` (identity verification terpisah dari state), `PlaylistPlayback` (serializer murni bersama, authorization admin tetap), `AppServiceProvider` (ingress limiter), `bootstrap/app.php` (aliases/priority/sanitized error renderer), `routes/api.php`. ScheduleResolver dan aturan Scheduler tidak diubah.

Tidak ada migration/seeder Step 7. Existing media tidak dipindah/dihapus. Tetap blokir `/storage` di server static sebagaimana Step 6; rule Laravel tidak menggantikan Nginx/IIS/CDN deny. TLS, persistent rate-limit cache, sinkronisasi waktu server, redaksi log proxy, private storage permissions dan PHP streaming timeouts harus sesuai deployment. Jangan menaruh endpoint ini di shared CDN cache. URL relative harus diselesaikan terhadap origin CMS tepercaya; kirim Bearer hanya ke origin itu.

## Validasi

Baseline sebelum perubahan: full PHP 130 test, 124 lulus, enam kegagalan Auth/Profile lama (dua 404, empat missing route dashboard).

Perintah yang digunakan:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Devices tests/Feature/Playlists tests/Feature/Scheduler
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest
node --test tests/js/*.test.mjs
npm.cmd run build
php artisan route:list --path=api/device
php artisan view:cache
php artisan view:clear
git diff --check
```

PHP SQLite lokal perlu diaktifkan lewat opsi -d; test memakai SQLite in-memory, bukan reset MySQL aplikasi. Pengujian API memakai Laravel HTTP integration dan pemeriksaan bytes streamed, bukan aplikasi Windows/Android atau browser manual.

Hasil akhir (2 November 2026):

| Pemeriksaan | Hasil |
| --- | --- |
| Device + Player API + Playlist + Scheduler | **136/136 lulus, 904 assertions** |
| Full PHP | **156/162 lulus, 965 assertions**, enam kegagalan lama yang sama dengan baseline |
| JavaScript Studio/Playback | **38/38 lulus** |
| PHP lint | Lulus, 15 file PHP baru/berubah |
| Vite build, Blade compilation, route:list, git diff --check | Lulus |
| JSON Schema syntax | Lulus parsing JSON |

Player API menambahkan 32 test cases (termasuk data-driven revision). Query test memuat 13 Pages, 49 Windows dan 289 items yang memakai satu asset terdeduplikasi; jumlah query build manifest dibatasi maksimum 12, tidak bertambah per item. Test lain mencakup media jadwal mendatang, overnight, gaps, priority/ties, perubahan file berukuran sama, conditional requests saat transisi waktu, scope berubah saat sync, range/suffix/HEAD/If-Range, missing files, serta respons exception yang disanitasi walau debug aktif. Aktivasi/heartbeat Step 6 dan seluruh regresi Playlist/Scheduler lulus.

Enam kegagalan lama tetap terpisah dan tidak diubah dalam Step 7:

- AuthenticationTest — users can authenticate using the login screen: route `dashboard` tidak terdefinisi.
- AuthenticationTest — navigation menu can be rendered: `/dashboard` 404.
- EmailVerificationTest — email can be verified: route `dashboard` tidak terdefinisi.
- PasswordConfirmationTest — password can be confirmed: route `dashboard` tidak terdefinisi.
- RegistrationTest — new users can register: route `dashboard` tidak terdefinisi.
- ProfileTest — profile page is displayed: `/profile` 404.

Selama pengujian awal ditemukan body `[]` yang dikirim helper getJson dan exception Laravel yang dirender sebelum middleware catch. Guard kini menerima body kosong tersebut, dan error renderer Device dipasang di exception handler agar trace tidak bocor. Seluruh angka di atas berasal dari run ulang setelah perbaikan. Browser, aplikasi Player, load test file video besar, dan konfigurasi static server produksi belum diuji pada tahap ini; tidak ada klaim keberhasilan untuk pemeriksaan tersebut.

Persiapan Windows Player: implementasikan secure credential store, schema validator, scheduler UTC, staging/cache checksum + atomic promotion, Range resume, retry/backoff, gap/expiry handling, serta renderer image/video yang kompatibel dengan layout/duration Step 4. Capability negotiation, remote commands dan assignment Device/Group merupakan scope berikutnya, belum dibuat di Step 7.
