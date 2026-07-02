@if (session()->has('success'))
    <div class="mb-4 p-4 text-sm text-green-800 rounded-lg bg-green-50">
        {{ session('success') }}
    </div>
@endif

@if (session()->has('error'))
    <div class="mb-4 p-4 text-sm text-red-800 rounded-lg bg-red-50">
        {{ session('error') }}
    </div>
@endif