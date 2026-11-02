@if($conflicts)
    <div class="my-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
        <h3 class="font-semibold">Ada jadwal yang bertumpuk</h3>
        <p class="mt-1 text-xs">Jadwal bertumpuk boleh disimpan. Prioritas lebih tinggi didahulukan; saat sama, jadwal yang dibuat lebih awal dipilih.</p>
        @foreach($conflicts as $conflict)
            <p class="mt-2 text-xs"><strong>{{ $conflict['name'] }}</strong> / {{ $conflict['playlist'] }} / Prioritas {{ $conflict['priority'] }} dibanding jadwal ini {{ $conflict['candidatePriority'] }}.<br>{{ $conflict['at'] }} {{ $conflict['timezone'] }} / {{ $conflict['candidateWins'] ? 'Jadwal ini didahulukan.' : 'Jadwal lain didahulukan.' }}</p>
        @endforeach
    </div>
@elseif($conflictReview)
    <p class="my-3 text-sm text-green-700">Tidak ada jadwal aktif yang bertumpuk.</p>
@endif
