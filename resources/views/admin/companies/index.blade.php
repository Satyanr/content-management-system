@extends('layouts.admin')

@section('content')
    <x-cms.page-header
        title="Companies"
        subtitle="Manage company tenants."
    />

    @livewire('companies.company-table')
@endsection