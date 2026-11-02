<div x-data="cmsGuide" data-user-id="{{ auth()->id() }}" @cms-guide-open.window="show()">
    <div x-cloak x-show="open" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/70 p-4" @keydown.escape.stop.prevent="close()" @keydown.tab="trap($event)">
        <section x-ref="dialog" role="dialog" aria-modal="true" aria-labelledby="cms-guide-title" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 shadow-2xl">
            <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-widest text-blue-600">Panduan singkat</p><h2 id="cms-guide-title" class="mt-2 text-2xl font-semibold">Dari media ke layar, selangkah demi selangkah</h2></div><button type="button" @click="close()" aria-label="Tutup panduan" class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">✕</button></div>
            <ol class="my-6 grid gap-3 sm:grid-cols-2">
                @foreach (['Unggah media' => 'Buka Pustaka Media, pilih Unggah, lalu tambahkan gambar atau video.', 'Buat Playlist' => 'Tentukan nama dan resolusi layar. Setiap Playlist dimulai dengan Page 1.', 'Susun di Studio' => 'Pilih Page, tarik media ke kanvas, lalu atur Window. Window berbeda tampil bersamaan; item di Window yang sama diputar berurutan.', 'Atur isi media' => 'Pilih Window dan item medianya. Zoom/pan mengubah isi gambar, sedangkan W/H mengubah kotak Window.', 'Simpan dan pratinjau' => 'Simpan perubahan, lalu buka Pratinjau untuk memeriksa urutan dan durasi.', 'Jadwalkan dan aktifkan perangkat' => 'Buat Jadwal untuk Playlist, lalu daftarkan dan aktifkan Perangkat di workspace perusahaan yang benar.'] as $title => $tip)
                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-4"><h3 class="text-sm font-semibold"><span class="mr-2 text-blue-600">{{ $loop->iteration }}.</span>{{ $title }}</h3><p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $tip }}</p></li>
                @endforeach
            </ol>
            <label class="mb-5 flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" x-model="never" class="rounded border-slate-300"> Jangan tampilkan lagi untuk akun ini</label>
            <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-xs text-slate-500">Buka kembali kapan saja melalui tombol Bantuan / ?.</p><button x-ref="start" type="button" @click="finish()" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700">Mulai Menggunakan</button></div>
        </section>
    </div>
</div>
