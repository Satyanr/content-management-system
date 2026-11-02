@extends('layouts.admin')
@section('content')
    <x-cms.page-header title="Scheduler" subtitle="Schedule Playlist playback, inspect conflicts and preview the next seven days." />
    @livewire('scheduler.schedule-table')
@endsection
