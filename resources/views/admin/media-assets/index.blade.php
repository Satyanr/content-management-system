@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Content Library</h1>
        <p class="text-gray-600">Manage uploaded media for signage content.</p>
    </div>

    @livewire('media.media-table')
@endsection