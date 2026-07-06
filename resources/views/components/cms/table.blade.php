<div class="relative overflow-x-auto">
    <table
        {{ $attributes->merge([
            'class' => 'w-full text-sm text-left text-gray-500 dark:text-gray-400',
        ]) }}>
        @isset($head)
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    {{ $head }}
                </tr>
            </thead>
        @endisset

        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
