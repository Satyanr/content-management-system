# Device Management — Step 6

Implementasi fondasi Device Management multi-company. Scheduler tetap company-wide; belum ada Player Manifest API, assignment Group, atau endpoint download media untuk Device.

## Database

Migration `2026_11_02_000006_create_device_management_tables.php` menambahkan:

| Tabel | Isi dan constraint |
| --- | --- |
| `devices` | Company, UUID publik unik, identitas, konfigurasi layar, status pending/active/disabled, waktu UTC, creator dan JSON diagnostik heartbeat. Company cascade; creator null-on-delete. |
| `device_groups` | Company, nama unik per company, deskripsi. |
| `device_group_device` | Many-to-many, unique Device/Group; composite foreign keys dengan company_id memaksa keduanya berasal dari company sama. |
| `device_pairing_codes` | Hash SHA-256 kode, expiry, consumed/cancelled UTC, attempts. Device cascade. |
| `device_credentials` | Identifier unik, hash SHA-256 secret, last-used/revoked UTC. Device cascade. |

Tidak ada boolean online permanen. Status dihitung server: Pending, Disabled, Revoked, Never connected, Online (heartbeat <=180 detik), atau Offline. Heartbeat direkomendasikan setiap 60 detik. `DEVICE_HEARTBEAT_FRESHNESS_SECONDS` dapat mengubah threshold di `config/devices.php`. Last Seen ditampilkan dalam timezone company dari Settings, fallback Asia/Jakarta.

## File implementasi

- Models: `Device`, `DeviceGroup`, `DevicePairingCode`, `DeviceCredential`; relasi Devices/Groups ditambahkan ke `Company`.
- Services: `DeviceWorkspace` (workspace dan timezone), `DeviceManager` (validasi CRUD/membership), `DeviceLifecycle` (pairing, credential dan heartbeat).
- Admin controller `Admin/DeviceController`; Livewire `Devices/DeviceTable` dan `Devices/DeviceDetails`; Blade di `resources/views/admin/devices/` dan `resources/views/livewire/devices/`.
- API controller `Api/DeviceController`; middleware `AuthenticateDevice`, `DeviceRequestGuard`; `routes/api.php`; integrasi routing/alias/priority di `bootstrap/app.php`; limiter di `AppServiceProvider`.
- `DevicePermissionSeeder`, integrasi `DatabaseSeeder`, `MenuSeeder`, dan `routes/web.php`.
- `ActivityLogService` menerima company eksplisit dan actor sistem untuk audit API tanpa meminjam identitas session admin.
- Hardening media: `MediaAsset`, `MediaStreamController`, `MediaUploadController`, `MediaTable` beserta view, `config/filesystems.php`, middleware `ProtectLegacyMediaStorage`, `public/.htaccess`, dan development router `server.php`.
- Tests: `tests/Feature/Devices/DeviceTest.php` dan `tests/Concurrency/device-activation.php`.

## Penggunaan admin

1. Pilih workspace company aktif, buka `/admin/devices`. All Companies menampilkan instruksi memilih workspace dan tidak mengizinkan mutasi.
2. Create Device: isi nama/platform, lokasi, resolusi/orientasi opsional, dan beberapa Group. UUID otomatis, status awal Pending.
3. Gunakan Generate Activation Code. Kode hanya dikirim sekali pada response aksi yang berhak, ditampilkan di state lokal browser; refresh/close menghilangkannya. Masa berlaku 10 menit. Generate ulang membatalkan kode sebelumnya.
4. Grid/List menyediakan pencarian, filter platform/status/group, sorting dan pagination. Details menampilkan identitas, heartbeat, riwayat pairing/credential dan audit tanpa kode/token/hash.
5. Group mendukung create/rename/delete dan add/remove membership. Menghapus Group mempertahankan Device. Membership tidak mengubah Playlist/Scheduler.
6. Cancel Code membatalkan kode tertunda. Revoke mencabut seluruh credential dan kode tertunda. Re-pair mencabut credential lama, mengembalikan Pending dan menerbitkan kode baru. Disable memblokir akses dan mencabut credential/kode; Enable mengembalikan Pending dan memerlukan pairing baru.

Permission additive: `devices.view/create/edit/delete/activate/revoke`. Admin/super-admin mendapat keenamnya; operator hanya view. Seeder tidak memanggil syncPermissions dan tidak mereset custom permission. Disable membutuhkan edit+revoke; re-pair activate+revoke. Mutasi sensitif memiliki konfirmasi dan audit tanpa rahasia.

