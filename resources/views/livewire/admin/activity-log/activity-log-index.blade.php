<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Activity Log</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">A record of changes made throughout the application.</p>
    </div>

    <x-ui.card :padded="false">
        <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 p-4 dark:border-white/10">
            <div class="max-w-xs flex-1">
                <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search description or causer..." />
            </div>

            <div class="w-40">
                <x-forms.select wire:model.live="event">
                    <option value="">All events</option>
                    @foreach ($this->eventOptions as $option)
                        <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                    @endforeach
                </x-forms.select>
            </div>

            <div class="w-40">
                <x-forms.select wire:model.live="subjectType">
                    <option value="">All subjects</option>
                    @foreach ($this->subjectTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-forms.select>
            </div>

            <div class="w-56">
                <x-forms.date-picker range wire:model.live.debounce.300ms="dateRange" />
            </div>
        </div>

        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Causer</th>
                    <th class="px-4 py-3 font-medium">Event</th>
                    <th class="px-4 py-3 font-medium">Subject</th>
                    <th class="px-4 py-3 font-medium">Description</th>
                    <th class="px-4 py-3 font-medium">&nbsp;</th>
                </tr>
            </x-slot:head>

            @forelse ($activities as $activity)
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-white/10"
                        @if ($activity->attribute_changes && $activity->attribute_changes->isNotEmpty()) x-data="{ open: false }" @endif
                    >
                        <tr wire:key="activity-{{ $activity->id }}">
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
                                @if ($activity->attribute_changes && $activity->attribute_changes->isNotEmpty())
                                    <button type="button" @click="open = !open" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                        <span x-text="open ? 'Hide' : 'View'"></span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if ($activity->attribute_changes && $activity->attribute_changes->isNotEmpty())
                            <tr x-show="open" x-cloak wire:key="activity-{{ $activity->id }}-details">
                                <td colspan="6" class="bg-gray-50 px-4 py-3 dark:bg-white/[0.02]">
                                    <x-activity-log.diff :changes="$activity->attribute_changes" />
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No activity recorded yet.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
        </x-ui.table>

        <div class="divide-y divide-gray-100 md:hidden dark:divide-white/10">
            @forelse ($activities as $activity)
                <div
                    wire:key="activity-mobile-{{ $activity->id }}"
                    class="p-4"
                    @if ($activity->attribute_changes && $activity->attribute_changes->isNotEmpty()) x-data="{ open: false }" @endif
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm text-gray-800 dark:text-white/90">{{ $activity->description }}</p>
                        @if ($activity->event)
                            <x-ui.badge color="gray">{{ ucfirst($activity->event) }}</x-ui.badge>
                        @endif
                    </div>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $activity->created_at?->format('Y-m-d H:i') }}
                        &middot; {{ $activity->causer?->name ?? 'System' }}
                        @if ($activity->subject_type)
                            &middot; {{ class_basename($activity->subject_type) }}
                        @endif
                    </p>

                    @if ($activity->attribute_changes && $activity->attribute_changes->isNotEmpty())
                        <button type="button" @click="open = !open" class="mt-2 min-h-11 font-medium text-brand-600 dark:text-brand-400">
                            <span x-text="open ? 'Hide details' : 'View details'"></span>
                        </button>

                        <div x-show="open" x-cloak class="mt-2">
                            <x-activity-log.diff :changes="$activity->attribute_changes" />
                        </div>
                    @endif
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No activity recorded yet.</p>
            @endforelse
        </div>

        <x-ui.load-more :paginator="$activities" />
    </x-ui.card>
</div>
