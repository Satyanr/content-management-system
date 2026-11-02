# Always-On Playlist, assignment, play limits, dan Proof of Play

Implementasi memakai workspace pada commit acuan `5585dc5cdf319a25b1bef469f6341ba4aec7b171`. Tidak ada reset database, penghapusan data existing, commit, atau push otomatis. Backend dan Player diperbarui bersama dengan **manifest v2** dan Windows Player **2.0.0**.

## Cara menggunakan

1. Pilih workspace perusahaan. Buka Playlist, lalu **Perangkat / Tayangkan** pada Playlist yang diinginkan.
2. Pilih beberapa Device, gunakan filter Device Group atau **Pilih semua perangkat perusahaan**, lalu **Terapkan / Tayangkan Sekarang**. Satu Device mempunyai satu Playlist utama; satu Playlist dapat dipilih banyak Device. Tindakan memerlukan `devices.assign`, `devices.view`, dan `playlists.view`.
3. Alternatifnya, buka Device Details untuk memilih/ganti Playlist utama, Tayangkan Sekarang, atau Pertahankan Playlist Terakhir yang dilaporkan. Riwayat menyimpan actor, source, timestamp, dan command ID. Perintah baru mempunyai UUID baru; polling ulang tidak membuat perintah baru.
4. Device Details memisahkan **perintah CMS** dari **laporan Player** dan menampilkan waktu laporan terakhir. Perangkat offline menunggu sinkronisasi; tidak diklaim sudah menampilkan perintah terbaru. Daftar penggunaan Playlist mencakup Playlist utama dan Playlist terakhir yang dilaporkan, dengan label berbeda.
5. Tanpa Schedule, Playlist utama berulang. Scheduler bersifat opsional. Pada jadwal baru pilih Persistent Switch atau Temporary Override, dan target semua Device perusahaan, Device tertentu, atau Device Group existing.
6. Studio → Daftar Window dan urutan media → **Jumlah Putaran**. Nilai 0 selalu mengulang, 1 satu completed play, 10 sepuluh kali; input numerik mendukung nilai kustom hingga 1 miliar. Batas berlaku untuk setiap Device dan item. Studio Preview tidak menggunakan counter Device.
7. Buka **Proof of Play** untuk KPI, detail event, media teratas, status pengiriman, counter, serta ekspor Excel. Pilih periode, timezone, Device, grup, Playlist, media, atau status. **Reset Counter** membutuhkan `playback.reset` dan konfirmasi; histori tetap ada dan epoch baru mulai berlaku setelah Device menerima manifest baru.

## Perilaku saat offline dan fallback

Paket lengkap dipromosikan atomik setelah semua berkas lolos ukuran/SHA256 dan revision dikonfirmasi ulang. Restart memulihkan paket lengkap dari cache dan server clock tersimpan. Counter dan antrean event berada di direktori `playback`, terpisah dari cache `content`; GC media tidak menghapus counter.

Jika Laravel/internet berhenti, Player melanjutkan paket lokal yang sudah valid. Setelah horizon plan habis, v2 memakai pilihan persistent terakhir/baseline yang disertakan dalam `fallbackSelection`, bukan menjadi blank. Temporary tidak dipertahankan selamanya setelah horizon berakhir. Event yang belum diketahui setelah horizon tidak ditebak; sinkronisasi diperlukan untuk memperoleh rencana lanjutan.

Jika belum ada paket lengkap, Player menampilkan **Belum ada konten**. Playlist utama yang dinonaktifkan/dihapus dikeluarkan dari paket online berikutnya; fallback adalah persistent selection lain yang masih eligible atau baseline aktif. Jika tidak ada, paket idle/layar kosong diterapkan. Paket baru dengan file tidak lengkap/korup gagal staging dan paket lengkap sebelumnya tetap dipakai sambil retry; kegagalan tampil pada status operator. Saat offline Player belum dapat mengetahui pencabutan terbaru. Respons autentikasi 401 karena credential/device/company tidak valid menghentikan playback dan memerlukan aktivasi ulang.

