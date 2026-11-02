@extends('layouts.admin')
@section('content')
    <x-cms.page-header title="Perangkat" subtitle="Daftarkan dan aktifkan layar pemutar dalam workspace perusahaan Anda." />
    <x-cms.workflow-tip title="Hubungkan layar dengan kode aktivasi" description="Tambah Perangkat, buat kode aktivasi, lalu masukkan kode pada aplikasi Player. Pilih workspace terlebih dahulu." />
    @livewire('devices.device-table')
@endsection
