@props(['action' => 'exportPdf'])

<x-ui.button type="button" variant="secondary" wire:click="{{ $action }}" {{ $attributes }}>
    {{ $slot->isEmpty() ? 'Export PDF' : $slot }}
</x-ui.button>
