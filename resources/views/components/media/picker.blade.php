@props(['label' => 'Choose File'])

{{--
    Embeds the Media Manager's picker: a button that opens a modal to
    search existing library files or upload a new one. Listen for the
    "media-picked" browser event in the embedding Livewire component to
    receive {id, url, name} once a file is chosen:

        #[On('media-picked')]
        public function attachMedia(array $media): void { ... }

    Pass wire:key explicitly if embedding more than one on the same page.
--}}
<livewire:media.media-picker :label="$label" {{ $attributes }} />
