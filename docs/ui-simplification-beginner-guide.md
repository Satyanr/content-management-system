# UI sederhana dan panduan pemula

Implementasi menggunakan source workspace yang sudah memiliki Playlist Studio, Scheduler, Devices, Player API v1, Windows Player, dan hotfix metadata. Tidak ada migrasi tabel, reset database, dependency baru, commit, atau push.

## Perubahan dan penggunaan

- Beranda, Pustaka Media, Playlist, Jadwal, dan Perangkat memiliki judul/petunjuk utama berbahasa Indonesia dan kartu panduan alur kerja. Sidebar menerjemahkan nama menu bawaan saat ditampilkan; nama menu kustom dan data menu tetap dipertahankan.
- Studio memakai tiga panel: Pages dan Pustaka Media di kiri, kanvas di tengah, Properties pilihan di kanan. Toolbar utama berisi kembali, nama, resolusi, pratinjau, simpan, dan Bantuan. Klik resolusi membuka pengaturan kanvas lanjutan.
- Timeline dan pilihan lanjutan tersembunyi pada pemakaian pertama. Lebar/visibilitas panel, timeline, dan pilihan lanjutan disimpan melalui `cms.studio.ui.v1.user.<ID>` di localStorage. Pilihan sementara saat preview tidak menimpa preferensi editor.
- Tarik media ke area kosong kanvas atau pilih **Buat Window Baru** untuk menampilkan media secara bersamaan dalam Window berbeda. **Tambah Window Baru** membuat Window kosong. **Tambahkan ke Window Ini** menambahkan item berurutan dalam Window pilihan. Window yang sudah ada tetap dipertahankan.
- Pilih Window melalui kanvas/daftar. Seret kotak untuk memindahkan dan gunakan handle untuk resize; X/Y/lebar/tinggi menggunakan pixel asli kanvas. Preset Fit/Tengah, kanvas penuh, separuh kiri/kanan mengubah draft geometri Window pilihan. Simpan Window untuk menerapkan. Kunci Window harus dibuka sebelum resize; jaga rasio Window hanya mengatur geometri kotaknya.
- Pilih item melalui **Item dalam Window** atau **Atur isi media**. Zoom 100–500%, geser horizontal/vertikal -100–100%, Fit, Fill/Crop, Stretch, dan jaga rasio media hanya mengubah isi item tersebut. Contoh: Window tetap 800×600 saat media diperbesar menjadi 200%. Crop tidak mengubah berkas asli. **Reset / Fit** atau Alt+R / Alt+F mengembalikan zoom/pan/fit; shortcut tidak mengambil input ketika mengetik dalam form. Gunakan **Simpan Media**, atau tombol Simpan utama untuk menyimpan draft yang aktif.

## Panduan

Komponen reusable menampilkan enam langkah dari unggah hingga aktivasi perangkat. Panduan terbuka otomatis sekali per sesi tab browser, dengan sessionStorage `cms.guide.v1.user.<ID>`. Pilihan **Jangan tampilkan lagi** disimpan per akun dan versi pada key yang sama di localStorage. Bantuan selalu membuka kembali panduan, termasuk untuk mengubah pilihan tersebut. Preferensi tidak tersinkron antar-browser/perangkat. Escape menutup; Tab/Shift+Tab berada di dialog; latar menjadi inert dan fokus dikembalikan saat ditutup. Komponen hanya dimuat di CMS, bukan Windows Player.

## Penyimpanan dan paritas playback

Tidak diperlukan migrasi data lama. `playlist_zone_items.settings.media_transform` menyimpan lima field tervalidasi: `scale` (1–5), `offset_x`, `offset_y` (-100–100), `fit_mode` (`contain`, `cover`, `stretch`), dan `keep_aspect_ratio` boolean. Offset adalah persen dari lebar/tinggi Window; CSS menggunakan translate lalu scale dengan origin tengah. Transform dibatasi oleh batas Window. ID request editor dan settings lain tidak diserialisasi ke Player.

`PlaylistPlayback` dan `DeviceManifestService` menambahkan field opsional `mediaTransform` per item. Player API tetap `schemaVersion: 1`; schema menerima manifest lama tanpa field tersebut. Default mengikuti fit Window lama dengan zoom 1 dan offset 0. Transform ikut menentukan revision manifest; checksum media asli tidak berubah. Authorization playlist/media, workspace aktif, isolasi perusahaan, dan URL streaming private tetap diterapkan pada server.

Studio editor dan preview menggunakan helper transform yang sama. Adapter Windows meneruskan field opsional ke renderer bersama, termasuk playback dari cache lokal. Player lama tetap membaca manifest v1, tetapi perlu diperbarui ke **1.0.1** agar menerapkan pengaturan media baru.

Installer berhasil dibangun: `players/windows/dist/CMS-Signage-Player-1.0.1-x64-Setup.exe` (121505107 byte). SHA256: `0FF3FF6F8D03B9D495AF20CCCFCA7A848BAD9413B5D915AC87218A47D6AA6328`. Build memakai Electron lokal dengan `npm.cmd run dist -- --config.electronDist=node_modules/electron/dist` setelah unduhan build biasa timeout. Installer belum ditandatangani; installer tidak dijalankan dalam pengujian ini. Installer 1.0.0 tetap tersedia. Artifact dist diabaikan Git.

## File utama

