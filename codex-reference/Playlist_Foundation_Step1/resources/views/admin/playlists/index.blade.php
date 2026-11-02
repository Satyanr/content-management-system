@extends('layouts.admin')

@section('content')
    <x-cms.page-header
        title="Playlist"
        subtitle="Create programs and configure the output canvas before opening Playlist Studio."
    />

    @livewire('playlists.playlist-table')
@endsection