Primary Playlist di database adalah pilihan dasar yang diperintahkan CMS. Persistent Schedule dapat menghasilkan pilihan efektif lain tanpa mengubah field primary tersebut. Karena itu primary, pilihan efektif, dan laporan applied ditampilkan sebagai konsep berbeda. **Pertahankan Playlist Terakhir** mengubah pilihan dasar menjadi Playlist aktif yang terakhir dilaporkan, dengan command baru.

## Scheduler dan kompatibilitas data

Migration menambahkan `playback_mode` dengan default **legacy** pada semua baris existing; perilaku jadwal lama tidak diam-diam menjadi persistent. Form/manager untuk jadwal baru menetapkan **persistent**. Pengguna dapat memilih legacy secara eksplisit saat mengedit. Rentang waktu lama tetap tersimpan.

Resolusi tetap di `ScheduleResolver`, memakai `eligible()` dan `ScheduleClock` existing untuk timezone/recurrence. `devicePlan()` adalah API per-Device dalam resolver tersebut, bukan service resolver lain. Target Device Group mengikuti membership ketika paket dibangun; perubahan membership memerlukan sinkronisasi. Target ID selalu divalidasi terhadap perusahaan yang sama dan difilter ulang saat resolusi.

- Persistent Switch memakai occurrence **start** sebagai pemicu, dan tetap menjadi baseline setelah end. Pemicu terbaru menggantikan pemicu lebih lama. Pemicu pada instant yang sama: priority lebih tinggi menang, lalu ID Schedule lebih rendah.
- Temporary/legacy override aktif mengikuti interval start inklusif, end eksklusif. Priority lebih tinggi menang; seri memakai ID lebih rendah. Jika ada persistent baseline, override harus mengalahkan priority/ID baseline tersebut. Setelah override habis, kembali ke baseline persistent yang mungkin sudah berubah saat override berlangsung.
- Perintah manual baru memotong pemicu/override yang dimulai sebelum atau tepat pada waktu command. Pemicu berikutnya dapat mengganti lagi. Ini membuat Tayangkan Sekarang efektif tanpa menghapus aturan jadwal.
- Plan memuat pilihan efektif awal, boundary pergantian, command deterministik untuk occurrence Schedule, fallback persistent akhir, validity, serta hanya Playlist/media yang dibutuhkan Device tersebut. Pemicu persistent sekali yang sudah lama berakhir tetap direkonstruksi pada paket berikutnya; recurrence mingguan terakhir juga diperhitungkan.

Form persistent masih menyimpan end/rentang untuk kompatibilitas kalender; petunjuk menjelaskan bahwa end tidak menghentikan pilihan persistent yang sudah terpicu.

## Play limits dan jurnal tahan crash

`playlist_zone_items.max_plays` default 0. Identitas counter adalah Device public UUID + item ID + counter epoch UUID. ID item unik, sehingga counter tidak berpindah ke item Playlist lain. Mengubah durasi, transform, Page, assignment, atau sinkronisasi tidak mereset counter. Menaikkan batas dapat membuat item eligible lagi; menurunkannya langsung mengecualikan item yang sudah melewati batas.

Main process memiliki `PlaybackLedger`, renderer tidak menulis filesystem. Jurnal append-only menyimpan begin, checkpoint, end, seed, dan acknowledgement secara serial. Setiap append di-flush dengan `fsync` sebelum dianggap committed. **End completed dan kenaikan counter adalah satu record atomik**; renderer menunggu commit sebelum memulai item berikutnya. Reservasi session mencegah dua sesi menghabiskan slot quota yang sama. Callback render berulang tidak menjadi completed play baru.

Jurnal dipadatkan setelah pertumbuhan sekitar 8 MiB sejak snapshot terakhir menjadi snapshot berisi counter, pending events, dan sesi terbuka, memakai temporary file yang di-fsync lalu rename. File compact yang ditinggalkan crash tidak mengganti jurnal valid. Tail tanpa newline dipotong pada recovery; record lengkap yang rusak menyebabkan gagal tertutup, bukan reset counter ke nol.

