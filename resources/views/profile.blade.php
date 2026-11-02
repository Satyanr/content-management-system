@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div><h1 class="text-2xl font-semibold text-slate-900">Profil saya</h1><p class="mt-2 text-sm text-slate-500">Kelola informasi akun, kata sandi, dan keamanan Anda.</p></div>
    <section class="dashboard-card"><div class="max-w-xl"><livewire:profile.update-profile-information-form /></div></section>
    <section class="dashboard-card"><div class="max-w-xl"><livewire:profile.update-password-form /></div></section>
    <section class="dashboard-card"><div class="max-w-xl"><livewire:profile.delete-user-form /></div></section>
</div>
@endsection
