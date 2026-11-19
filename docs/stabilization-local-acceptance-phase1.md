# Fase 1: stabilisasi dan local acceptance

**Status: PARTIAL / PENDING**, 19 November 2026. Acceptance nyata dan installer baru belum memenuhi exit criteria.

## Baseline dan lingkungan

- Branch `main`, HEAD awal `dab759ba2724a39e81e6377b6f426fbe45ee5d66`, working tree bersih. GitHub connector menunjukkan `main` identik dengan HEAD tersebut; akses Git langsung ke GitHub gagal dari lingkungan ini.
- Windows, PHP CLI 8.5.1, Laravel 13.17.0, Composer 2.9.3, Node 24.12.0, npm 11.6.2, Electron 45.2.1, Playwright 1.64.1, Google Chrome tersedia. MySQL CLI tidak ditemukan pada PATH; `.env` menyatakan `APP_ENV=local` dan koneksi MySQL, tetapi identitas dan status production/shared database belum diverifikasi. Tidak ada secret yang dicatat di dokumen ini.
- `phpunit.xml` menggunakan SQLite `:memory:` dengan `RefreshDatabase`; tidak ada konfigurasi Laravel cached dan tidak ada variabel DB yang menimpa konfigurasi tersebut. Ekstensi SQLite tersedia tetapi perlu diaktifkan per proses dengan `-d extension=pdo_sqlite -d extension=sqlite3`.
- Baseline aktual: PHP 182/188, 1.156 assertion, dua kegagalan 404 untuk route dashboard/profile lama dan empat error route `dashboard` tidak tersedia; JS CMS 51/51; unit Windows Player 40/40. Ini hasil lokal, bukan hasil historis pada dokumentasi lama.

## Perubahan dan akar masalah

- `VerifyEmailController.php`, view Volt login, register, confirm-password, verify-email, `User.php`, `routes/auth.php` dan `resources/views/auth/pending.blade.php`: redirect lama memakai route yang tidak ada; akun baru juga tidak memiliki company/permission. Redirect default sekarang memilih dashboard bila user memiliki permission dan company aktif (atau super-admin), selain itu halaman status berautentikasi dengan tombol logout. Redirect intended tetap melewati middleware pada tujuan; tidak ada permission, role atau akses tenant baru diberikan.
- Test Auth/Profile dan `tests/Pest.php`: fixture dashboard memiliki company aktif dan permission yang diperlukan; navigasi dan profile memakai route CMS saat ini. Test login, logout, verification, password dan profile tetap dipertahankan. Ditambahkan verifikasi bahwa akun tanpa permission tidak dapat membuka dashboard.
- `players/windows/src/playback/device-engine.js` dan `players/windows/tests/ledger.test.js`: hotfix sebelumnya menunggu sampai delapan detik untuk mengidentifikasi frame beku sambil tetap menghitung waktu aktif. Video singkat dapat salah mencapai completed sebelum timeout. Setelah 250 ms tanpa kemajuan posisi media, readiness menjadi false; timeout tetap mendeteksi kegagalan. Test menguji bahwa frame beku tidak dapat menyelesaikan video satu detik. Toleransi 250 ms dan perilaku decoder nyata masih perlu acceptance perangkat.
- Tidak ada perubahan schema API, database pengguna, `.env`, credential, jurnal operator atau hak akses middleware. Tidak ada commit/push.

## Hasil pengujian aktual

| Pemeriksaan | Hasil |
| --- | --- |
| PHP Auth/Profile | 25/25, 83 assertion |
| PHP suite | 189/189, 1.180 assertion, SQLite in-memory |
| JavaScript CMS | 51/51 |
| Windows Player unit | 41/41; `prepare:shared` dijalankan |
| Build Vite | Berhasil |
| PHP lint file PHP diubah; Blade compilation | Berhasil |
| Composer validate | Valid; warning constraint versi OpenSpout eksak, cache Composer tidak writable |
| `git diff --check` | Berhasil; peringatan normalisasi CRLF |
| Electron E2E | 2 gagal saat Electron launch, 1 real acceptance opt-in tidak dijalankan. ACL folder Electron menolak akses token sandbox `ALL APPLICATION PACKAGES`; pesan OS crypt juga muncul. Tidak ada hasil playback yang dapat disimpulkan. |
| Laravel + Electron acceptance | PENDING: persetujuan penggunaan fixture belum diberikan, target database belum diverifikasi terisolasi, dan Electron belum dapat diluncurkan. |
| Frame H.264 sebenarnya, playback time, loop, window paralel, restart/offline | PENDING pada browser/Player nyata. Chrome headless dapat dibuka, tetapi percobaan merekam MP4 H.264 sintetis menghasilkan `NotSupportedError` saat dimuat; belum ada bukti decoding frame. Unit test tidak membuktikan decoding hardware. |
| Assignment multi-device, limit 0/1/3/5/100, PoP, export XLSX | Dicakup sebagian oleh test terisolasi, tetapi rangkaian acceptance nyata dan pemeriksaan isi XLSX masih PENDING. |
| Installer baru | PENDING: build mensyaratkan regression dan acceptance localhost berhasil. Installer lama `CMS-Signage-Player-2.0.0-x64-Setup.exe`, SHA-256 `BF8D365B9AB8F5B07C844D5F69CF5FD7DDBADCC330E1C6077A6B80F493E08279`, berasal dari build sebelumnya dan tidak mengandung perubahan fase ini. Tidak diklaim signed atau tervalidasi upgrade. |

