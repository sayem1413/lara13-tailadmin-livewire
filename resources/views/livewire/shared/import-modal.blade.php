<div>
    <x-ui.button type="button" variant="secondary" wire:click="open">{{ $title }}</x-ui.button>

    <x-ui.modal wire-model="show" :title="$title" max-width="lg">
        <div class="mb-4 flex items-center justify-between gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Download the template, fill it in, then upload it below.</p>
            <x-ui.button type="button" variant="secondary" wire:click="downloadTemplate">Download Template</x-ui.button>
        </div>

        <x-forms.file-upload wire:model="file" accept=".xlsx,.xls,.csv" :max-size-mb="5" />
        <x-forms.error for="file" />

        @if ($importedCount !== null)
            <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
                Imported {{ $importedCount }} record(s){{ $failures ? ' - see the errors below.' : '.' }}
            </div>
        @endif

        @if ($failures)
            <div class="mt-4 max-h-48 overflow-y-auto rounded-lg border border-red-100 dark:border-red-500/20">
                <table class="w-full text-left text-xs">
                    <thead class="bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">Row</th>
                            <th class="px-3 py-2 font-medium">Errors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100 dark:divide-red-500/10">
                        @foreach ($failures as $failure)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $failure['row'] }}</td>
                                <td class="px-3 py-2 text-red-600 dark:text-red-400">{{ implode(' ', $failure['errors']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.modal>
</div>
