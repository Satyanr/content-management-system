# Fase 1: stabilisasi dan local acceptance

**Status: PARTIAL**, 19 November 2026. Preflight, acceptance Laravel–Electron, regresi dan build installer lulus. Freeze decoder nyata dan beberapa kombinasi laporan belum diuji end-to-end; instalasi/upgrade pada Windows bersih juga belum diuji.

## Opsi bypass inspeksi grant

Atas instruksi pengguna, `ACCEPTANCE_SKIP_GRANT_INSPECTION=1` hanya melewati pembacaan dan parsing `SHOW GRANTS`; pemeriksaan lain tetap wajib. Guard memerlukan environment lokal/testing, allowlist eksplisit `CMS_ACCEPTANCE_DISPOSABLE_DATABASES`, schema berbeda dari `.env` utama, MySQL loopback, user non-root, serta hasil `DATABASE()`, `CURRENT_USER()` dan port server yang sesuai konfigurasi acceptance. Konfigurasi utama production/staging, database URL/socket yang tidak aman, mismatch identitas, dan schema di luar allowlist ditolak. Ketika bypass berhasil, Laravel menulis peringatan `Acceptance grant inspection bypassed` beserta nama database dan user tanpa password. Mode normal tetap memeriksa grant. Opsi skrip PowerShell `-SkipGrantInspection` mengaktifkan bypass secara eksplisit; tanpa opsi itu inspeksi grant tetap aktif.

Tes guard positif dan negatif lulus 16/16 (37 assertion). Preflight nyata **lulus** untuk `cms_acceptance_test` / `cms_acceptance_user`; log `testing.WARNING` mengonfirmasi bypass. Migrasi normal dan permission fixture telah dijalankan pada schema acceptance; tidak ada reset atau wipe. Pada percobaan pertama, skrip PowerShell salah menangkap peringatan Node sebagai exception walaupun Playwright mencatat status passed. Setelah penanganan peringatan diperbaiki, acceptance nyata dijalankan ulang dan **lulus** dengan ringkasan bukti Laravel dan Electron. Schema yang sudah memiliki seluruh migrasi dan permission dapat digunakan kembali tanpa migrasi ulang; schema parsial ditolak. Karena `SHOW GRANTS` dilewati, bypass **tidak membuktikan user hanya memiliki akses ke schema acceptance**; isolasi hak akun harus dikonfirmasi secara terpisah. Tidak ada perubahan konfigurasi atau data production.

Regresi akhir: PHP 205/205 (1.227 assertion), JS CMS 51/51, Player 41/41. Smoke Electron 2/2 lulus di luar sandbox Codex dengan sandbox Electron aktif; acceptance Laravel–Electron terpisah 1/1 lulus. Acceptance memeriksa frame dan waktu video meningkat, gambar paralel, restart offline, counter 3/5, upload tanpa duplikasi, applied state dan sheet/angka XLSX terhadap data fixture. Test terisolasi mencakup freeze dan skenario 50/100; freeze decoder fisik dan semua kombinasi filter/report belum dibuktikan oleh acceptance nyata.

Installer baru: `players/windows/dist/phase1-acceptance/CMS-Signage-Player-2.0.0-x64-Setup.exe`, **121.511.406 byte**, SHA-256 `5A9A5F8947200828E72841F747FD25616B5163A34CB7FECFFC2E1756F041A45D`. Build tidak ditandatangani (`NotSigned`) dan tidak dipasang otomatis. Metadata executable menyatakan CMS Signage Player 2.0.0; DevicePlayback, renderer, shared renderer, schema v2, ledger dan cache dalam ASAR cocok byte-for-byte dengan source. Konfigurasi `deleteAppDataOnUninstall=false` dan pembatasan HTTP localhost terhadap build unpackaged dipertahankan; upgrade dan retensi data belum diuji pada Windows bersih. Installer lama disimpan terpisah.

## Lanjutan setelah persetujuan database

