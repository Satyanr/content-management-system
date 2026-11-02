@if (!$activePage || $activePage->zones->where('is_visible', true)->isEmpty())
    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400">
        <x-studio-icon name="monitor" class="h-6 w-6" />
        <span class="text-xs">{{ $activePage ? 'Empty canvas' : 'Select or add a page' }}</span>
    </div>
@endif