## Aktivasi dan heartbeat

Gunakan HTTPS pada deployment. Kode/token dikirim dalam JSON/header, tidak dalam URL. Body maksimal 8192 byte; JSON malformed ditolak; company/device identity dari body dilarang. Pairing tidak menggunakan login admin.

Contoh PowerShell interaktif (gunakan alamat CMS sendiri):

```powershell
$cmsBase = 'https://cms.example.test'
$pairingCode = Read-Host 'Activation code'
$activationBody = @{ activation_code=$pairingCode; platform='windows'; player_version='0.1.0'; screen_width=1920; screen_height=1080 } | ConvertTo-Json
$activationResult = Invoke-RestMethod -Method Post -Uri "$cmsBase/api/device/activate" -ContentType 'application/json' -Body $activationBody
$deviceToken = $activationResult.credential
# Simpan credential di secure storage Player; jangan cetak response/token ke log.
$heartbeatBody = @{ platform='windows'; player_version='0.1.0'; screen_width=1920; screen_height=1080; uptime_seconds=60; device_timestamp=[DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ssZ') } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$cmsBase/api/device/heartbeat" -ContentType 'application/json' -Headers @{ Authorization="Bearer $deviceToken" } -Body $heartbeatBody
Remove-Variable pairingCode, activationBody, activationResult, deviceToken
```

Aktivasi berhasil: HTTP 201, `device.public_id`, `device.status`, `credential`, `token_type=Bearer`, `heartbeat_interval_seconds=60`. Credential plaintext tersedia hanya pada response itu. Kode invalid/expired/consumed/cancelled mendapat 422 generik; replay tidak menerbitkan token. Token invalid/revoked/disabled/company nonaktif mendapat 401. Limit terlampaui 429; payload besar 413; tipe konten salah 415; JSON rusak 400.

Heartbeat mengembalikan status, UUID perangkat, waktu server UTC dan interval. Tidak mengembalikan konten atau schedule. Timestamp Player hanya diagnostik; last_seen_at menggunakan waktu server. Token tidak mengautentikasi halaman admin atau stream media admin.

Kode berentropi 80 bit, secret credential 256 bit, identifier 128 bit; UUID bukan password. Hanya hash disimpan. Aktivasi menggunakan transaction dan lock Device lalu pairing; revoke/heartbeat mengikuti urutan lock konsisten. Dua aktivasi bersamaan hanya dapat mengonsumsi satu kode sekali. Limiter aktivasi 5/menit per IP dan hash kode; ingress heartbeat 120/menit per IP dan authenticated heartbeat 10/menit per Device. Rahasia tidak dimasukkan ke rate-limit keys atau Activity Log.

## Isolasi dan akses media

Semua pembacaan/mutasi Device admin memakai workspace aktif dan permission server-side. `forCompany(null)` Device/Group mengembalikan kosong. ID form dikunci Livewire; workspace lama ditolak setelah switching. API mengambil company dari Device hasil autentikasi, bukan input Player.

Upload baru menggunakan disk local private (`storage/app/private`), local serving dimatikan. URL MediaAsset sekarang menuju stream admin yang memeriksa permission `media.view`, workspace aktif dan company pemilik; mendukung image/video/PDF dan Range 206/416. All Companies tidak dapat membuka media. Response stream private/no-store.

File/row legacy pada disk public tidak dipindah atau dihapus. Laravel middleware menolak `/storage`, Apache `.htaccess` memblokirnya sebelum static-file shortcut, dan router `php artisan serve` juga memblokir akses static ke storage. **Server yang melayani static file di luar Laravel wajib menerapkan deny yang setara**; middleware saja tidak melindungi Nginx/IIS/CDN/object storage. Contoh Nginx (di server block, lalu validasi config/reload oleh operator):

```nginx
location = /storage { return 403; }
location ^~ /storage/ { return 403; }
```

Pastikan tidak ada alias/case variant/URL CDN/bucket publik lain yang masih membuka file legacy. Atur rule setara pada IIS/reverse proxy, matikan public bucket bila digunakan, dan invalidasi cache publik lama. Apache production dan konfigurasi server eksternal belum diuji di lingkungan ini. Jangan deploy hardening parsial tanpa memastikan rule static server berlaku.

