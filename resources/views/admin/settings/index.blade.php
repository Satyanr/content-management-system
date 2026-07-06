@extends('layouts.admin')

@section('content')
    <x-cms.page-header
        title="Settings"
        subtitle="Manage global and company settings."
    />

    @livewire('settings.setting-form')
@endsection