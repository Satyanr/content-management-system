@props([
    'colspan' => 1,
    'message' => 'No data found.',
])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-6 text-center text-gray-500">
        {{ $message }}
    </td>
</tr>