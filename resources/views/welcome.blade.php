<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kelola konten digital signage, perangkat, dan laporan dalam satu platform.">
    <title>CMS Digital Signage — Konten yang siap tampil</title>
    @vite(['resources/css/app.css'])
</head>
<body class="landing">
<header class="landing-nav"><div class="landing-wrap nav-inner">
    <a class="brand" href="#top" aria-label="CMS Digital Signage, kembali ke atas"><span class="brand-mark">▦</span><span>CMS <small>DIGITAL SIGNAGE</small></span></a>
    <nav aria-label="Navigasi utama"><a href="#features">Fitur</a><a href="#how">Cara Kerja</a><a href="#download">Download</a></nav>
    @auth <a class="nav-action" href="{{ route(auth()->user()->landingRoute()) }}">{{ auth()->user()->landingRoute() === 'account.pending' ? 'Status Akun' : 'Dashboard' }} <span>↗</span></a>
    @else <a class="nav-action" href="{{ route('login') }}">Login <span>↗</span></a> @endauth
</div></header>
<main id="top">
<section class="hero landing-wrap"><div class="hero-copy">
    <div class="eyebrow"><span class="status-dot"></span> PLATFORM DIGITAL SIGNAGE</div>
    <h1>Konten yang siap tampil, <em>di setiap layar.</em></h1>
    <p class="hero-title">Digital Signage Management Platform</p>
    <p class="lead">Kelola media, susun playlist, dan kirim pengalaman visual ke perangkat Windows Anda dari satu tempat.</p>
    <div class="actions"><a class="button primary" href="#download">Dapatkan Windows Player <span>↗</span></a><a class="button secondary" href="#how">Pelajari cara kerja <span>↓</span></a></div>
    <div class="hero-note"><span>✦</span> Dari konten hingga laporan, lebih terorganisir.</div>
</div>
<div class="studio" aria-label="Ilustrasi antarmuka Playlist Studio, bukan tangkapan layar aplikasi">
    <div class="studio-top"><div><span class="studio-logo">▦</span> Playlist Studio <span class="studio-muted"> / Kampanye utama</span></div><span class="studio-pill">● &nbsp; Editor</span></div>
    <div class="studio-body"><div class="studio-side"><div class="studio-label">WORKSPACE</div><div class="studio-selected">▣ &nbsp; Canvas</div><div>▤ &nbsp; Media</div><div>◷ &nbsp; Timeline</div><div class="studio-label space">WINDOWS</div><div class="studio-window">▦ &nbsp; Window 01 <span>•••</span></div><div class="studio-window">▦ &nbsp; Window 02 <span>•••</span></div><div class="studio-add">+ Tambah window</div></div>
    <div class="studio-main"><div class="studio-tools"><span>Canvas <b>1920 × 1080</b></span><span>− &nbsp; 75% &nbsp; +</span></div><div class="canvas"><div class="screen-main"><div class="screen-tag">SELAMAT DATANG</div><strong>Ideas in<br>motion<span>.</span></strong><div class="screen-line"></div><small>YOUR MESSAGE, BEAUTIFULLY DISPLAYED</small></div><div class="screen-side"><div class="orb">✳</div><small>INFORMASI<br>HARI INI</small><strong>Make it<br>visible.</strong></div><div class="screen-ticker">● &nbsp; INFORMASI TERBARU &nbsp; / &nbsp; KONTEN UNTUK SETIAP LAYAR</div></div><div class="studio-bottom"><span>▣ &nbsp; Preview layout</span><span>● &nbsp; 2 windows</span></div></div></div>
    <div class="studio-caption">ILUSTRASI PLAYLIST STUDIO <span>✳</span></div>