| Area | File baru/diubah |
|---|---|
| Onboarding | Baru: `resources/js/cms-guide.js`, `resources/views/components/cms/welcome-guide.blade.php`, `workflow-tip.blade.php`. Integrasi: `resources/js/app.js`, layout admin, topbar, sidebar, halaman Studio |
| Halaman CMS | `resources/views/admin/{dashboard,media-assets,playlists,scheduler,devices}/index.blade.php`, fragmen Livewire media, playlist, scheduler, devices untuk label/form/tindakan utama |
| Studio | Baru: `resources/js/studio-ui.js`, `resources/views/livewire/playlists/studio/media-properties.blade.php`. Integrasi: `playlist-studio.js`, `studio-interactions.js`, `studio-geometry.js`, `studio-timeline.js`, root Blade Studio dan fragmen toolbar/pages/library/windows/properties/window-properties/file-window/timeline/preview |
| Server | Baru: `app/Services/MediaTransform.php`. Diubah: `PlaylistWindowEditor`, `PlaylistLayout`, `PlaylistPlayback`, `DeviceManifestService`, Livewire `PlaylistStudio` dan `InteractsWithStudioWindows` |
| Player | `docs/player-api-v1.schema.json`, `studio-playback-renderer.js`, `players/windows/src/playback/adapter.js`, package/lock versi 1.0.1, README dan test Player |
| Regresi | `tests/js/studio-ui.test.mjs`, `studio-playback.test.mjs`, feature Playlist Playback/Studio/Isolation, Player API, serta penyesuaian assertion label di Devices/Scheduler. Browser fixture dan acceptance di `tests/Browser/` |

Workspace juga mempertahankan hotfix metadata pada streaming controller, playback engine, renderer, timeline, test, dan [catatan diagnosis hotfix](playlist-preview-metadata-hotfix.md).

## Validasi aktual

| Pemeriksaan | Hasil |
|---|---|
| Seluruh PHP, `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest` | 167 test, 161 lulus, 1019 assertion; dua assertion gagal dan empat error lama dijelaskan di bawah |
| PlaylistPlayback suite terfokus | 15 lulus, 144 assertion; termasuk validasi transform, permission, isolasi, Window dan sequence |
| JavaScript `node --test tests/js/*.test.mjs` | 47/47 lulus; preferensi per akun/versi, transform independen, metadata/timeout/sequence |
| Windows `npm.cmd test` | 25/25 lulus; adapter/renderer transform, manifest lama, schema, cache/offline/checksum/revision |
| Windows `npm.cmd run test:e2e` | 2/2 lulus di Electron Windows; termasuk style transform pada elemen media sebenarnya |
| Vite / Blade / sintaks | `npm.cmd run build`, `php artisan view:cache`, lint compiled Blade, lint 15 file PHP yang berubah/baru, parsing JSON schema, dan `git diff --check` lulus; cache view dibersihkan setelah pemeriksaan |
| Chrome UI, `node tests/Browser/studio-ui-smoke.mjs` | Dua run akhir lulus pada viewport 1440×1000: onboarding/fokus/Escape/Help/preferensi, zoom/pan tersimpan setelah reload, geometri tetap, empat Window paralel, sequence, drop, resize mouse satu Window tanpa mengubah yang lain, preferensi timeline |
| Chrome playback, `node tests/Browser/preview-smoke.mjs` | MP4 H.264 fixture 41358 byte: metadata durasi 1.068118 detik, dua video menampilkan frame dan bermain bersamaan, Auto dan manual 4 detik; Range 206 dan 64 byte sesuai; company lain 404; video rusak error; timeout 8 detik error tanpa infinite loading |
| Installer | NSIS x64 1.0.1 berhasil dibangun; tidak diinstal otomatis |

Enam kegagalan PHP juga ada pada baseline sebelum perubahan UI: navigation menu dan profile mengharapkan `/dashboard` serta `/profile` yang 404; Authentication, EmailVerification, PasswordConfirmation, Registration mengacu route bernama `dashboard` yang tidak terdaftar. Ini belum diperbaiki karena berada di luar perubahan UI Studio. Suite penuh **tidak** diklaim lulus.

Acceptance browser menggunakan Laravel server lokal dan akun/perusahaan/media fixture sementara, dengan session login autentik dan endpoint media private. Cleanup terbatas pada fixture ber-tag; tidak ada penghapusan/reset data asli. Screenshot aktual: [Panduan](screenshots/welcome-guide.png), [Studio sederhana dengan zoom berbeda](screenshots/studio-simple.png).

## Batas pengujian dan pemeriksaan manual

Label utama sudah diterjemahkan; beberapa status/diagnostik teknis dan pesan validasi lama masih mengikuti bahasa existing. Preferensi bergantung pada storage browser yang tersedia; jika diblokir, aplikasi tetap berjalan tanpa persistensi preferensi. Pengujian otomatis belum mencakup seluruh ukuran laptop, monitor signage fisik, seluruh codec, audio perangkat, atau instalasi/upgrade NSIS di mesin bersih. Tidak ada konversi video yang dilakukan: fixture H.264 terbukti dapat diputar; dukungan file pengguna bergantung codec/container browser.

Untuk verifikasi Chrome: buka DevTools Network (Preserve log), pilih media pada preview, periksa MIME `video/mp4`, Range 206/Content-Range, lalu bandingkan dua Window dengan zoom berbeda. Simpan transform, reload Studio, dan sinkronkan Player 1.0.1: revision berubah, berkas media tidak perlu berubah. Uji akun perusahaan lain tidak dapat membuka URL media/playlist tersebut. Untuk metadata/error/codec, ikuti [panduan DevTools hotfix](playlist-preview-metadata-hotfix.md).
