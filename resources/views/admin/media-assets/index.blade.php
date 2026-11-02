@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Pustaka Media</h1>
        <p class="text-gray-600">Simpan dan kelola gambar serta video untuk tayangan Anda.</p>
    </div>

    <x-cms.workflow-tip title="Unggah media, lalu gunakan di Studio" description="Pilih tab Unggah untuk menambah file. Gambar dan video dapat dipakai berulang dalam beberapa Playlist tanpa mengubah file asli." />
    @livewire('media.media-table')
@endsection
