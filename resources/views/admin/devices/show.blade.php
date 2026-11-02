@extends('layouts.admin')
@section('content')
    <x-cms.page-header title="Device Details" subtitle="Device identity, activation history and server heartbeat information." />
    @livewire('devices.device-details', ['publicId' => $publicId])
@endsection
