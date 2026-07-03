<tr {{ $attributes->merge([
    'class' => 'bg-white border-b hover:bg-gray-50'
]) }}>
    {{ $slot }}
</tr>