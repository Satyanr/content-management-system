# Dashboard dan navigasi Digital Signage

Dashboard kini berfokus pada operasional signage. Tampilan memakai sidebar navy, latar slate terang, kartu putih, aksen biru, radius 12px, dan icon stroke dari komponen existing. Route, permission, data perusahaan, serta mekanisme workspace tetap dipertahankan.

## Ringkasan dashboard

- Empat KPI jika seluruh izin tersedia: Total Perangkat, Perangkat Online, Playlist Aktif, dan Jadwal Aktif Saat Ini. Rincian offline/belum terhubung, pending, nonaktif/dicabut, serta jadwal mendatang ditampilkan pada kartu terkait. Tidak ada angka dummy atau statistik sesi pengguna.
- Online menggunakan `Device::connectionStatus()` dengan `withCount('activeCredentials')`: heartbeat segar tanpa credential tidak dihitung online. Disabled, pending, revoked, belum terhubung, stale, dan heartbeat masa depan ditangani sesuai method existing. Kategori offline pada kartu mencakup Offline dan Never connected; kategori lain ditampilkan terpisah.
- Playlist aktif berarti `is_active`, bukan bukti sedang diputar. Jumlah jadwal aktif mencakup jadwal bertumpuk; jadwal mendatang menghitung jadwal eligible yang tidak aktif sekarang dan memiliki occurrence dalam tujuh hari berikutnya.
- **Dijadwalkan Tayang** memakai `ScheduleResolver::resolve()` untuk pemenang dan `timeline()` untuk transisi berikutnya. Occurrence dan waktu lokal memakai `ScheduleClock` existing. Batas horizon tujuh hari tidak ditampilkan sebagai transisi palsu. Bagian ini menjelaskan bahwa jadwal bukan telemetri playback perangkat.
- Enam perangkat terbaru berdasarkan last seen memiliki status, platform, dan tautan detail berdasarkan public UUID. Aktivitas terbaru dibatasi lima, hanya untuk pengguna dengan `activity_logs.view`.
- Akses cepat membuka halaman modul dengan label **Buka Konten / Playlist / Jadwal Tayang / Perangkat**. Halaman tersebut menyediakan tindakan sesuai permission existing; tombol dashboard tidak mengklaim langsung membuka form yang belum memiliki deep link.
- Data merupakan snapshot saat halaman dibuka, dengan waktu pengambilan UTC. Muat ulang halaman untuk memperbarui; tidak ada polling/telemetri baru.

## Isolasi dan authorization

`SignageDashboard` menerapkan scope `company_id` secara eksplisit pada device, playlist, dan aktivitas. Pengguna biasa mengikuti perusahaan akunnya, meskipun session workspace dipalsukan. Null workspace tidak memberikan akses global kepada pengguna biasa.

Super-admin dengan workspace pilihan hanya melihat perusahaan tersebut. Mode Semua Perusahaan boleh menampilkan agregat sesuai permission, tetapi tidak menampilkan pemenang Playlist global. Pengguna diminta memilih workspace untuk melihat jadwal suatu perusahaan dan melakukan perubahan. Mutasi modul tetap memakai guard existing; dashboard hanya melakukan pembacaan.

KPI perangkat memerlukan `devices.view`, KPI Playlist memerlukan `playlists.view`, ringkasan jadwal memerlukan `scheduler.view` dan `playlists.view`, feed aktivitas memerlukan `activity_logs.view`. Tautan dan bagian terkait mengikuti izin yang sama. Pengguna tanpa akses modul mendapat petunjuk untuk menghubungi administrator.

## Navigasi database

Menu tetap berasal dari `SidebarMenuService` dan tabel `menus`, bukan daftar link statis. Mapping presentasi mengelompokkan route existing:

1. **Dashboard**.
2. **Operasional**: Konten, Playlist, Jadwal Tayang, Perangkat.
3. **Administrasi**, dapat dibuka/tutup: Pengguna, Perusahaan, Hak Akses / Peran, Hak Akses / Izin, Manajemen Menu, Pengaturan, Log Aktivitas, Riwayat Login.
4. **Menu lainnya** untuk route/grup kustom. Judul kustom dan hierarki tetap dipertahankan. Hanya container bawaan tanpa route bernama `CMS Core` dan `Content` yang diratakan untuk presentasi.

Filter memperhatikan `is_active`, permission menu, serta middleware permission route. Route yang tidak ada, route non-GET, dan route dengan parameter wajib tidak menjadi link sidebar. Parent tanpa link hanya tampil jika memiliki anak valid. Monitoring tanpa route/anak tidak ditampilkan. Route detail modul ikut menandai parent aktif; submenu aktif terbuka otomatis.

`DashboardNavigationSeeder` hanya menambahkan dua menu log yang belum memiliki route entry. Seeder ini tidak mengubah entry log yang telah diganti nama, dipindah, dinonaktifkan, atau diedit. Sudah dijalankan pada database lokal. `MenuSeeder` memanggilnya untuk setup berikutnya, tetapi keseluruhan MenuSeeder lama tidak dijalankan ulang pada data lokal agar edit menu existing tetap aman. Tidak ada perubahan permission atau role.

Desktop mendukung sidebar 264px/80px, tooltip melalui title, dan preferensi `cms.navigation.v1.user.<ID>` pada localStorage. Pada tablet/mobile, preferensi desktop tidak menghalangi submenu: sidebar menjadi drawer, dengan backdrop, Escape, focus trap, serta penutupan setelah navigasi. Pemilih workspace tetap tersedia pada mobile. Menu akun menyediakan Profil dan logout POST dengan CSRF.

## Onboarding dan startup aset