Checkpoint durasi parsial dibuat sekitar setiap satu detik aktif per Window. Setelah crash, sesi terbuka menjadi **interrupted** dengan durasi checkpoint terakhir, tanpa completed increment. Sisa parsial setelah checkpoint terakhir tidak ditebak; akurasi parsial saat crash dibatasi interval checkpoint. Gap renderer/suspend lebih dari satu detik tidak dianggap sebagai durasi aktif. Timestamp end/checkpoint diturunkan dari UTC awal sesi dan elapsed monotonic, sehingga realignment jam server saat sesi berjalan tidak membalik urutan timestamp. Restart tidak mencoba melanjutkan sesi setengah selesai sebagai completed. Acknowledgement hanya membuang event pending; counter tetap disimpan. Nilai counter manifest adalah recovery hint yang digabung dengan `max(local, server)` untuk epoch yang sama, sehingga sync tidak menurunkan counter.

Antrean dibatasi 100.000 event/sesi pending. Jika penuh atau penulisan ledger gagal, playback berhenti memulai item baru/di-pause, bukan berjalan tanpa pencatatan atau membuang event. Counter lama tidak dihapus ketika epoch baru diterima. Re-pair ke Device public ID lain tidak mengirim antrean identitas lama memakai token baru.

`DevicePlayback` hanya dipakai Player v2. Studio tetap memakai engine Preview existing. Setiap Window mempunyai sequence dan session sendiri dengan satu clock Page; Window lain tetap berjalan ketika satu Window habis quota. Pemilihan eligible dibatasi jumlah item dan mengingat item exhausted/failed agar tidak infinite loop. Video manual yang mengulang source di dalam satu interval menghasilkan satu completed play untuk interval tersebut. Error decode/metadata menghasilkan failed; Auto video dengan durasi tidak diketahui menyimpan expected duration `null`, bukan durasi palsu. Page yang memotong item menghasilkan interrupted, bukan completed. Studio memberi peringatan bila durasi item yang diketahui lebih panjang daripada Page.

Reset counter mengganti epoch dan mengembalikan current server count ke nol, dengan audit actor dan nilai epoch/count lama-baru. Event epoch lama tetap diterima sebagai histori dari paket yang pernah diterbitkan, tetapi tidak menambah counter epoch baru. Device offline tetap memakai epoch lamanya sampai sinkronisasi, sehingga reset tidak dapat memengaruhi Device yang belum terhubung.

## API dan keamanan

Schema v1 tetap di [player-api-v1.schema.json](player-api-v1.schema.json). Schema baru ada di [player-api-v2.schema.json](player-api-v2.schema.json). Player 2.0.0 mengirim `X-Player-Schema-Version: 2`; v2 memuat `playing/idle`, pemilihan Playlist per segmen, fallback selection, command ID, serta `maxPlays`, `counterEpoch`, `completedCount` pada item.

Client v1 tetap mendapat kontrak legacy untuk Device/company yang tidak memakai assignment, target/mode baru, atau play limit. Jika ada semantics baru yang tidak dapat dipenuhi v1, manifest memberi **426 `upgrade_required`**. Versi lain memberi 406 `unsupported_schema_version`. Paket v1 masih dapat divalidasi/diputar oleh adapter sebagai kompatibilitas cache; backend lama yang hanya menerima request v1 harus diperbarui bersama Player baru.

Assignment, quota/epoch, target, dan aturan jadwal ikut mengubah revision. Completed count yang dikirim sebagai recovery hint tidak mengubah content revision setiap selesai bermain. SHA256 media, private streaming, download Range, pemeriksaan revision, staging atomik, dan credential authentication existing dipertahankan.

Endpoint Device baru, semuanya memakai bearer credential dan middleware Device existing:

| Endpoint | Fungsi |
|---|---|
| `POST /api/device/playback-events` | Batch 1–50 event, maksimum body 65.536 byte; balasan `acknowledged` berisi event ID yang diterima |
| `POST /api/device/playback-state` | Konfirmasi revision, Playlist, command yang benar-benar diterapkan renderer, dan jumlah pending yang terakhir diketahui |

Ingestion dan state dibatasi bersama 30 request/menit per Device serta ingress IP existing. Query identity ditolak; company/device diambil dari credential, bukan payload. Event mencakup UUID event/session, revision, Playlist/Page/Window/item/media IDs, Schedule nullable, UTC start/end, actual/expected milliseconds, status/reason, serta epoch.

