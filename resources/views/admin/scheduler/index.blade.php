@extends('layouts.admin')
@section('content')
    <x-cms.page-header title="Jadwal" subtitle="Tentukan kapan Playlist ditayangkan dan periksa jadwal yang bertumpuk." />
    <x-cms.workflow-tip title="Pilih Playlist yang sudah siap" description="Buat jadwal sekali tayang atau mingguan. Prioritas lebih tinggi diputar saat beberapa jadwal bertumpuk." />
    @livewire('scheduler.schedule-table')
@endsection
