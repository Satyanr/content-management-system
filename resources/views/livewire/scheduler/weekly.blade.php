<div class="overflow-hidden rounded-xl border border-slate-700 bg-slate-950 text-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
        <div><h2 class="font-semibold">Jadwal Mingguan</h2><p class="mt-1 text-xs text-slate-400">{{ $days[0]['date']->format('d M Y') }} - {{ $days[6]['date']->format('d M Y') }} / {{ $timezone }}</p></div>
        <div class="flex gap-2"><button wire:click="shiftWeek(-1)" class="rounded border border-slate-700 px-3 py-1.5 text-xs">Minggu sebelumnya</button><button wire:click="currentWeek" class="rounded border border-slate-700 px-3 py-1.5 text-xs">Minggu ini</button><button wire:click="shiftWeek(1)" class="rounded border border-slate-700 px-3 py-1.5 text-xs">Minggu berikutnya</button></div>
    </div>
    <div class="overflow-x-auto p-4 pt-0">
        <div class="grid min-w-[770px] grid-cols-7 gap-2">
            @foreach($days as $day)
                <section class="min-h-64 rounded-lg border border-slate-800 p-2">
                    <h3 class="mb-3 text-xs font-medium {{ $day['date']->isToday() ? 'text-cyan-300' : 'text-slate-300' }}">{{ $day['date']->format('D d M') }}</h3>
                    @forelse($day['blocks'] as $block)
                        @php $s = $block['schedule']; $colors = ['#244675','#526b54','#795e48','#63517d','#35696d','#75495a']; @endphp
                        <button type="button" wire:click="showDetails({{ $s->id }})" wire:key="block-{{ $day['date']->toDateString() }}-{{ $s->id }}-{{ $block['start']->timestamp }}" class="mb-2 block w-full rounded-lg border p-2 text-left text-xs {{ $block['overlap'] ? 'border-amber-400' : 'border-transparent' }} {{ $s->is_active && $s->playlist->is_active ? '' : 'opacity-50' }}" style="background-color: {{ $colors[$s->playlist_id % count($colors)] }}">
                            <span class="block font-semibold">{{ $block['continued'] ? 'Continued / ' : '' }}{{ $block['displayStart'] }} - {{ $block['displayEnd'] }}</span>
                            <span class="mt-1 block truncate">{{ $s->playlist->name }}</span><span class="block truncate text-[10px] text-slate-200">{{ $s->name }} / P{{ $s->priority }}</span>
                            @if(!$s->is_active || !$s->playlist->is_active)<span class="block text-[10px]">Inactive / no playback</span>@endif
                            @if($block['overlap'])<span class="mt-1 block text-[10px] text-amber-200">Overlap / priority applies</span>@endif
                        </button>
                    @empty <p class="text-[10px] text-slate-600">Belum ada jadwal pada tampilan ini</p>@endforelse
                </section>
            @endforeach
        </div>
    </div>
    <p class="px-4 pb-4 text-xs text-slate-400">Blocks use {{ $timezone }}; details retain each Schedule timezone. Muted blocks do not play. Borders mark active overlap, including competing schedules hidden by filters.</p>
</div>
