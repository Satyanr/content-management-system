@props(['title','description'])
<div class="mb-5 flex flex-col justify-between gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 sm:flex-row sm:items-center">
    <div><h2 class="text-sm font-semibold text-blue-950">{{ $title }}</h2><p class="mt-1 text-sm leading-relaxed text-blue-800">{{ $description }}</p></div>
    <button type="button" x-data @click="$dispatch('cms-guide-open')" class="shrink-0 rounded-lg bg-white px-3 py-2 text-xs font-semibold text-blue-700 shadow-sm" title="Buka panduan langkah demi langkah">? Panduan</button>
</div>
