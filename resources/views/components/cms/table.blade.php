<div class="relative overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
    <table {{ $attributes->merge([
        'class' => 'w-full text-sm text-left text-gray-500'
    ]) }}>
        {{ $slot }}
    </table>
</div>