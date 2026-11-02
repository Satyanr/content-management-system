@extends('layouts.admin')
@section('content')
    <x-cms.page-header title="Devices" subtitle="Provision, activate and monitor Devices in the current company workspace." />
    @livewire('devices.device-table')
@endsection
