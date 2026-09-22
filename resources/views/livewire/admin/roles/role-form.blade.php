@php
    // One lowercase "haystack" string per module (its own label plus
    // every permission's friendly section label), in the same order as
    // the @foreach below - the client-side search filter below matches
    // against these rather than re-deriving labels in JS.
    $searchHaystacks = $permissionGroups->map(function ($permissions, $group) {
        $groupLabel = (string) str($group)->replace('-', ' ')->headline();
        $sectionLabels = $permissions->map(fn ($permission) => $this->sectionLabel($permission->section))->implode(' ');

        return strtolower($groupLabel.' '.$sectionLabels);
    })->values()->all();

    $totalPermissions = $permissionGroups->sum(fn ($permissions) => $permissions->count());
@endphp

<div>
    <form
        wire:submit="save"
        x-data="{
            search: '',
            haystacks: @js($searchHaystacks),
            original: @js($originalPermissions),
            matches(index) { return this.search === '' || this.haystacks[index].includes(this.search.toLowerCase()); },
            get hasAnyMatch() { return this.haystacks.some((haystack, index) => this.matches(index)); },
            get isReducingPermissions() { return this.original.some((name) => ! $wire.selectedPermissions.includes(name)); },
        }"
    >
        {{-- Sticky within <main>'s own scroll container (see app-layout.blade.php) -
             the global header above it is sticky at z-30, so this stays below it. --}}
        <div class="sticky top-0 z-10 -mx-4 mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50/95 px-4 py-4 backdrop-blur-sm sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:border-white/10 dark:bg-gray-900/95">
            <div>
                <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                    {{ $roleId ? 'Edit Role' : 'Add Role' }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ count($selectedPermissions) }} of {{ $totalPermissions }} permissions selected
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.roles.index') }}">
                    <x-ui.button type="button" variant="secondary">Cancel</x-ui.button>
                </a>
                <x-ui.button
                    type="submit"
                    loading-text="Saving..."
                    x-bind:data-confirm="isReducingPermissions ? 'reduce-permissions' : null"
                >Save</x-ui.button>
            </div>
        </div>

        <x-forms.error for="role" />

        <div class="space-y-6">
            <x-ui.card class="space-y-5">
                <div>
                    <x-forms.label for="name">Role Name</x-forms.label>
                    <x-forms.input type="text" icon="shield" id="name" wire:model="name" required />
                    <x-forms.error for="name" />
                </div>

                <div>
                    <x-forms.label for="description">Description</x-forms.label>
                    <x-forms.textarea id="description" wire:model="description" rows="3" />
                    <x-forms.error for="description" />
                </div>

                <x-forms.toggle wire:model="is_active" label="Active" />
                <x-forms.error for="is_active" />
            </x-ui.card>

            <div class="max-w-sm">
                <x-forms.input type="search" icon="search" x-model="search" placeholder="Search permissions..." />
            </div>

            <x-forms.error for="selectedPermissions.*" />
            <x-forms.error for="permissions" />

            <p x-show="! hasAnyMatch" x-cloak class="text-sm text-gray-500 dark:text-gray-400">
                No permissions match "<span x-text="search"></span>".
            </p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($permissionGroups as $group => $permissions)
                    @php
                        $namesInGroup = $permissions->pluck('name')->all();
                        $heldNamesInGroup = array_values(array_intersect($namesInGroup, $heldPermissions));
                        $selectedInGroup = array_intersect($namesInGroup, $selectedPermissions);
                        $groupLabel = (string) str($group)->replace('-', ' ')->headline();
                        $allHeldSelected = $heldNamesInGroup !== [] && empty(array_diff($heldNamesInGroup, $selectedPermissions));
                    @endphp
                    <div wire:key="permission-group-{{ $group }}" x-show="matches({{ $loop->index }})">
                        <x-ui.card>
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon :name="$this->moduleIcon($group)" class="size-5 text-gray-400" />
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $groupLabel }}</h3>
                                </div>
                                <x-ui.badge :color="count($selectedInGroup) === count($namesInGroup) ? 'green' : 'gray'">
                                    {{ count($selectedInGroup) }} of {{ count($namesInGroup) }}
                                </x-ui.badge>
                            </div>

                            <label class="mb-3 flex items-center gap-2 border-b border-gray-100 pb-3 text-sm font-medium text-gray-700 dark:border-white/10 dark:text-gray-200">
                                <x-forms.checkbox
                                    wire:click="toggleGroup({{ json_encode($heldNamesInGroup) }})"
                                    :checked="$allHeldSelected"
                                    :disabled="$heldNamesInGroup === []"
                                />
                                Select all in this module
                            </label>

                            <div class="space-y-2">
                                @foreach ($permissions as $permission)
                                    @php $isHeld = in_array($permission->name, $heldPermissions, true); @endphp
                                    <label
                                        wire:key="permission-{{ $permission->id }}"
                                        class="flex min-h-9 items-center gap-2 text-sm {{ $isHeld ? 'text-gray-700 dark:text-gray-300' : 'cursor-not-allowed text-gray-400 dark:text-gray-600' }}"
                                        @unless ($isHeld) title="You don't have this permission yourself, so you can't grant it to a role." @endunless
                                    >
                                        <x-forms.checkbox wire:model.live="selectedPermissions" value="{{ $permission->name }}" :disabled="! $isHeld" />
                                        {{ $this->sectionLabel($permission->section) }}
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.card>
                    </div>
                @endforeach
            </div>
        </div>
    </form>
</div>
