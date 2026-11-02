<div x-cloak x-show="mobileOpen" @click="closeDrawer()" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" aria-hidden="true"></div>
<aside id="admin-sidebar" x-ref="sidebar" :class="{ 'drawer-open': mobileOpen }" :role="mobileOpen ? 'dialog' : null" :aria-modal="mobileOpen ? 'true' : null" @keydown.tab="if (mobileOpen) trapDrawer($event)" class="admin-sidebar" aria-label="Navigasi utama">
    <div class="flex h-20 shrink-0 items-center gap-3 border-b border-slate-700/70 px-5">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-400"><x-studio-icon name="monitor" class="h-6 w-6"/></span>
        <span class="nav-label truncate text-sm font-semibold text-white" title="{{ $cmsAppName }}">{{ $cmsAppName }}</span>
        <button type="button" @click="closeDrawer()" aria-label="Tutup navigasi" class="ml-auto rounded-lg p-3 text-slate-300 lg:hidden"><x-studio-icon name="close"/></button>
    </div>
    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-5">
        <ul class="space-y-1">@foreach ($navGroups['dashboard'] as $item)<x-admin.nav-item :item="$item"/>@endforeach</ul>
        @if (count($navGroups['operational']))
            <p class="nav-label mb-3 mt-7 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Operasional</p>
            <ul class="mt-3 space-y-1">@foreach ($navGroups['operational'] as $item)<x-admin.nav-item :item="$item"/>@endforeach</ul>
        @endif
        @if (count($navGroups['administration']))
            <div x-data="{ expanded: {{ collect($navGroups['administration'])->contains('active', true) ? 'true' : 'false' }} }" class="mt-6 border-t border-slate-700 pt-4">
                <button type="button" @click="expanded = !expanded" :aria-expanded="expanded || compact" title="Administrasi" class="flex min-h-11 w-full items-center justify-between rounded-lg px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400 hover:bg-slate-800 hover:text-white"><span class="nav-label">Administrasi</span><x-studio-icon name="settings" class="h-5 w-5"/><span class="sr-only">Buka/tutup Administrasi</span></button>
                <ul x-cloak x-show="expanded || compact" class="mt-2 space-y-1">@foreach ($navGroups['administration'] as $item)<x-admin.nav-item :item="$item"/>@endforeach</ul>
            </div>
        @endif
        @if (count($navGroups['custom']))<div class="mt-6 border-t border-slate-700 pt-4"><p class="nav-label mb-3 px-3 text-[11px] uppercase tracking-widest text-slate-400">Menu lainnya</p><ul class="space-y-1">@foreach ($navGroups['custom'] as $item)<x-admin.nav-item :item="$item"/>@endforeach</ul></div>@endif
    </nav>
    <div class="nav-label border-t border-slate-700 p-5 text-xs leading-relaxed text-slate-400">Konten, jadwal, dan layar dalam satu workspace.</div>
</aside>
