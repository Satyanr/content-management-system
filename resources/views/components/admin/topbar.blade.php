<header class="admin-topbar border-b border-slate-200 bg-white/95">
    <div class="flex min-h-20 flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <button x-ref="sidebarToggle" type="button" @click="toggleSidebar()" aria-label="Buka/tutup navigasi" aria-controls="admin-sidebar" :aria-expanded="mobileOpen || !sidebarCollapsed" title="Buka/tutup navigasi" class="rounded-xl p-3 text-slate-500 hover:bg-slate-100"><x-studio-icon name="menu" class="h-5 w-5"/></button>
            <nav aria-label="Breadcrumb" class="min-w-0 text-sm"><span class="hidden text-slate-400 sm:inline">CMS <span class="mx-2">/</span></span><span class="font-medium text-slate-800">{{ $currentPage }}</span></nav>
        </div>
        <div class="order-2 flex items-center gap-2">
            <button type="button" @click="$dispatch('cms-guide-open')" title="Buka panduan penggunaan" aria-label="Bantuan" class="rounded-xl p-3 text-slate-500 hover:bg-blue-50 hover:text-blue-700"><x-studio-icon name="help" class="h-5 w-5"/></button>
            <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape.stop="open = false; $refs.account.focus()">
                <button x-ref="account" type="button" @click="open = !open" :aria-expanded="open" aria-controls="account-menu" aria-label="Menu akun" class="flex min-h-11 items-center gap-2 rounded-xl p-1.5 hover:bg-slate-100"><span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-800">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="hidden max-w-32 truncate text-sm text-slate-700 xl:block">{{ auth()->user()->name }}</span><x-studio-icon name="chevron"/></button>
                <div id="account-menu" x-cloak x-show="open" class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                    <p class="truncate border-b border-slate-100 px-3 py-3 text-sm font-medium text-slate-700">{{ auth()->user()->name }}</p>
                    <a href="{{ route('admin.profile') }}" class="block rounded-lg px-3 py-3 text-sm text-slate-700 hover:bg-slate-50">Profil saya</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="w-full rounded-lg px-3 py-3 text-left text-sm text-red-700 hover:bg-red-50">Keluar</button></form>
                </div>
            </div>
        </div>
        <div class="order-3 w-full sm:order-1 sm:w-auto sm:flex-1 sm:pr-2"><x-admin.company-indicator/></div>
    </div>
</header>
