# Windows Player — Phase 1.5 release candidate

**Status: PARTIAL**, 19 November 2026. Regresi terisolasi dan playback mock lulus; clean install/uninstall, upgrade nyata, dan soak 24–72 jam masih pending. Audit grant MySQL acceptance **DEFERRED** atas keputusan pengguna dan wajib diselesaikan sebelum deployment produksi.

## Lanjutan tanpa database acceptance

- Pengguna memutuskan menunda audit MySQL sampai tahap deployment server. Tidak ada audit grant baru, bypass autentikasi, koneksi atau fixture MySQL acceptance yang dijalankan pada lanjutan ini. Hasil Laravel–Electron Fase 1 adalah **baseline historis**, bukan bukti keamanan database terbaru. Sebelum produksi, administrator harus memastikan akun mempunyai hak literal hanya pada schema test, tanpa privilege global/role tambahan, dan audit serta preflight tanpa bypass harus lulus.
- Hasil aktual: PHP **205/205** (1.227 assertion) memakai SQLite in-memory; JavaScript CMS **51/51**; Windows Player **43/43**; dua smoke Electron dengan API mock **2/2**. Build Vite berhasil. Pengujian ini tidak memerlukan database acceptance.
- [mock-video-soak.mjs](../players/windows/scripts/mock-video-soak.mjs) menggunakan Device/API mock lokal, fixture H.264 Chromium dengan checksum terverifikasi, dan userData sementara baru. Uji terbaru 15 detik lulus: dua Window terpisah menampilkan video dan gambar, frame/waktu video bertambah, **13** completed video sebelum offline, restart offline berhasil, **9** event tertunda, **21** event unik diterima, dan **0** duplikasi. Uji 30 detik sebelumnya juga lulus dengan 27 completed sebelum offline dan 0 duplikasi. Hasil ini memakai mock, bukan Laravel terbaru atau perangkat signage dan **bukan soak 24–72 jam**. Sampel dan summary tersanitasi disimpan di direktori temp test yang dicetak oleh tool.
- Windows Home Single Language terdeteksi; executable Windows Sandbox tidak ditemukan dan direktori userData Player sudah ada pada profil Windows. Instalasi/uninstall installer packaged pada profil ini dapat menyentuh registrasi atau userData existing. Tidak ada clean install, uninstall, atau upgrade yang dilakukan; jalankan prosedur VM bersih di bawah pada lingkungan terisolasi. Metadata `deleteAppDataOnUninstall=false` bukan bukti retensi setelah uninstall yang sesungguhnya.
- Installer 2.0.0 Fase 1 tetap disimpan, tidak dipasang atau ditimpa. Versi 2.0.1 belum dibuat: perbedaan versi diperlukan untuk uji upgrade, tetapi penerbitan RC ditunda sampai acceptance installer yang diwajibkan tersedia. Tidak ada klaim code signing, reboot, power-loss atau uji hardware.
- Fondasi dan kontrak yang perlu diikuti Android Player didokumentasikan di [android-player-foundation.md](android-player-foundation.md); belum ada aplikasi atau perubahan schema Android.

Untuk mengulang uji mock pendek, sediakan fixture Chromium `bear.mp4` yang hash-nya sesuai dan jalankan dari `players/windows`:

```powershell
$env:CMS_TEST_VIDEO_PATH = '<path fixture bear.mp4>'
node scripts/mock-video-soak.mjs --seconds=30
```

Tool menolak fixture dengan SHA-256 berbeda; tanpa variabel tersebut ia mencari salinan test lokal `storage/app/phase1-acceptance-bear.mp4`. Jangan gunakan media atau credential produksi. Untuk pengujian 24–72 jam pada Device khusus, gunakan `soak.mjs` sesuai petunjuk di bagian Recovery dan soak; durasi itu belum dijalankan.

## Audit setelah grant database-level dilaporkan diberikan

Audit read-only dicoba dua kali tanpa bypass setelah pengguna melaporkan grant schema diberikan. Kedua percobaan berhenti pada autentikasi dengan kode MySQL **1045**, sebelum `SHOW GRANTS` atau preflight dapat diperiksa. Kode ini tidak cukup untuk membedakan password, host akun, atau metode autentikasi; grant baru belum terverifikasi. Status audit sebelumnya diarsipkan, dan diagnostik hanya menyimpan kode driver tanpa password atau pesan koneksi mentah. Tidak ada migrasi, fixture, acceptance, reset database, atau penghapusan data operator pada percobaan ini. Pemeriksaan installer dan soak 24–72 jam tetap disiapkan dalam panduan di bawah dan belum dinyatakan lulus.

