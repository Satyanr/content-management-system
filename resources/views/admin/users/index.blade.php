@extends('layouts.admin')

@section('content')
    <x-cms.page-header title="Users" subtitle="Manage CMS users and access." />

    @livewire('users.user-table')
@endsection
