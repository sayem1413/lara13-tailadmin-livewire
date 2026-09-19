@props(['changes'])

@php
    $old = collect($changes['old'] ?? []);
    $new = collect($changes['attributes'] ?? []);
    $fields = $new->keys()->merge($old->keys())->unique()->values();
    $isUpdate = $old->isNotEmpty();

    $format = function (mixed $value) {
        return match (true) {
            is_null($value) => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => implode(', ', $value),
            default => (string) $value,
        };
    };
@endphp

@if ($fields->isNotEmpty())
    <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-white/10">
        <table class="w-full text-left text-xs">
            <thead class="bg-gray-50 text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2 font-medium">Field</th>
                    @if ($isUpdate)
                        <th class="px-3 py-2 font-medium">Before</th>
                    @endif
                    <th class="px-3 py-2 font-medium">{{ $isUpdate ? 'After' : 'Value' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($fields as $field)
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ Str::headline($field) }}</td>
                        @if ($isUpdate)
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $format($old->get($field)) }}</td>
                        @endif
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $format($new->get($field)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
