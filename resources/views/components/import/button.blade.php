@props(['model', 'permission', 'title' => 'Import'])

{{--
    Embeds the shared Import modal for any Eloquent model implementing
    App\Contracts\Importable. Listen for the "imported" browser event in
    the embedding Livewire component to refresh the list once rows have
    been created:

        #[On('imported')]
        public function refreshAfterImport(): void {}

    Pass wire:key explicitly if embedding more than one on the same page.
--}}
<livewire:shared.import-modal :model-class="$model" :permission="$permission" :title="$title" {{ $attributes }} />
