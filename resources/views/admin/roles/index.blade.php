@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Roles</h1>
        <p class="text-gray-600">Manage user roles and permissions.</p>
    </div>

    @livewire('roles.role-table')
@endsection