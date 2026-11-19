# Fondasi Android Player

Dokumen ini adalah rancangan awal, bukan implementasi atau bukti kompatibilitas Android. Windows Player tetap menjadi client referensi; backend dan schema tidak diubah untuk rancangan ini.

## Kontrak yang harus dipertahankan

- Gunakan [Player API v2](player-api-v2.schema.json) dan perilaku [always-on, quota, dan Proof of Play](always-on-playlist-proof-of-play.md). Aktivasi, heartbeat, manifest, media, playback-state dan playback-events tetap menggunakan endpoint Device yang ada. Credential tidak boleh ditampilkan di UI, log atau laporan.
- Validasi manifest dan referensi media, unduh media ke cache privat, cek ukuran dan SHA-256 sebelum promosi paket atomik; paket lama yang lengkap tetap dipakai ketika sync gagal. Saat offline, jalankan fallback sesuai kontrak dan simpan counter serta antrean event di luar pembersihan cache.
- Identitas counter harus mencakup Device, item dan epoch. Catat completed hanya setelah durasi aktif benar-benar tercapai; gagal, terpotong dan proses mati tidak boleh menambah completed. Simpan event secara tahan crash dan kirim ulang secara idempotent setelah reconnect.
- Implementasikan Page dan Window paralel, urutan item, durasi Auto/Manual, fit dan transform dengan hasil visual yang dibandingkan terhadap Studio dan Windows Player. Deteksi media gagal atau macet dengan batas yang dapat diuji tanpa menghambat Window lain.
- Gunakan HTTPS dengan validasi sertifikat. Simpan credential pada penyimpanan privat yang dilindungi perangkat; jangan membawa mode HTTP localhost development Windows ke aplikasi Android produksi.

## Persiapan implementasi

1. Tentukan perangkat dan versi Android target, resolusi/orientasi, dukungan codec serta kebijakan kiosk dan startup bersama pemilik perangkat sebelum memilih teknologi implementasi.
2. Buat aplikasi Android terpisah yang menggunakan kontrak di atas; jangan berbagi credential, cache atau jurnal dengan Windows Player. Definisikan batas modul untuk API, penyimpanan, clock/jadwal, playback, ledger dan renderer.
3. Gunakan fixture kontrak yang sama untuk validasi schema v1/v2 yang relevan, checksum, range/retry, sinkronisasi atomik, revocation, timeout dan identitas tenant. Penambahan kemampuan platform tidak boleh mengubah schema v2 tanpa uji kompatibilitas.
4. Buat pengujian terisolasi untuk offline, restart proses dan OS, counter/epoch, antrean event dan duplikasi, lalu uji perangkat nyata untuk decoder, kiosk, storage penuh, pergantian jaringan, power-loss dan operasi jangka panjang.
5. Bedakan laporan software Player dari bukti hardware independen. Catat versi aplikasi, perangkat, durasi test dan batas bukti; jangan memakai hasil Windows sebagai bukti Android.

Keputusan teknologi, aplikasi Android, pengujian perangkat dan release Android masih **PENDING**. Status Windows RC dan hasil aktual berada di [windows-player-release-candidate.md](windows-player-release-candidate.md).