</div></section>
<section class="feature-section" id="features"><div class="landing-wrap"><div class="section-head"><div><div class="eyebrow">01 / KEMAMPUAN PLATFORM</div><h2>Semua yang Anda butuhkan<br><em>untuk mengelola layar.</em></h2></div><p>Alur kerja yang menyatukan konten, perangkat, dan data pemutaran dalam satu platform.</p></div>
<div class="feature-grid">
@foreach ([['▦','Playlist Studio & Multi-Window','Rancang tata letak dan susun beberapa window dalam satu playlist.'],['◉','Device Management','Kelola dan aktifkan perangkat dalam workspace perusahaan.'],['↗','Playlist Assignment','Tentukan playlist untuk perangkat yang tepat.'],['◷','Smart Scheduler','Atur jadwal penayangan sesuai kebutuhan operasional.'],['↻','Offline Playback','Konten yang telah tersimpan dapat terus diputar saat koneksi terputus sementara.'],['▤','Proof of Play & Excel Reporting','Tinjau peristiwa pemutaran dan ekspor laporan ke Excel.'],['◇','Multi-Company Isolation','Pisahkan workspace dan akses data antar perusahaan.']] as [$icon, $title, $description])
<article class="feature-card"><span class="feature-icon">{{ $icon }}</span><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
@endforeach
<article class="feature-card feature-callout"><span>SIAP UNTUK MEMULAI?</span><p>Wujudkan komunikasi visual yang lebih terarah.</p><a href="#download">Kenali Windows Player ↗</a></article>
</div></div></section>
<section class="how landing-wrap" id="how"><div class="section-head"><div><div class="eyebrow">02 / CARA KERJA</div><h2>Ide Anda, <em>hingga ke layar.</em></h2></div><p>Lima langkah sederhana untuk mengelola perjalanan konten Anda.</p></div><div class="steps">
@foreach ([['01','Upload','Unggah gambar dan video ke pustaka media.'],['02','Design','Susun konten dan window di Playlist Studio.'],['03','Assign','Hubungkan playlist dengan perangkat.'],['04','Play','Player mengunduh dan memutar konten.'],['05','Monitor Reports','Tinjau aktivitas dan ekspor laporan.']] as [$number,$title,$description])
<div class="step"><span>{{ $number }}</span><h3>{{ $title }}</h3><p>{{ $description }}</p></div>
@endforeach
</div></section>
<section class="download-section" id="download"><div class="landing-wrap download-grid"><div><div class="eyebrow">03 / WINDOWS PLAYER</div><h2>Siapkan layar Anda<br><em>untuk mulai bercerita.</em></h2><p class="lead">Instal CMS Signage Player pada perangkat Windows, lalu hubungkan ke CMS menggunakan kode aktivasi.</p>
@if ($downloadUrl)<a class="button primary" href="{{ $downloadUrl }}">Download Windows Player <span>↗</span></a>@else<span class="button unavailable" aria-disabled="true">Download belum tersedia</span><p class="download-hint">Installer akan tersedia setelah tautan unduhan terverifikasi dikonfigurasi.</p>@endif
<div class="download-meta"><span>VERSI <strong>2.0.0</strong></span><span>ARSITEKTUR <strong>x64</strong></span><span>SISTEM <strong>Windows 10 / 11</strong></span></div><p class="hash">SHA-256 <code>5A9A5F8947200828E72841F747FD25616B5163A34CB7FECFFC2E1756F041A45D</code></p></div>
<div class="setup-card"><div class="setup-head"><span>↗</span><div><small>PANDUAN SINGKAT</small><h3>Mulai dalam 3 langkah</h3></div></div><ol><li><b>Instal Player</b><p>Unduh installer terverifikasi dan jalankan pada Windows 10 atau 11 x64.</p></li><li><b>Siapkan perangkat</b><p>Buat perangkat dan kode aktivasi di workspace CMS Anda.</p></li><li><b>Hubungkan & putar</b><p>Masukkan alamat HTTPS CMS dan kode aktivasi di Player, lalu tetapkan playlist.</p></li></ol><div class="setup-foot">Koneksi internet diperlukan untuk aktivasi dan sinkronisasi.</div></div></div></section>
<section class="faq landing-wrap"><div class="section-head"><div><div class="eyebrow">04 / PERTANYAAN UMUM</div><h2>Perlu tahu lebih lanjut?</h2></div></div><div class="faq-list"><details><summary>Apakah Player tetap berjalan saat koneksi terputus?</summary><p>Player dapat memutar konten yang sudah diunduh. Aktivasi, pembaruan konten, dan pengiriman laporan memerlukan koneksi kembali.</p></details><details><summary>Bagaimana cara menghubungkan perangkat?</summary><p>Buat perangkat dan kode aktivasi di CMS, kemudian masukkan kode tersebut bersama alamat HTTPS CMS pada Player.</p></details><details><summary>Apakah beberapa perusahaan dapat menggunakan CMS?</summary><p>Ya. Workspace dan akses data dipisahkan berdasarkan perusahaan serta izin akun.</p></details><details><summary>Di mana saya melihat laporan pemutaran?</summary><p>Buka Proof of Play di CMS dengan akun yang memiliki izin, lalu ekspor laporan ke Excel jika diperlukan.</p></details></div></section>
</main><footer><div class="landing-wrap footer-inner"><a class="brand" href="#top"><span class="brand-mark">▦</span><span>CMS <small>DIGITAL SIGNAGE</small></span></a><p>Kelola konten. Hubungkan layar. Sampaikan pesan.</p><span>© {{ date('Y') }} CMS Digital Signage</span></div></footer>
</body></html>
