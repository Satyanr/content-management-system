@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Permissions</h1>
        <p class="text-gray-600">Manage system permissions.</p>
    </div>

    @livewire('permissions.permission-table')
@endsection