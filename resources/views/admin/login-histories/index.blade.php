@extends('layouts.admin')

@section('content')
    <x-cms.page-header
        title="Login History"
        description="Monitor user login and logout activity."
    />

    <livewire:login-histories.login-history-table />
@endsection