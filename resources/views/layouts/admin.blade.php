<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Digital Signage CMS') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 antialiased">

    @include('components.admin.topbar')

    @include('components.admin.sidebar')

    <main class="p-4 md:ml-64 pt-20 min-h-screen">
        @yield('content')
    </main>

    @include('components.admin.footer')

    @livewireScripts
</body>
</html>