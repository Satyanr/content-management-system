@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600">Welcome to Digital Signage CMS</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="p-5 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="text-sm text-gray-500">Total Content</h3>
            <p class="mt-2 text-3xl font-bold">0</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="text-sm text-gray-500">Active Playlist</h3>
            <p class="mt-2 text-3xl font-bold">0</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="text-sm text-gray-500">Online Devices</h3>
            <p class="mt-2 text-3xl font-bold">0</p>
        </div>

        <div class="p-5 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="text-sm text-gray-500">Schedules</h3>
            <p class="mt-2 text-3xl font-bold">0</p>
        </div>
    </div>
@endsection