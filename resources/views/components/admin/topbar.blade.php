<nav class="fixed top-0 z-50 w-full bg-white border-b border-gray-200">
    <div class="h-16 px-4 lg:px-5 lg:pl-3">
        <div class="flex h-full items-center justify-between">

            <div class="flex items-center">
                <button data-drawer-target="admin-sidebar" data-drawer-toggle="admin-sidebar" aria-controls="admin-sidebar"
                    type="button"
                    class="inline-flex items-center p-2 text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100">
                    <span class="sr-only">Open sidebar</span>
                    ☰
                </button>

                <a href="{{ route('admin.dashboard') }}" class="flex ms-2 md:me-24">
                    <span class="self-center text-xl font-semibold whitespace-nowrap">
                        {{ $cmsAppName ?? config('app.name', 'Digital Signage CMS') }}
                    </span>
                </a>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" x-data @click="$dispatch('cms-guide-open')" title="Buka panduan penggunaan" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">? <span class="hidden sm:inline">Bantuan</span></button>
                <x-admin.company-indicator />

                <span class="hidden sm:block text-sm text-gray-600">
                    {{ auth()->user()->name }}
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit"
                        class="text-sm px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-700">
                        Keluar
                    </button>
                </form>
            </div>

        </div>
    </div>
</nav>
