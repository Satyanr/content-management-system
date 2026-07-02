@extends('layouts.admin')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Menu Management</h1>
        <p class="text-gray-600">Manage sidebar menu structure.</p>
    </div>

    @livewire('menus.menu-table')
@endsection