Bukti ringkas tersanitasi: baseline PHP `tests=188 passed=182 failed=2 errors=4 assertions=1156`; sesudah perubahan `tests=189 passed=189 assertions=1180`. Baseline dan hasil JS/Player di atas berasal dari perintah test aktual. Log Playwright lokal ada di `phase1-e2e.log` (diabaikan Git), termasuk rincian kegagalan launch; jangan membagikan trace mentah tanpa audit. Screenshot acceptance baru tidak tersedia. `npm ci` belum dijalankan agar instalasi dependency yang ada tidak dihapus saat akses jaringan belum tersedia.

Reproduksi PHP tanpa mengubah konfigurasi global:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest --compact
node --test tests/js/*.test.mjs
npm.cmd run build
cd players/windows
npm.cmd test
npm.cmd run test:e2e
```

## Audit harness acceptance

`always-on-localhost.spec.js` memakai tag acak 12 digit hex, port localhost sementara, direktori userData sementara dan menghentikan hanya proses server yang dibuatnya. Fixture menulis Company, User, Playlist, Page, Zone, media, Device, assignment dan tabel playback; cleanup menghapus data berdasarkan company code bertag serta file di prefix fixture. Ini tidak cukup untuk membuktikan database yang dituju bukan production/shared. Ada risiko tabrakan tag: flag cleanup ditetapkan sebelum setup selesai dan cleanup memilih company berdasarkan code, sehingga tabrakan/gagal setup berpotensi menyasar company yang telah ada. Jangan jalankan terhadap database existing. `public/hot` dipindah sementara; jika file baru muncul selama tes, backup lama tidak otomatis dipulihkan. Kegagalan/terminasi paksa juga memerlukan pemeriksaan manual fixture dan backup miliknya. Harness hanya memeriksa header ZIP XLSX, belum memeriksa nilai sheet, filter, isolasi company, atau teks formula. Download fixture H.264 dari Chromium juga perlu akses jaringan dan validasi decoding. Semua kekurangan ini harus ditangani sebelum acceptance.

Siapkan database test MySQL baru yang benar-benar terisolasi, konfirmasikan host/database dan `APP_ENV`, dan minta persetujuan eksplisit untuk fixture sebelum membuat atau menjalankannya. Jangan menjalankan `migrate:fresh`, `db:wipe`, cleanup massal atau seeder keseluruhan pada database existing. Gunakan perintah migrasi biasa dan seeder permission yang diperlukan hanya pada database test yang telah disetujui. Tinjau dan perbaiki batas kepemilikan cleanup serta pemulihan `public/hot` sebelum menjalankan harness. Perbaiki akses sandbox Electron dengan cara yang disetujui pada lingkungan test, tanpa mematikan sandbox produksi. Setelah semua prasyarat dan persetujuan terpenuhi, jalankan `CMS_REAL_ACCEPTANCE=1` sesuai instruksi proyek, lalu verifikasi isi XLSX dan skenario yang belum dicakup secara terpisah.

## Panduan manual localhost

Gunakan hanya data dan perangkat test yang Anda izinkan; pilih port kosong. HTTP localhost hanya untuk player unpackaged development.

```powershell
# Terminal A, root
php artisan serve --host=127.0.0.1 --port=8000
# Terminal B, root
npm.cmd run dev
# Terminal C, folder players/windows
npm.cmd start -- --allow-localhost-http
```

1. Login, pilih workspace company, unggah salinan video H.264/AAC dan gambar.
2. Buat Playlist dengan minimal dua Window paralel, tentukan Auto/Manual duration dan batas 0, 1, 3 dan 5 pada item test. Periksa posisi, rasio dan zoom di Studio.
3. Buat Device test, assign Playlist, generate kode aktivasi, lalu pairing Player ke `http://127.0.0.1:8000`. Pastikan Playlist tampil berulang tanpa Schedule dan kedua Window bergerak/tampil bersamaan.
4. Ganti Playlist secara manual; periksa perbedaan perintah dan laporan applied. Uji Schedule persistent dan temporary memakai data test.
5. Hentikan hanya server Laravel yang Anda jalankan, amati playback dari cache dan restart Player saat server tetap offline. Catat counter sebelum dan sesudah restart.
6. Nyalakan server kembali, tunggu sinkronisasi dan periksa Proof of Play: completed, interrupted, failed dan tidak ada event duplikat. Export Excel; buka sheet Summary dan Detail, bandingkan jumlah/durasi/filter dan pastikan teks mirip formula tidak menjadi formula.
7. Periksa console, log Laravel/Player, status sync, disk cache dan pertumbuhan antrean. Jangan mencatat token/kode aktivasi dalam laporan.

## Long-run dan batasan

Rencanakan pemantauan beberapa jam, lalu 24–72 jam pada perangkat fisik: catat waktu, CPU, RAM, frame/time video, heartbeat, disk/cache, pertumbuhan queue, reconnect dan restart renderer; uji restart OS/autostart dan upload tanpa duplikasi. Durasi ini belum dijalankan. Pengujian hardware signage, power-loss fisik, reboot Windows, codec/perangkat lain, instalasi dan upgrade bersih, serta sertifikat resmi masih pending. Exit criteria fase ini belum terpenuhi.
