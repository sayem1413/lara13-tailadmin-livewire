<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Activity Log</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">A record of changes made throughout the application.</p>
    </div>

    <x-ui.card :padded="false">
        <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 p-4 dark:border-white/10">
            <div class="max-w-xs flex-1">
                <x-forms.input type="search" wire:model.live.debounce.300ms="search" placeholder="Search description or causer..." />
            </div>

            <x-forms.select wire:model.live="event" class="w-auto">
                <option value="">All events</option>
                @foreach ($this->eventOptions as $option)
                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                @endforeach
            </x-forms.select>

            <x-forms.select wire:model.live="subjectType" class="w-auto">
                <option value="">All subjects</option>
                @foreach ($this->subjectTypeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-forms.select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-500 uppercase dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Causer</th>
                        <th class="px-4 py-3 font-medium">Event</th>
                        <th class="px-4 py-3 font-medium">Subject</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 font-medium">&nbsp;</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($activities as $activity)
                        <tr wire:key="activity-{{ $activity->id }}" x-data="{ open: false }">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ $activity->created_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $activity->causer?->name ?? 'System' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($activity->event)
                                    <x-ui.badge color="gray">{{ ucfirst($activity->event) }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $activity->subject_type ? class_basename($activity->subject_type) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $activity->description }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($activity->properties && $activity->properties->isNotEmpty())
                                    <button type="button" @click="open = !open" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                        <span x-text="open ? 'Hide' : 'View'"></span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if ($activity->properties && $activity->properties->isNotEmpty())
                            <tr x-show="open" x-cloak wire:key="activity-{{ $activity->id }}-details">
                                <td colspan="6" class="bg-gray-50 px-4 py-3 dark:bg-white/[0.02]">
                                    <pre class="overflow-x-auto text-xs text-gray-600 dark:text-gray-300">{{ $activity->properties->toJson(JSON_PRETTY_PRINT) }}</pre>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No activity recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 p-4 dark:border-white/10">
            {{ $activities->links() }}
        </div>
    </x-ui.card>
</div>