## Audit setelah perubahan privilege

Setelah pengguna menyatakan privilege telah diperbaiki, audit read-only dicoba dua kali tanpa bypass. Keduanya berhenti sebelum `SHOW GRANTS` karena MySQL mengembalikan **1044** ketika koneksi memilih `cms_acceptance_test`. Hasil ini menunjukkan akses schema ditolak, tetapi belum membuktikan apakah grant global telah dihapus atau mengapa grant schema tidak berlaku. Tidak ada fixture, migrasi, atau acceptance database yang dijalankan pada percobaan ini.

Audit diperluas untuk membaca identitas dan klasifikasi grant tanpa memilih database terlebih dahulu, lalu mencoba memilih hanya schema acceptance; error yang disimpan hanya kode driver. Audit tambahan **lulus autentikasi dan identitas akun/port**, tetapi hanya menemukan **USAGE pada `*.*`** dan tidak menemukan grant pada schema acceptance. Pemilihan `cms_acceptance_test` tetap ditolak dengan kode **1044**, sehingga preflight tanpa bypass **FAIL**. `partial_revokes` dilaporkan OFF. Tidak ada privilege global selain USAGE yang terlihat pada hasil audit ini, tetapi akun belum mempunyai akses schema yang diperlukan. Tidak ada fixture, migrasi, atau acceptance database yang dijalankan.

Administrator perlu memberikan grant yang diperlukan secara literal pada schema `cms_acceptance_test` kepada akun yang benar, tanpa privilege global atau role luas. Ketika `partial_revokes` OFF, underscore dalam grant tingkat schema MySQL dapat berfungsi sebagai wildcard dan harus diperlakukan secara literal. Setelah grant selesai, audit dan preflight tanpa bypass harus diulang. Prosedur VM clean install/uninstall/retensi/upgrade dan tool soak 24–72 jam di bawah tetap siap, tetapi belum dijalankan atau dinyatakan lulus.

## Audit ulang setelah permintaan pembatasan grant