`device_playback_packages` menyimpan grant snapshot dari paket yang pernah diterbitkan: ID relasi, epoch, durasi, nama untuk laporan, dan selection yang sah. Ingestion memverifikasi against grant perusahaan/Device tersebut. Event terlambat setelah edit/hapus item tetap dapat disimpan tanpa FK ke item yang sudah dihapus. Data grant/histori tidak dipangkas otomatis agar Device offline tidak kehilangan legitimasi upload.

Database unik `(device_id,event_id)` mencegah retry menggandakan total. `(device_id,playback_session_id)` juga mencegah satu session dikirim ulang dengan event ID lain. Event ID sama dengan payload berubah ditolak 409. Batch disimpan dalam transaksi; Player menghapus pending hanya setelah acknowledgement valid di-fsync. Gangguan server/retry tidak mereset antrean.

Timestamp harus UTC, end tidak sebelum start, tidak lebih dari lima menit di masa depan, dan start tidak mendahului penerbitan grant lebih dari toleransi lima menit. Actual milliseconds harus masuk rentang, sesuai durasi yang ditetapkan, dan tidak melebihi span wall-clock dengan toleransi clock kecil. Wall-clock gap akibat suspend diperbolehkan: gap bukan durasi aktif. Completed membutuhkan durasi expected yang diketahui dan actual yang telah mencapai intervalnya. Waktu penerimaan server disimpan terpisah.

Laporan applied baru dibuat setelah frame/isi media siap atau Player sudah memastikan Window habis quota/idle. Main process memvalidasi revision, Playlist, dan command yang dikonfirmasi terhadap paket aktif; menerima paket lengkap saja tidak disamakan dengan renderer sudah menerapkannya. Receipt disimpan lokal untuk retry saat offline.

Trust model: ini laporan software dari Player terautentikasi, **bukan bukti hardware independen**. Host/Player yang dikompromikan dapat memalsukan laporan. Jurnal melindungi konsistensi/crash recovery, bukan melindungi dari administrator OS yang sengaja mengubah berkas lokal. Reboot proses dan outage benar-benar diuji; pemadaman listrik fisik dan integritas media penyimpanan/OS tetap di luar bukti pengujian ini.

## Laporan dan XLSX

Menu **Proof of Play** berasal dari tabel menu, dengan permission `playback.view`. `playback.export` dan `playback.reset` terpisah. Seeder bersifat additive, memberi permission baru ke role admin/super-admin existing, tidak ke operator view-only, dan tidak menjalankan `syncPermissions()`.

Laporan selalu scoped workspace terpilih; All Companies diminta memilih workspace untuk laporan/export/reset. Filter UI dan export menggunakan `PlaybackReport::query()` yang sama. Filter grup mengikuti membership Device saat laporan dijalankan. Nama pada event adalah snapshot sehingga event tetap dapat dibaca setelah sumbernya dihapus. Filter ID historis juga didukung melalui parameter query, meskipun dropdown modul berisi objek yang masih ada.

Durasi disimpan/dijumlahkan dalam integer milliseconds. XLSX berisi Summary per Device/Playlist/media (completed, partial, failed, milliseconds dan konversi detik/menit/jam), serta Detail per event (UTC, lokal/timezone, IDs event/epoch, status, waktu penerimaan). OpenSpout menulis bertahap, query dibaca per 500 baris; Detail memakai chunkById dan high-water mark sehingga event yang masuk setelah export dimulai tidak menggeser hasil.

Teks memakai `StringCell` eksplisit, termasuk nama yang diawali `=`, `+`, `-`, atau `@`; tidak dijadikan formula. Tidak ada token, credential, storage path, atau data company lain dalam export. ZIP XLSX dibuat pada temporary file private lalu dikirim melalui download dan dihapus setelah pengiriman.

