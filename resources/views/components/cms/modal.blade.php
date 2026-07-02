@props([
    'show' => false,
    'title' => '',
    'maxWidth' => 'max-w-lg',
])

@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
        <div class="w-full {{ $maxWidth }} bg-white rounded-lg shadow">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ $title }}
                </h3>

                {{ $close ?? '' }}
            </div>

            {{ $slot }}
        </div>
    </div>
@endif