Migrasi fisik media dilakukan terpisah: backup DB dan file, inventaris disk/path/referensi, copy ke private tanpa menghapus sumber, periksa ukuran+SHA256 dan streaming/range/preview setiap tipe; ubah pointer disk secara transactional dalam batch terukur setelah copy lolos; pertahankan backup/sumber sampai verifikasi selesai. Rollback mengembalikan pointer DB dan backup yang terverifikasi, tetap mempertahankan pembatasan akses publik. Jangan mengaktifkan kembali URL publik sebagai rollback. Tidak ada migrasi/penghapusan media fisik yang dijalankan pada Step 6.

## Validasi

Migration tambahan serta `DevicePermissionSeeder` dan `MenuSeeder` telah dijalankan pada MySQL lokal. Tidak ada fresh/wipe/reset database aplikasi. Feature tests memakai SQLite in-memory. Uji race MySQL memakai dua proses PHP sungguhan, membuat fixture terisolasi dan membersihkan hanya fixture tersebut.

```powershell
php artisan migrate --path=database/migrations/2026_11_02_000006_create_device_management_tables.php
php artisan db:seed --class=DevicePermissionSeeder
php artisan db:seed --class=MenuSeeder
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Devices tests/Feature/Playlists tests/Feature/Scheduler
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest
php tests/Concurrency/device-activation.php
node --test tests/js/*.test.mjs
npm.cmd run build
php artisan view:cache
php artisan view:clear
git diff --check
```

PHP CLI lokal belum memuat SQLite secara default; opsi `-d` di atas diperlukan. Percobaan tanpa opsi tersebut gagal karena driver tidak dimuat, kemudian dijalankan ulang dengan driver aktif. PowerShell memblokir npm.ps1, sehingga build memakai npm.cmd.

Hasil akhir pada 2 November 2026:

| Pemeriksaan | Hasil |
| --- | --- |
| Device + Playlist + Scheduler | 104/104 lulus, 707 assertions (28 Device dan 76 Playlist/Scheduler) |
| Full PHP | 130 tests: 124 lulus, 2 assertion failures dan 4 errors lama, 768 assertions |
| MySQL activation race, dua proses | Lulus: 1 activation, 1 credential, 1 consumed code |
| JavaScript | 38/38 lulus |
| Vite / Blade / PHP lint / route registration / diff check | Lulus |

Enam kegagalan full suite sama dengan baseline sebelum Step 6 dan tidak diperbaiki dalam scope ini:

- `AuthenticationTest`: users can authenticate using the login screen — route `dashboard` tidak terdefinisi.
- `AuthenticationTest`: navigation menu can be rendered — `/dashboard` menghasilkan 404.
- `EmailVerificationTest`: email can be verified — route `dashboard` tidak terdefinisi.
- `PasswordConfirmationTest`: password can be confirmed — route `dashboard` tidak terdefinisi.
- `RegistrationTest`: new users can register — route `dashboard` tidak terdefinisi.
- `ProfileTest`: profile page is displayed — `/profile` menghasilkan 404.

Error import waktu pada filter Device dan urutan import bootstrap pada script concurrency ditemukan selama validasi dan sudah diperbaiki; hasil tabel di atas berasal dari pengujian ulang setelah perbaikan. Tidak ada kegagalan baru pada Device/Playlist/Scheduler.

Smoke test HTTP nyata pada development server: akses probe static `/storage/...` mendapat 403 tanpa kebocoran isi; heartbeat tanpa credential mendapat 401. Test concurrency MySQL lulus: satu request berhasil, satu credential, satu consumed pairing. Test JavaScript 38/38 lulus; Vite build, Blade compilation, PHP lint 41 file dan route registration lulus. Tidak tersedia browser automation untuk memverifikasi tampilan visual, browser console atau decoding video nyata.

## Persiapan Step 7

Autentikasi Device, identitas company, konfigurasi resolusi/platform/version, heartbeat dan revocation menjadi fondasi Player API berikutnya. Default yang direncanakan: schedule company-wide berlaku untuk Device aktif milik company tersebut, sampai assignment/override diperkenalkan secara eksplisit. Group belum memengaruhi ScheduleResolver. Step 7 perlu menentukan capabilities, revision konten, manifest/delivery authorization dan strategi sinkronisasi. Endpoint manifest/download belum dibuat pada Step 6.
