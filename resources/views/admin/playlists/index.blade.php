@extends('layouts.admin')

@section('content')
    <x-cms.page-header
        title="Playlist"
        subtitle="Susun media menjadi tayangan dengan resolusi layar yang sesuai."
    />

    <x-cms.workflow-tip title="Mulai dari Playlist baru" description="Buat Playlist, buka Studio, lalu tambahkan beberapa Window untuk media yang tampil bersamaan." />
    @livewire('playlists.playlist-table')
@endsection
