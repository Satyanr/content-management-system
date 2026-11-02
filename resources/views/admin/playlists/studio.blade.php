<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Playlist Studio - {{ $playlist->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-950 font-sans antialiased">
    @livewire('playlists.playlist-studio', ['playlistId' => $playlist->id])
    @livewireScripts
</body>
</html>
