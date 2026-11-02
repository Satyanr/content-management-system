<h2 class="mb-1 text-sm font-semibold">File Window</h2>
<p class="mb-4 text-xs text-slate-400">Windows on {{ $activePage?->name ?? 'the active page' }}.</p>
<div class="space-y-2">
    @forelse ($activePage?->zones ?? [] as $zone)
        <div wire:key="window-{{ $zone->id }}" class="rounded-lg border border-slate-700 bg-slate-800 p-3">
            <div class="flex items-center gap-2 text-sm"><x-studio-icon name="layers" /><span class="truncate">{{ $zone->name }}</span></div>
            <dl class="mt-2 space-y-1 text-xs text-slate-400">
                <div>Position: {{ $zone->x }}, {{ $zone->y }} px</div>
                <div>Size: {{ $zone->width }} &times; {{ $zone->height }} px</div>
                <div>Layer: {{ $zone->z_index }} / {{ $zone->fit_mode }} / {{ $zone->keep_aspect_ratio ? 'Keep ratio' : 'Free ratio' }}</div>
                <div>{{ $zone->is_visible ? 'Visible' : 'Hidden' }} / {{ $zone->is_locked ? 'Locked' : 'Unlocked' }}</div>
            </dl>
        </div>
    @empty <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-xs text-slate-400">No windows on this page.</div> @endforelse
</div>