Pengguna menyetujui secara spesifik MySQL `127.0.0.1:3306`, database `cms_acceptance_test`, user `cms_acceptance_user`, dan fixture sementara. Login tanpa password ditolak (SQLSTATE `HY000`, kode MySQL 1045); password kemudian dimasukkan melalui prompt lokal. Schema dan identitas koneksi terverifikasi; cakupan grant user belum diverifikasi karena bypass yang diminta.

Pada tiga percobaan awal, preflight berhenti sebelum migrasi, dua di antaranya dengan `fixture_database_grants_invalid`. Guard lama tidak mengenali escape untuk underscore pada grant MySQL; diperbaiki agar hanya menerima nama literal sesuai `partial_revokes`, dan ditambahkan tes untuk menolak wildcard. Grant aktual tetap belum diperiksa sehingga belum diketahui apakah hak terlalu luas atau memakai format/role yang belum didukung guard. Lihat [dokumentasi GRANT MySQL](https://dev.mysql.com/doc/refman/8.0/en/grant.html) mengenai escape wildcard database. Percobaan berikutnya menggunakan bypass atas instruksi pengguna dan hasilnya tercatat di atas.

Skrip [acceptance-local.ps1](../players/windows/scripts/acceptance-local.ps1) dibuka pada jendela PowerShell interaktif untuk input password tersembunyi. Password hanya berada di memory/environment proses yang menjalankan test; tidak dituliskan ke `.env` atau status. Sebelum migrasi biasa, guard memastikan host, nama dan identitas schema yang dituju, user non-root, allowlist, dan schema kosong; jika gagal, proses berhenti. Inspeksi grant dilewati hanya ketika diminta dengan opsi bypass. Aksi fixture `seed-permissions` membuat hanya delapan permission yang dibutuhkan dalam schema acceptance. Skrip menjalankan acceptance dan mencatat status tersanitasi di `storage/app/phase1-acceptance-status.json`; ia tidak membuat database/user atau membangun installer. Video test Chromium disimpan sementara di `storage/app/phase1-acceptance-bear.mp4` dan checksum SHA-256 cocok dengan nilai di bawah.

## Lanjutan pada commit 7089b80

Working tree awal bersih pada `7089b8086bd978cdd0897c3a768d37ad28baf7f2`. Pemeriksaan ulang menyelesaikan pekerjaan non-destruktif berikut:

- Dalam sandbox Codex, ACL `players/windows/node_modules/electron/dist` tidak memberi `ALL APPLICATION PACKAGES`; `icacls` terhadap direktori dependency ditolak `Access is denied`. Salinan khusus test diberi izin `(OI)(CI)(RX)` untuk SID tersebut, termasuk pada `electron.exe`, tetapi masih gagal dengan diagnosis yang sama. Salinan probe telah dihapus. Menjalankan `npm.cmd run test:e2e` di luar sandbox Codex berhasil: **2 smoke test lulus**, termasuk `sandbox=true`, `contextIsolation=true`, pairing, safeStorage, cache/restart dan revocation; satu acceptance opt-in tidak dijalankan. Tidak ada perubahan ACL drive/folder sistem, UAC, antivirus atau sandbox Electron. Pesan `os_crypt` pada percobaan sandbox Codex tidak muncul sebagai kegagalan smoke test di luar sandbox. Playwright juga mendukung `CMS_TEST_ELECTRON_EXECUTABLE` untuk memilih salinan test.
- Chrome headless memutar fixture MP4 `chromium/chromium:media/test/data/bear.mp4` yang diperoleh melalui GitHub connector; Git blob `476dc2b648491c7df32fabb694e488ea6b8c3aed`, SHA-256 `AA2786362E3D61646390BA23CB0639B6AE9C9F48B58A882A7622D94C8AFE7FE1`. Resolusi 320×180, durasi 1,068 detik; antara sampel waktu 0,208 dan 0,713 detik frame meningkat 10 ke 25 dan checksum piksel berubah. Tidak ada error halaman. Ini bukti decoding Chrome, bukan Electron atau hardware signage. File probe sementara dihapus.
- Harness kini mensyaratkan `APP_ENV=testing`, MySQL loopback, nama schema eksplisit berakhiran `_acceptance_test`, nama berbeda dari database `.env`, konfigurasi tidak cached dan user bukan root. `SHOW GRANTS` ditolak kecuali USAGE global dan grant pada schema acceptance yang sama. Konfigurasi child PHP dan server ditetapkan dari variabel `CMS_ACCEPTANCE_DB_*`; tidak mengubah `.env`. Guard untuk SQLite dan production telah diuji tanpa koneksi database pengguna. Ini bukan bukti bahwa database pilihan benar-benar private: pengguna tetap harus mengonfirmasi bahwa schema dan server terisolasi.
- Fixture menggunakan tag acak dan token kepemilikan acak, menolak company atau file dengan nama bertabrakan, memverifikasi pemilik sebelum cleanup, dan membungkus setup database dalam transaksi dengan rollback/cleanup file saat exception. `public/hot` tidak dipindah atau dihapus; jika ada, test berhenti. Screenshot berada di direktori temporer, trace acceptance dimatikan untuk menghindari credential, dan bind server diverifikasi sebelum health check. Forced termination dapat tetap meninggalkan media fixture setelah rollback; jangan membersihkannya tanpa memverifikasi tag dan path.
- XLSX diuji menggunakan OpenSpout: sheet, nama, angka completed/interrupted/failed, total milidetik, timezone, filter device, isolasi company dan teks menyerupai formula diperiksa dalam test SQLite. Harness acceptance juga membaca workbook hasil download dan membandingkan ringkasan dengan database fixture, tetapi perubahan harness belum dijalankan pada MySQL/Electron sehingga belum terverifikasi end-to-end.
- Suite PHP terbaru **191/191, 1.196 assertion**; test CMS 51/51 dan Player 41/41; Vite build, Blade compilation, PHP lint dan `git diff --check` berhasil. Uji guard baru 2/2. Installer 2.0.0 lama tetap berasal dari build sebelumnya, tidak dibangun ulang atau diuji upgrade.

File lanjutan: `players/windows/tests/e2e/{player.spec.js,always-on-localhost.spec.js}`, `tests/Browser/{always-on-fixture.php,read-acceptance-xlsx.php}`, `tests/Feature/AlwaysOnPlaybackTest.php`, `tests/Unit/AcceptanceFixtureGuardTest.php`, dan dokumen ini.

### Prasyarat acceptance berikutnya

1. Pengguna menyetujui host `127.0.0.1`, nama schema khusus berakhiran `_acceptance_test`, pembuatan/setup database tersebut dan fixture sementara. Siapkan user MySQL terbatas hanya pada schema itu; jangan gunakan root, database CMS utama, data produksi, atau schema yang digunakan bersama. Tidak ada database atau migrasi yang telah dibuat/dijalankan dalam lanjutan ini.
2. Dalam proses test saja, tetapkan `CMS_ACCEPTANCE_DB_HOST`, `CMS_ACCEPTANCE_DB_DATABASE`, `CMS_ACCEPTANCE_DB_USERNAME`, `CMS_ACCEPTANCE_DB_PASSWORD`, dan opsional `CMS_ACCEPTANCE_DB_PORT`. Jangan menyimpan password di laporan atau `.env` utama. Perlindungan fixture memeriksa grant efektif; pengaturan role/grant kompleks mungkin ditolak secara fail-closed. Tetapkan juga `CMS_REAL_ACCEPTANCE=1` ketika benar-benar siap.
3. Sebelum migrasi biasa pada schema yang disetujui, jalankan action fixture `preflight` dengan environment DB dan `APP_ENV=testing` yang sama, konfirmasikan hasil `{"safe":true}`, dan verifikasi nama database serta izin user secara independen. Jangan jalankan `migrate:fresh` atau `db:wipe`. Sediakan permission yang dibutuhkan hanya dalam schema test. Jangan menganggap preflight sebagai pengganti persetujuan pengguna.
4. Jalankan Electron di luar sandbox Codex bila perlu, dengan sandbox Electron tetap aktif seperti pada smoke test yang lulus. `CMS_TEST_ELECTRON_EXECUTABLE` dapat menunjuk executable salinan test; tidak diperlukan perubahan ACL pada percobaan yang berhasil.
5. Pastikan `public/hot` tidak ada melalui penghentian normal Vite. Gunakan fixture Chromium dengan SHA-256 di atas; `CMS_ACCEPTANCE_VIDEO_PATH` opsional menunjuk file tersebut jika download tidak tersedia. Lalu jalankan real acceptance dan uji skenario yang belum dicakup (termasuk filter/report lintas company, freeze nyata dan restart) sebelum membangun installer.

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
| PHP suite | 205/205, 1.227 assertion, SQLite in-memory |
| JavaScript CMS | 51/51 |
| Windows Player unit | 41/41; `prepare:shared` dijalankan |
| Build Vite | Berhasil |
| PHP lint file PHP diubah; Blade compilation | Berhasil |
| Composer validate | Valid; warning constraint versi OpenSpout eksak, cache Composer tidak writable |
| `git diff --check` | Berhasil; peringatan normalisasi CRLF |
| Electron E2E | 2 smoke test lulus di luar sandbox Codex dengan sandbox Electron aktif; real acceptance opt-in lulus pada run terpisah. Percobaan di dalam sandbox Codex gagal saat launch karena akses ACL. |
| Laravel + Electron acceptance | Lulus dengan database acceptance yang disetujui; grant user tidak diinspeksi karena bypass yang diminta. |
| Frame H.264, playback time, window paralel, restart/offline | H.264 dan gambar tampil di Electron; waktu dan jumlah frame meningkat, restart offline dan kuota 3/5 lulus. Hardware dan freeze decoder nyata belum diuji. |
| Assignment multi-device, limit 0/1/3/5/100, PoP, export XLSX | Test terisolasi mencakup assignment/kuota, termasuk 50/100; acceptance nyata menguji 3/5, upload/reconnect tanpa duplikasi dan membandingkan nilai workbook dengan database. Tidak semua kombinasi diuji dalam acceptance nyata. |
| Installer baru | Build dan pemeriksaan isi ASAR lulus; checksum dan batas instalasi di atas. Installer lama dengan SHA-256 `BF8D365B9AB8F5B07C844D5F69CF5FD7DDBADCC330E1C6077A6B80F493E08279` disimpan terpisah. |

Bukti ringkas tersanitasi: baseline PHP `tests=188 passed=182 failed=2 errors=4 assertions=1156`; hasil akhir `tests=205 passed=205 assertions=1227`. Baseline dan hasil JS/Player di atas berasal dari perintah test aktual. Log Playwright lokal lama ada di `phase1-e2e.log` (diabaikan Git), termasuk rincian kegagalan launch; jangan membagikan trace mentah tanpa audit. Screenshot acceptance disimpan sementara dan dibersihkan oleh harness. `npm ci` belum dijalankan agar instalasi dependency yang ada tidak dihapus saat akses jaringan belum tersedia.

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

Audit awal menemukan risiko tabrakan tag, cleanup berdasarkan code semata, pemindahan `public/hot`, dan validasi XLSX yang hanya memeriksa header ZIP. Perubahan dan batas baru dijelaskan pada bagian **Lanjutan pada commit 7089b80** di atas. Guard baru dan acceptance telah dijalankan pada database MySQL yang disetujui; hak grant akun belum diaudit karena bypass.

Siapkan database test MySQL baru yang benar-benar terisolasi, konfirmasikan host/database dan `APP_ENV`, dan minta persetujuan eksplisit untuk fixture sebelum membuat atau menjalankannya. Jangan menjalankan `migrate:fresh`, `db:wipe`, cleanup massal atau seeder keseluruhan pada database existing. Gunakan perintah migrasi biasa dan seeder permission yang diperlukan hanya pada database test yang telah disetujui. Setelah semua prasyarat dan persetujuan terpenuhi, jalankan `CMS_REAL_ACCEPTANCE=1` sesuai instruksi proyek, lalu verifikasi isi XLSX dan skenario yang belum dicakup secara terpisah.

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
