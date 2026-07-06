@extends('layouts.admin')

@section('content')
    <x-cms.page-header
        title="Activity Logs"
        subtitle="View system activities and user actions."
    />

    @livewire('activity-logs.activity-log-table')
@endsection