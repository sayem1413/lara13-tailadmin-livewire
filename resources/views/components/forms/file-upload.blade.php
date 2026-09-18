@props(['accept' => 'image/*', 'maxSizeMb' => 2, 'preview' => null, 'rounded' => true, 'bag' => 'default'])

@php
    $errorKey = wireModelName($attributes) ?? $attributes->get('name');
    $hasError = $errorKey && $errors->getBag($bag)->has($errorKey);
@endphp

<div x-data="fileUpload({ maxSizeMb: {{ (float) $maxSizeMb }}, accept: @js($accept), preview: @js($preview) })">
    <div
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="handleDrop($event)"
        x-on:click="$refs.input.click()"
        :class="dragging ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/5' : '{{ $hasError ? 'border-red-300 dark:border-red-500/50' : 'border-gray-300 hover:border-gray-400 dark:border-white/10 dark:hover:border-white/20' }}'"
        class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-6 text-center transition-colors"
    >
        <template x-if="previewUrl">
            <img :src="previewUrl" class="size-20 object-cover {{ $rounded ? 'rounded-full' : 'rounded-lg' }}" alt="" />
        </template>
        <template x-if="!previewUrl">
            <span class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-white/5">
                <x-ui.icon name="upload-cloud" class="size-6" />
            </span>
        </template>

        <div class="text-sm">
            <span class="font-medium text-brand-600 dark:text-brand-400">Click to upload</span>
            <span class="text-gray-500 dark:text-gray-400"> or drag and drop</span>
            <p class="text-xs text-gray-400" x-text="hint"></p>
        </div>

        <input
            type="file"
            x-ref="input"
            accept="{{ $accept }}"
            x-on:click.stop
            x-on:change="handleChange($event)"
            {{ $attributes->merge(['class' => 'sr-only']) }}
        />
    </div>

    <p x-show="error" x-cloak x-text="error" class="mt-1.5 text-sm text-red-600 dark:text-red-400"></p>
</div>
