@if($conflicts)
    <div class="my-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
        <h3 class="font-semibold">Schedule conflicts detected</h3>
        <p class="mt-1 text-xs">Overlaps may be saved. Higher priority wins; equal priority uses the lowest Schedule ID. No competing Schedule is deleted.</p>
        @foreach($conflicts as $conflict)
            <p class="mt-2 text-xs"><strong>{{ $conflict['name'] }}</strong> / {{ $conflict['playlist'] }} / Priority {{ $conflict['priority'] }} vs this Schedule {{ $conflict['candidatePriority'] }}.<br>{{ $conflict['at'] }} {{ $conflict['timezone'] }} / {{ $conflict['candidateWins'] ? 'This Schedule wins this pair.' : 'The competing Schedule wins this pair.' }}</p>
        @endforeach
    </div>
@elseif($conflictReview)
    <p class="my-3 text-sm text-green-700">No active schedule conflicts found.</p>
@endif
