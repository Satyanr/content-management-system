@php
    $settingCompanyId = auth()->user()?->hasRole('super-admin') ? null : auth()->user()?->company_id;

    $cmsAppName = app(\App\Services\SettingService::class)->get(
        key: 'app_name',
        default: config('app.name', 'Digital Signage CMS'),
        companyId: $settingCompanyId,
    );
    $navGroups = app(\App\Services\SidebarMenuService::class)->groups();
    $findActive = function ($items) use (&$findActive) {
        foreach ($items as $item) { if ($item['active']) return $findActive($item['children']) ?? $item['label']; }
        return null;
    };
    $currentPage = $findActive(array_merge(...array_values($navGroups))) ?? (request()->routeIs('admin.profile') ? 'Profil saya' : 'CMS');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $cmsAppName }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body x-data="adminNavigation" data-user-id="{{ auth()->id() }}" :class="{ 'is-collapsed': sidebarCollapsed }" @keydown.escape.window="if (mobileOpen) closeDrawer()" class="admin-shell bg-slate-50 text-slate-800 antialiased">

    @include('components.admin.topbar')

    @include('components.admin.sidebar')

    <main id="main-content" class="admin-main min-w-0 p-4 sm:p-6 lg:p-8">
        @yield('content')
    </main>

    @include('components.admin.footer')
    <x-cms.welcome-guide />

    @livewireScriptConfig
</body>

</html>