Panduan existing dipertahankan, terisolasi per akun dan versi. Status sesi sekarang ditandai saat panduan ditutup, bukan sebelum ditampilkan, sehingga penggantian DOM oleh navigasi Livewire tidak menghabiskan kesempatan tampil. Destroy akibat pergantian DOM tidak menandai panduan sudah dibaca. Bantuan selalu dapat membuka kembali; pilihan permanen tetap di localStorage.

Startup frontend dibundel melalui runtime Livewire ESM yang sudah tersedia di vendor. Layout memakai `@livewireScriptConfig`; `Livewire.start()` dijalankan setelah komponen Alpine CMS terdaftar. Tidak ada dependency baru atau instance Alpine kedua. Halaman profil memakai layout admin dan mempertahankan ketiga form Livewire existing. Studio tetap memakai layout fullscreen sendiri.

Acceptance production juga membuktikan bug pada router development `server.php`: `require` terhadap router Laravel tidak meneruskan nilai `false`, sehingga aset JS yang ada mendapat HTTP 200 HTML kosong. Perubahan menjadi `return require ...` memulihkan pengiriman static asset. Guard `/storage` dan pemeriksaan realpath tetap dijalankan sebelum shortcut static file; browser memverifikasi `/storage/acceptance-private` tetap 403. Ini tidak menjadikan media tenant public.

## File yang dibuat atau diubah

| Area | File |
|---|---|
| Data dashboard | Baru `app/Services/SignageDashboard.php`; diubah `app/Http/Controllers/Admin/DashboardController.php` |
| Menu dan seeder | `app/Services/SidebarMenuService.php`, `database/seeders/MenuSeeder.php`; baru `database/seeders/DashboardNavigationSeeder.php` |
| Komponen/UI | Dashboard index; sidebar, topbar, company-indicator, footer; baru `components/admin/nav-item.blade.php` dan `components/cms/kpi.blade.php`; tambahan path icon pada `components/studio-icon.blade.php` |
| Layout dan startup | `resources/css/app.css`, `resources/js/app.js`, baru `resources/js/admin-navigation.js`, `resources/js/cms-guide.js`; layout admin/app/guest, halaman welcome, profile, dan shell admin/playlists/studio |
| Server lokal | `server.php`: meneruskan hasil router Laravel untuk static asset |
| Test | Baru `tests/Feature/DashboardNavigationTest.php`, `tests/js/admin-navigation.test.mjs`, `tests/Browser/dashboard-fixture.php`, `tests/Browser/dashboard-navigation-smoke.mjs`; tambahan regresi lifecycle pada `tests/js/studio-ui.test.mjs` |

## Validasi aktual

- Suite PHP relevan awal: **148/148 lulus, 1006 assertion** untuk DashboardNavigation, Devices (termasuk Player API), Scheduler, dan Playlists. Satu test profil admin kemudian ditambahkan; hasil suite penuh akhir dicatat di bawah.
- Suite PHP penuh akhir: **175 test, 169 lulus, 1071 assertion**, dengan dua assertion gagal dan empat error yang sudah ada pada baseline. Navigation menu dan ProfileTest masih meminta URL lama `/dashboard` dan `/profile` (404); Authentication, EmailVerification, PasswordConfirmation, dan Registration masih mengacu route bernama `dashboard` yang tidak terdaftar. Delapan test DashboardNavigation baru lulus, termasuk route profil yang benar `/admin/profile`. Suite penuh tidak diklaim lulus; alias route lama tidak ditambahkan dalam redesign ini.
- JavaScript: **49/49 lulus**, termasuk preferensi sidebar per akun, transisi desktop ke mobile, lifecycle panduan saat DOM diganti, dan seluruh test metadata/preview/geometry existing.
- PHP lint: **8 file** non-Blade yang dibuat/diubah lulus. Pint dijalankan pada enam file PHP utama. Kompilasi Blade dan lint **184 compiled view** lulus; cache view dibersihkan setelahnya. Vite production build lulus. `git diff --check` lulus.
- Acceptance Chrome nyata lulus memakai production build, lalu lulus lagi memakai Vite development: KPI database, pemenang jadwal, selected workspace dan agregat global, collapse tersimpan setelah reload, active route, drawer mobile, Escape, backdrop, focus trap, navigasi mobile, workspace selector mobile, Help/preferensi, Content Library, Studio fullscreen, Profil, logout POST dan penolakan akses dashboard setelah logout.
- Viewport: desktop 1440×1100, mobile 390×844, tablet 768×1024. Tidak ada horizontal overflow pada dashboard mobile/tablet yang diuji. Test juga memeriksa asset JS production memiliki MIME JavaScript dan byte nyata serta `/storage` tetap ditolak.
- Fixture browser menggunakan akun/perusahaan/device/jadwal sementara pada database lokal; cleanup hanya menyasar perusahaan dengan tag fixture acak yang telah divalidasi. Tidak ada reset/migrasi database. Penunjuk `public/hot` dipindahkan sementara saat acceptance production dan dikembalikan melalui `finally`; Vite dev server pengguna tidak dihentikan.

Screenshot aktual: [Desktop](screenshots/dashboard-desktop.png), [Mobile](screenshots/dashboard-mobile.png), [Drawer mobile](screenshots/navigation-mobile.png). Data pada screenshot adalah fixture acceptance, bukan data dummy dalam implementasi dashboard.

## Batasan

Belum ada telemetri konten aktual di layar. Transisi dan jadwal mendatang dibatasi horizon tujuh hari. Preferensi sidebar/panduan tersimpan pada browser yang digunakan dan tidak disinkronkan antar-browser. Beberapa form profil existing masih memakai bahasa Inggris; judul dan navigasi barunya berbahasa Indonesia. Tidak dilakukan pengujian baru installer Windows atau hardware signage karena renderer/protokol/player tidak berubah dalam tugas ini. Tidak ada commit atau push otomatis.
