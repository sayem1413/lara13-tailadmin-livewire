<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Settings</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Manage application-wide settings.</p>
    </div>

    <form wire:submit="save" class="max-w-3xl space-y-5">
        @foreach ($schema as $group)
            <x-ui.card :title="$group['label']">
                <div class="space-y-5">
                    @foreach ($group['fields'] as $key => $field)
                        <div>
                            @if ($field['type'] === 'boolean')
                                <x-forms.toggle wire:model="values.{{ $key }}" :label="$field['label']" />
                            @else
                                <x-forms.label for="{{ $key }}">{{ $field['label'] }}</x-forms.label>

                                @if ($field['type'] === 'textarea')
                                    <x-forms.textarea id="{{ $key }}" wire:model="values.{{ $key }}" rows="3">{{ $values[$key] ?? '' }}</x-forms.textarea>
                                @elseif ($field['type'] === 'select')
                                    <x-forms.select id="{{ $key }}" wire:model="values.{{ $key }}">
                                        @foreach ($field['options'] ?? [] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </x-forms.select>
                                @else
                                    <x-forms.input type="{{ $field['input_type'] ?? 'text' }}" :icon="$field['icon'] ?? null" id="{{ $key }}" wire:model="values.{{ $key }}" />
                                @endif
                            @endif

                            <x-forms.error for="values.{{ $key }}" />
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        <x-ui.button type="submit" loading-text="Saving...">Save Settings</x-ui.button>
    </form>
</div>
