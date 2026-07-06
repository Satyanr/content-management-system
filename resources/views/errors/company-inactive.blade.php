<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Company Inactive</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center px-4">
    <div class="max-w-md w-full rounded-2xl bg-white dark:bg-gray-800 shadow p-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
            !
        </div>

        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Company Inactive
        </h1>

        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            Your company account is currently inactive. Please contact the system administrator.
        </p>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf

            <button
                type="submit"
                class="w-full rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900"
            >
                Logout
            </button>
        </form>
    </div>
</body>
</html>