Library **OpenSpout 4.32.0** dipilih setelah memeriksa [composer resmi versi tersebut](https://github.com/openspout/openspout/blob/v4.32.0/composer.json): mendukung PHP 8.3/8.4/8.5 dan ekstensi yang tersedia lokal. Versi 5 membutuhkan PHP lebih tinggi daripada minimum project. Instalasi mengubah hanya satu dependency; package existing tidak di-upgrade. Audit Composer mendeteksi 25 advisory pada tujuh dependency existing, bukan OpenSpout; pembaruan dependency tersebut belum termasuk perubahan fitur ini.

## Migrasi dan deployment

Enam migration additive telah dijalankan pada database lokal:

1. Kolom assignment/reported pada `devices`, tabel `device_playlist_changes`.
2. Mode/target Schedule dengan existing default legacy.
3. `max_plays`, `device_item_counters`, `device_playback_packages`.
4. `playback_events` dan status antrean/penerimaan Device.
5. Expected duration nullable untuk event gagal dengan metadata tidak diketahui.
6. `manifest_revision` pada riwayat assignment, diisi saat Player mengonfirmasi perubahan. Command UUID tetap tersedia sejak instruksi dibuat; revision paket belum diklaim sebelum konfirmasi.

Pada deployment lain, jalankan migration biasa dan `php artisan db:seed --class=PlaybackPermissionSeeder`, lalu build frontend dan upgrade Player. Jangan memakai migrate:fresh/db:wipe. Install dependencies dari composer.lock (`composer install`). `DatabaseSeeder` memanggil seeder permission baru untuk setup mendatang, tetapi seeder keseluruhan tidak dijalankan ulang pada data lokal.

Windows Player memakai package versi 2.0.0. `prepare:shared` menyalin schema v1/v2 dan renderer bersama; source Device engine dan ledger ikut dipaketkan. Installer berhasil dibuat di `players/windows/dist/CMS-Signage-Player-2.0.0-x64-Setup.exe`, ukuran **121511170 byte**, SHA256 **BF8D365B9AB8F5B07C844D5F69CF5FD7DDBADCC330E1C6077A6B80F493E08279**. Build memakai Electron lokal (`npm.cmd run dist -- --config.electronDist=node_modules/electron/dist`), tanpa publish. Isi ASAR ledger, Device engine, renderer, dan schema v2 dibandingkan byte-for-byte dengan source final: cocok. Installer belum ditandatangani dan tidak dipasang otomatis. Instalasi/upgrade pada Windows bersih belum diuji; acceptance memakai Player unpackaged.

## Perubahan file utama

| Area | File baru/diubah |
|---|---|
| Assignment | Baru `DevicePlaylistAssignment`, `PlaylistAssignment` Livewire dan view assignment; modifikasi Device model/Details, Playlist table, route admin |
| Jadwal/manifest | `Schedule` model, `ScheduleManager`, `ScheduleResolver`, `ScheduleTable`/form, `DeviceManifestService`, `PlayerController`; schema baru `docs/player-api-v2.schema.json` |
| Counter/Studio | Baru `DevicePlaybackCounter`; `PlaylistZoneItem`, `PlaylistWindowEditor`, `InteractsWithStudioWindows`, file-window Blade, helper peringatan durasi di `studio-timeline.js`; petunjuk Dashboard/welcome diperbarui karena Scheduler opsional |
| Events/report | Baru `PlaybackEventIngestion`, `PlaybackEventController`, `PlaybackReport`, `ProofOfPlayController`, view `admin/proof-of-play/index.blade.php`; middleware payload/rate limit, API/admin routes dan sidebar presentation |
| Migrasi/permission | Enam migration `2026_11_03_000001` sampai `000006`; `PlaybackPermissionSeeder` additive dan pemanggilan dari `DatabaseSeeder` |
| Player | Baru `src/services/playback/ledger.js`, `src/playback/device-engine.js`; runtime, adapter, renderer app, IPC/preload, protocol whitelist, manifest validator, API client dan sync callback; package/lock 2.0.0, schema-copy script, README/gitignore |
| Test | `tests/Feature/AlwaysOnPlaybackTest.php`, penyesuaian unsupported-version test v1; Studio JS regression; Player core/ledger tests, `always-on-localhost.spec.js`, fixture PHP bertag dan screenshot |
| Dependency/docs | `composer.json`/lock menambahkan OpenSpout saja; dokumen ini, catatan v2 pada Step 7, README Player dan screenshot Proof of Play |

## Validasi dan batas operasional

Baseline: PHP 169/175 (1071 assertion, enam kegagalan lama), JavaScript 49/49, Player 25/25. Checkpoint assignment, Scheduler, ledger, ingestion, dan XLSX diuji sebelum acceptance akhir.

| Validasi aktual | Hasil |
|---|---|
| PHP suite penuh akhir | **188 test, 182 lulus, 1156 assertion**; dua assertion gagal dan empat error sama dengan enam kegagalan auth/profile baseline |
| PHP fitur baru + regresi Device setelah audit riwayat | **41/41 lulus, 302 assertion**; termasuk 13 test AlwaysOnPlayback |
| JavaScript CMS | **50/50 lulus**, termasuk metadata, Preview, geometri, dan peringatan durasi Auto/manual tanpa durasi palsu |
| Windows Player unit/integration | **38/38 lulus**: 50/100 bertahan restart, queue/ack, crash tail/recovery/compaction, dua Window, manual/partial/failed/unknown Auto, suspend/UTC realignment, dan input session malformed |
| Electron E2E penuh | **3/3 lulus**, terdiri dari dua regresi Player existing dan satu acceptance Laravel nyata |
| Acceptance nyata berulang | Dua pengulangan terakhir sebelum E2E penuh: **2/2 lulus**, masing-masing menguji stop Laravel, dua restart offline, kuota 3/5, seluruh antrean tersinkron, tanpa duplikasi, applied dan XLSX |
| Ekspor besar | 10.001 event menjadi detail XLSX lengkap, tambahan peak memory di bawah 32 MiB; ZIP/XML Summary/Detail dan formula injection diperiksa |
| Build/sintaks | Vite production build, Blade compilation/lint, PHP lint, Composer autoload dan `git diff --check` dijalankan; Composer validate valid dengan warning pin dependency 4.32 yang disengaja |

Kegagalan PHP baseline yang tetap berada di luar fitur ini: navigation/profile mengharapkan `/dashboard` dan `/profile` yang 404; Authentication, EmailVerification, PasswordConfirmation, dan Registration mengacu route lama `dashboard` yang tidak terdaftar. Route CMS yang benar tetap `/admin/dashboard` dan `/admin/profile`. Suite penuh tidak dinyatakan semuanya lulus.

Acceptance nyata menggunakan Laravel localhost, MySQL lokal, dan Electron Windows unpackaged `--allow-localhost-http --smoke-test`, dengan Chrome terautentikasi untuk report/export. Acceptance final memakai software decoding H.264 untuk menghindari GPU offscreen yang sesekali macet; konfigurasi akselerasi produksi tidak diubah. Fixture H.264 dari Chromium menampilkan decoded frame dan image PNG berjalan paralel. Laravel dihentikan, Player ditutup/direstart offline, kuota tercapai tepat 3 video/5 image, Player direstart lagi, Laravel dihidupkan kembali, seluruh event disinkronkan tanpa duplikasi, applied dikonfirmasi, dan XLSX valid diunduh. Fixture/akun/perusahaan sementara dibersihkan berdasarkan tag acak, bukan reset data existing. [Screenshot laporan aktual](screenshots/proof-of-play.png).

Tidak ada klaim pengujian monitor fisik, pemadaman listrik, seluruh codec, atau Windows OS reboot sungguhan. Test crash mencakup jurnal terpotong, recovery session, dan compact file tertinggal. Laporan/counter server hanya mencerminkan event yang telah diterima; jumlah tertunda pada Device offline belum dapat dipastikan. Penulisan filesystem/antrean gagal menyebabkan pause, sehingga pengelola perlu menyediakan ruang disk. Server menyimpan histori/grant tanpa auto-retention; perencanaan kapasitas/arsip produksi perlu mempertimbangkan volume event. Batch invalid ditolak atomik dan tetap berada di Player untuk diagnosis, bukan dibuang diam-diam. Assignment dibatasi 1.000 Device per batch; perusahaan dengan lebih banyak Device perlu membagi seleksi. Tidak ada inferensi putaran historis sebelum ledger v2 diperkenalkan.

Beberapa pengulangan acceptance awal sempat mengalami initial-sync timeout lokal; harness final memakai port dinamis yang dimilikinya sendiri, membaca/menutup health-response, mencatat diagnostik path/status/ukuran tanpa credential, dan batas tunggu sync yang sesuai retry. Kegagalan awal tidak dihitung lulus. Dua pengulangan dan E2E penuh terakhir lulus, termasuk pengujian ulang setelah perbaikan compaction ledger; mekanisme staging tetap mempertahankan paket lengkap sebelumnya saat network/sync gagal.
