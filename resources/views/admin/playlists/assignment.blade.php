@extends('layouts.admin')
@section('content')
@livewire('playlists.playlist-assignment', ['playlistId' => $playlistId])
@endsection
