# Hotfix Playlist Studio — metadata loading

## Penyebab yang dibuktikan

Chrome headless mengakses Laravel local server dengan akun/company/Playlist/media fixture terisolasi melalui login normal. Sebelum perbaikan:

- Console: `Illegal invocation`.
- Setelah 10 detik metadata UI masih `[]`, Auto Window `loading`, clock berjalan.
- Window manual 4 detik berhasil memutar video yang sama (292 decoded frames).
- Endpoint video mengembalikan 206, MIME video/mp4, Content-Range/bytes benar. Company lain ditolak 404.

`MetadataPool` menyimpan native `setTimeout` dan `clearTimeout` sebagai property, kemudian memanggil `this.setTimer(...)`/`this.clearTimer(...)`. Chrome menolak native Web API dengan receiver objek MetadataPool. Exception terjadi sebelum timer, event listeners dan source disiapkan, sehingga batas 8 detik juga tidak pernah aktif. Mocks Node sebelumnya tidak meniru pembatasan receiver native browser.

Upload private dan saved manifest tidak menjadi penyebab kasus yang direproduksi. Metadata server memang nullable; Auto video boleh memperoleh durasi asli dari HTMLVideoElement. Tidak ditambahkan durasi buatan atau URL publik.

## Perubahan

| File | Perubahan |
| --- | --- |
| resources/js/studio-playback-renderer.js | Wrapper timer dengan receiver aman; cleanup/late-event guards; stalled/waiting diagnostics tanpa memperpanjang timeout; error jaringan/source/decoder lebih spesifik; timeout frame dan metadata tetap terbatas |
| resources/js/studio-playback.js | Known-duration items tetap berjalan jika Auto lain belum diketahui; Window hanya loading jika tidak ada item yang dapat dijadwalkan; error membawa pesan; track tetap menampilkan bagian yang sudah diketahui |
| resources/js/studio-timeline.js | Metadata reset saat reload, callback terikat engine yang benar, error renderer diteruskan, duration label jelas, logging development opt-in |
| resources/views/livewire/playlists/playlist-studio.blade.php | Flag diagnostics hanya local/testing |
| app/Http/Controllers/Admin/MediaStreamController.php | HEAD terautorisasi mengembalikan header tanpa membaca/streaming seluruh file; GET/Range tetap existing |
| tests/js/studio-playback.test.mjs | Empat regression tests baru, update ekspektasi known item pada Window dengan Auto pending |
| tests/Feature/Playlists/PlaylistPlaybackTest.php | Regression private local stream, MIME, bytes/Range, HEAD, permission dan Auto null duration |
| tests/Browser/preview-fixture.php | Fixture lokal dengan tag acak; cleanup hanya data/files/accounts fixture tersebut |
| tests/Browser/preview-smoke.mjs | Chrome nyata: valid H264 Auto/manual concurrent Windows, decoded frames, 200/206 byte integrity, company isolation, corrupt container dan timeout 8 detik |

PlaylistPlayback, MediaUploadController dan MediaAsset telah diaudit, tidak perlu diubah. Authorization/workspace/private storage tetap aktif. Tidak ada perubahan Scheduler, Device Management, Player API atau installer. Windows Player unit tests ikut dijalankan karena memakai engine bersama; installer tidak dibangun ulang. Tidak ada commit/push atau database reset.

## Hasil aktual

- Browser Chrome + Laravel server: durasi asli **1.068118 detik**, kedua Window ready, **286/294 decoded frames**, readyState 4, paused false, tanpa pageerror.
- Manual duration tetap **4 detik**, Auto memakai durasi asli; clock/Page/Window looping tetap berjalan.
- GET 200 sama byte-for-byte dengan fixture; Range 206 mengembalikan 64 bytes sesuai permintaan; Content-Type video/mp4; foreign company 404.
- Corrupt MP4: `Media source/codec is unsupported, or the response is not a playable video. Try MP4 H.264/AAC.`
- Stream ditahan melalui interception browser: `Metadata timed out after 8s...`, Window error dan tidak lagi loading. Request normal sebelumnya telah diuji benar-benar melalui controller Laravel, bukan mock streaming.
- JavaScript CMS: **42/42 lulus**.
- Full PHP: **157/163 lulus, 987 assertions**. Enam kegagalan lama tetap sama: Authentication login/EmailVerification/PasswordConfirmation/Registration merujuk route dashboard yang tidak ada; Authentication navigation `/dashboard` dan Profile `/profile` 404.
- Focused PHP Playback suite: **12/12 lulus, 117 assertions**.
- Shared Windows Player unit/integration: **24/24 lulus**.
- Vite build, Blade compilation, PHP lint, JS syntax dan git diff --check lulus.