Audit read-only `acceptance-grant-audit.ps1` dijalankan kembali tanpa bypass pada 19 November 2026. Koneksi berhasil dan `DATABASE()`, `CURRENT_USER()` serta port cocok dengan konfigurasi acceptance. Hasilnya **FAIL**: `SHOW GRANTS` masih menunjukkan privilege pada `*.*`, termasuk `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `FILE`, `INDEX` dan `ALTER`. Tidak ada grant role yang muncul pada hasil audit yang diklasifikasikan, tetapi privilege global tersebut sudah cukup untuk menolak akun. `partial_revokes` dilaporkan OFF.

Preflight tanpa `ACCEPTANCE_SKIP_GRANT_INSPECTION` juga **FAIL**, sehingga regresi dan acceptance yang menggunakan database khusus tidak dijalankan pada percobaan ini. Tidak ada grant, data database utama, credential operator atau data operator yang diubah. Hasil audit tersanitasi tersedia lokal di `storage/app/phase15-grant-audit.json`; password tidak disimpan dalam laporan.

Installer 2.0.0 Fase 1 masih tersedia dan hash SHA-256-nya cocok dengan nilai di bawah. Prosedur VM untuk clean install, uninstall, retensi dan upgrade serta perintah soak 24–72 jam tetap disiapkan di bagian berikut; tidak ada pengujian tersebut yang dijalankan pada percobaan ini. Administrator perlu menghapus privilege global akun acceptance dan memberikan hanya grant literal schema `cms_acceptance_test`, lalu audit dan preflight tanpa bypass harus diulang sebelum acceptance database dilanjutkan.

## Baseline dan hasil nyata

- Branch `main`, HEAD awal `ba779b7c21c226de7374b4dfebefde7b7275c959`, working tree bersih. Fase 1 mencatat PHP 205/205, JS CMS 51/51, Player 41/41, dua smoke Electron dan satu acceptance Laravel–Electron lulus. Detail cakupan dan batasnya ada di [laporan Fase 1](stabilization-local-acceptance-phase1.md).
- Audit read-only pada `127.0.0.1:3306` berhasil memverifikasi `DATABASE()`, `CURRENT_USER()` dan port terhadap `cms_acceptance_test` dan `cms_acceptance_user`. `partial_revokes` **OFF**; hasil `SHOW GRANTS` terklasifikasi **ALL PRIVILEGES pada `*.*`**. Ini hak global, sehingga persyaratan akses terbatas ke schema **FAIL**. Tidak ada password atau grant mentah disimpan di laporan.
- Preflight tanpa bypass **FAIL** dengan guard grant yang ada. Penolakan terhadap privilege global benar dan tidak ditemukan bukti false positive yang membenarkan pelonggaran. Bypass sebelumnya diperlukan untuk acceptance Fase 1 atas permintaan pengguna, tetapi tidak membuktikan hak yang terbatas dan tidak disarankan digunakan lagi untuk release. Tidak ada grant atau database yang diubah pada audit ini. Minta administrator MySQL membatasi user khusus ke schema acceptance yang literal, tanpa hak global/role luas, lalu jalankan ulang audit dan preflight tanpa bypass. Periksa semua grant efektif, termasuk peran dan wildcard; jangan mengubah hak pada database CMS utama.
- Script audit [acceptance-grant-audit.php](../tests/Browser/acceptance-grant-audit.php) dan [acceptance-grant-audit.ps1](../players/windows/scripts/acceptance-grant-audit.ps1) hanya membaca identitas, variabel dan grant. Password diminta sebagai SecureString dan diteruskan melalui environment proses; hasil tersanitasi disimpan lokal. Hasil ini bukan audit hak akun lain. File status lokal sebelumnya tidak ditimpa otomatis.
- Installer Fase 1 tersedia di `players/windows/dist/phase1-acceptance/CMS-Signage-Player-2.0.0-x64-Setup.exe`; ukuran 121.511.406 byte, SHA-256 `5A9A5F8947200828E72841F747FD25616B5163A34CB7FECFFC2E1756F041A45D`, `NotSigned`. Metadata executable menunjukkan CMS Signage Player 2.0.0. DevicePlayback, renderer, shared renderer, manifest v2, ledger, dan cache dalam ASAR sama dengan source; kode packaged hanya mengizinkan bypass HTTP localhost saat `!app.isPackaged`. Ini inspeksi paket, bukan instalasi atau uji HTTPS live pada perangkat target.
- Tes PHP lengkap **205/205** (1.227 assertion), Player dengan tooling baru **43/43**, tes guard PHP **16/16**, JS CMS **51/51**. Sampler soak dijalankan nyata selama **6 detik** pada Player tersembunyi, belum dipair, dengan userData sementara baru: menghasilkan sampel CPU/memori, status dan renderer load; tanpa video, heartbeat atau counter nyata. Ini hanya smoke tooling, **bukan soak 24 jam**, tidak membuktikan metrik pada Device terhubung.

## Installer dan upgrade: prosedur manual pada VM bersih

Semua langkah ini **PENDING**. Pakai snapshot/VM Windows dan akun Windows khusus acceptance; jangan memasang atau meng-uninstall di akun operator. Gunakan CMS HTTPS test, Device test dan media test. `--allow-localhost-http` hanya untuk Player unpackaged; installer packaged harus menggunakan HTTPS dengan sertifikat valid.

1. Pastikan VM tidak memiliki instalasi Player atau userData operator. Catat snapshot, versi Windows, installer hash di atas, identitas akun Windows dan waktu mulai.
2. Instal 2.0.0 secara interaktif. Periksa entri Start Menu, nama/versi, lokasi instalasi dan apakah aplikasi dapat dimulai dari shortcut. Jangan menganggap metadata paket sebagai bukti shortcut berhasil.
3. Buat Device dan aktivasi khusus acceptance di CMS; pair menggunakan HTTPS. Periksa credential tersimpan terenkripsi dan tidak tampil di log. Tampilkan Playlist dengan dua Window, gambar dan video H.264, lalu periksa posisi, rasio, pergerakan frame, fullscreen dan kiosk. Periksa pengaturan PIN dengan akun test.
4. Pada akun yang sama, tutup proses secara normal. Catat lokasi userData test, inventaris cache dan checksum file yang stabil setelah proses berhenti, termasuk `credentials.bin` dan `playback/playback.journal`; jangan membuka atau membagikan isi credential. Simpan salinan data test sebagai bukti di VM.
5. Putuskan jaringan/server test dan buka Player lagi. Pastikan Playlist yang sudah tersinkron diputar, counter tidak mundur, dan event tertunda tetap ada. Nyalakan kembali server; pastikan heartbeat dan upload event diterima tanpa duplikasi. Bandingkan daftar event ID/counter di CMS test; jangan catat credential.
6. Tutup Player, uninstall melalui Windows pada VM test. Periksa shortcut dan file aplikasi, lalu verifikasi userData test, credential, cache dan jurnal masih ada. Instal kembali dan periksa pairing/pemutaran offline serta sinkronisasi. Jangan menghapus userData untuk membuat tes terlihat bersih.
7. Untuk upgrade versi yang sah, pasang build versi lama yang berbeda, pair dan kumpulkan state pada VM terpisah, kemudian instal build baru dan ulangi pemeriksaan retensi. Installer Fase 1 sebelumnya dan terbaru sama-sama **2.0.0**: reinstall antar keduanya tidak membuktikan upgrade versi. Uji upgrade 2.0.0 ke 2.0.1 baru dapat dilakukan setelah 2.0.1 dibangun.

## Recovery dan soak

Uji restart proses, logout/login Windows dengan autostart diaktifkan, putus/sambung jaringan, dan outage/restart Laravel test sebagai skenario terpisah. Catat waktu, Device, heartbeat server, jumlah event pending dan completed sebelum/sesudah tiap langkah. Bandingkan event ID setelah reconnect untuk mendeteksi duplikasi. Restart proses tidak membuktikan reboot OS; reboot Windows dan power-loss fisik tetap **PENDING** sampai benar-benar dilakukan pada perangkat fisik yang disiapkan.

Tool [soak.mjs](../players/windows/scripts/soak.mjs) meluncurkan **Player unpackaged baru** dengan userData acak di direktori temp, tidak menempel pada Player operator, tidak menghapus userData, dan menulis `samples.jsonl` di direktori run yang dicetak saat start. Gunakan Device/Playlist/media khusus acceptance. Untuk jaringan production-like gunakan HTTPS; flag localhost hanya untuk development unpackaged.

```powershell
cd players/windows
npm.cmd run prepare:shared
node scripts/soak.mjs --seconds=86400 --interval=30
# Untuk 72 jam: --seconds=259200
```

Pair Device test secara manual pada jendela yang muncul. Simpan dan amankan direktori run untuk analisis, hentikan dengan Ctrl+C bila diperlukan; tool menutup hanya Player yang diluncurkannya. Sampel mencakup CPU dan working set per proses Electron, item aktif dari sesi jurnal, jumlah pending dan completed per item/epoch, cache bytes, status sync, frame dan waktu video, perkiraan stall, renderer gone/load, serta heartbeat yang diamati **setelah instrumentasi terpasang**. Periode sebelum pairing/observer tidak dianggap heartbeat sehat. `stalled` adalah indikator sampel, bukan bukti visual frame hitam; ganti/reload elemen video mereset pembanding. Jurnal di atas 64 MiB atau saat tidak dapat dibaca ditandai unavailable, bukan dihitung nol. Counter dapat mencakup baseline server dan epoch berbeda; bandingkan juga di CMS. Nilai CPU yang tidak valid ditandai null.

Sebelum soak, tentukan waktu mulai/akhir, media, Device dan ekspektasi batas putaran; kumpulkan sampel dan log server tersanitasi secara periodik. Periksa pertumbuhan memori, CPU, cache dan queue, perubahan frame, renderer restart, heartbeat dan event ganda. Jangan menyebutnya lulus kecuali durasi 24–72 jam benar-benar terpenuhi dan bukti diperiksa. Tidak ada soak panjang atau power-loss fisik yang telah dilakukan pada fase ini.

## Keputusan versi dan release

Versi patch **2.0.1 direkomendasikan** untuk membedakan artefak baru dan memungkinkan uji upgrade yang nyata dari 2.0.0. Versi source dan lockfile belum diubah serta installer RC belum dibangun, karena security grant dan installer acceptance masih blocked/pending. Setelah hak akun aman dan pemeriksaan installer/recovery yang diwajibkan lulus, ubah versi secara konsisten, jalankan regresi, build installer terpisah, verifikasi source ASAR/HTTPS dan checksum, lalu lakukan uji upgrade nyata. Code signing resmi masih pending; jangan menyebut installer signed sebelum sertifikat valid digunakan dan diverifikasi. Tidak ada release atau push otomatis.
