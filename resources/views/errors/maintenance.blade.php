<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Mode</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow text-center">
        <h1 class="text-2xl font-bold text-gray-900">
            Maintenance Mode
        </h1>

        <p class="mt-3 text-gray-600">
            The system is currently under maintenance. Please try again later.
        </p>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf

            <button type="submit"
                    class="px-5 py-2.5 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-700">
                Logout
            </button>
        </form>
    </div>

</body>
</html>