Fixture H264 fallback menggunakan [bear.mp4 Chromium](https://github.com/chromium/chromium/blob/main/media/test/data/bear.mp4), dengan provenance di [Chromium media test documentation](https://github.com/chromium/chromium/blob/main/media/test/data/README.md). Tidak menambahkan video tersebut ke Git atau runtime dependency FFmpeg. Attempt encoder MediaRecorder H264 pada Chrome lokal menghasilkan file kosong, sehingga pengujian menggunakan fixture repository Chromium tersebut. Video pengguna yang bermasalah belum diberikan dan belum diperiksa codec/container-nya secara individual.

## Verifikasi Chrome DevTools

1. Jalankan `npm.cmd run build` dari root CMS dan hard reload Studio (Ctrl+F5) agar asset JS terbaru terpakai.
2. Aktifkan diagnostic pada origin CMS lokal melalui Console:

```javascript
localStorage.setItem('cms.playback.debug', '1');
location.reload();
```

3. Buka Playback Preview, tekan Play. Console `[Studio media]` menampilkan event, item ID, HTTP status/MIME/length/ranges (probe HEAD hanya saat diagnostics aktif), readyState/networkState, errorCode, duration dan status source assigned/unassigned. Tidak ada cookie, Authorization, credential, URL mentah atau private filesystem path yang dicatat. Diagnostics dinonaktifkan di luar environment local/testing.
4. Di Network filter `/stream`: video harus berisi bytes asli dan MIME video/mp4; GET Range mendapat 206 serta Content-Range, normal GET 200. Redirect login/403/404 berarti masalah izin/workspace/session/source, bukan alasan untuk membuat media publik.
5. Pilih elemen `<video>` dalam preview melalui Elements, lalu Console:

```javascript
({ duration: $0.duration, readyState: $0.readyState,
   networkState: $0.networkState, errorCode: $0.error?.code,
   width: $0.videoWidth, time: $0.currentTime,
   frames: $0.getVideoPlaybackQuality?.().totalVideoFrames });
```

Durasi valid harus finite/positif, videoWidth >0 dan decoded frames bertambah saat Play. Auto label menampilkan durasi nyata; file gagal menampilkan pesan spesifik. Mode diagnostik tidak mengunduh konten setiap frame.

Matikan kembali:

```javascript
localStorage.removeItem('cms.playback.debug');
location.reload();
```

## Konversi dan batasan

Perbaikan ini tidak memerlukan konversi untuk fixture H264 yang valid. Tidak ada alasan untuk mengonversi semua video sebagai solusi bug timer. Untuk video tertentu yang tetap mengalami decode/source error, periksa codec/container secara terpisah. MP4 adalah container, bukan jaminan codec kompatibel; H264 dengan pixel format yuv420p dan AAC merupakan target interoperabilitas umum. Metadata moov/container yang rusak juga dapat membuat browser gagal memperoleh duration.

Jika operator sudah memiliki FFmpeg/ffprobe, pemeriksaan/konversi opsional pada salinan file:

```text
ffprobe -v error -show_entries stream=codec_name,codec_type,pix_fmt:format=duration -of json input.mp4
ffmpeg -i input.mp4 -c:v libx264 -pix_fmt yuv420p -c:a aac -movflags +faststart output-h264.mp4
```

Jangan menimpa file asli. Faststart membantu akses metadata awal, tetapi bukan perbaikan untuk semua file korup. Tidak memasang atau mewajibkan ffprobe server-side karena akar masalah telah dibuktikan di client. Video pengguna tertentu, audio/hardware codec lintas komputer dan jaringan sangat lambat tetap memerlukan pemeriksaan masing-masing; 8 detik adalah batas metadata yang dipertahankan, dengan pesan timeout yang kini berfungsi.

## Reproduce automated tests

```powershell
node --test tests/js/*.test.mjs
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest
npm.cmd run build
php artisan view:cache
php artisan view:clear
php artisan serve --host=127.0.0.1 --port=8147 --no-reload
# Terminal kedua, root repository, Chrome terpasang dan dependencies players/windows tersedia:
node tests/Browser/preview-smoke.mjs
```

Browser helper dibatasi APP_ENV local/testing, memerlukan permission existing dari seed CMS, membuat fixture dengan tag acak, lalu membersihkan fixture sendiri dalam finally. Tidak menggunakan migrate:fresh/db:wipe dan tidak menyentuh media existing. PREVIEW_BASE_URL dapat menunjuk server test lokal yang berbeda. Jika proses dihentikan paksa, fixture yang tertinggal memakai company code `PHF-<tag>`/`PHF-<tag>B`; jangan menghapus data lain saat membersihkan.
