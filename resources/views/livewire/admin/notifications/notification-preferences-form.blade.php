<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Notification Preferences</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Choose which channels you want to receive each kind of notification through.</p>
    </div>

    <form wire:submit="save" class="max-w-3xl space-y-5">
        @foreach ($schema as $type => $preference)
            <x-ui.card :title="$preference['label']">
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ $preference['description'] }}</p>

                <div class="space-y-4">
                    @foreach ($preference['channels'] as $channel => $enabled)
                        <x-forms.toggle
                            wire:model="values.{{ $type }}.{{ $channel }}"
                            :label="match ($channel) {
                                'mail' => 'Email',
                                'database' => 'In-app',
                                default => ucfirst($channel),
                            }"
                        />
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        <x-ui.button type="submit" loading-text="Saving...">Save Preferences</x-ui.button>
    </form>
</div>
