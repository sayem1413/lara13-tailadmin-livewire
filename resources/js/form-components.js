import Choices from 'choices.js';
import flatpickr from 'flatpickr';

/**
 * Registers the Alpine.data components backing the resources/views/
 * components/forms/{password,select,date-picker,file-upload}.blade.php
 * components. Call once, before Alpine.start()/Livewire.start().
 */
export function initFormComponents(Alpine) {
    Alpine.data('submitGuard', () => ({
        submitting: false,

        init() {
            this.$el.closest('form')?.addEventListener('submit', () => {
                this.submitting = true;
            });

            // Full-page forms never need to reset - the page navigates away
            // (success or a validation-error redirect back) either way. A
            // Livewire form re-renders in place instead, so it needs an
            // explicit reset once its request settles, or the button would
            // stay disabled forever after the first submit.
            window.addEventListener('livewire-request-finished', () => {
                this.submitting = false;
            });
        },
    }));

    Alpine.data('passwordField', () => ({
        visible: false,
        value: '',
        score: 0,

        update(value) {
            this.value = value;
            this.score = this.scoreOf(value);
        },

        scoreOf(value) {
            if (value.length === 0) {
                return 0;
            }

            let score = value.length >= 8 ? 1 : 0;

            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
                score++;
            }

            if (/\d/.test(value)) {
                score++;
            }

            if (/[^A-Za-z0-9]/.test(value)) {
                score++;
            }

            return score;
        },

        get strengthLabel() {
            return ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'][this.score];
        },

        get strengthColor() {
            return ['bg-red-500', 'bg-red-500', 'bg-yellow-500', 'bg-blue-500', 'bg-green-500'][this.score];
        },

        get strengthTextColor() {
            return ['text-red-600', 'text-red-600', 'text-yellow-600', 'text-blue-600', 'text-green-600'][this.score];
        },
    }));

    Alpine.data('enhancedSelect', () => ({
        instance: null,

        init() {
            this.instance = new Choices(this.$refs.select, {
                shouldSort: false,
                searchEnabled: true,
                itemSelectText: '',
                removeItemButton: this.$refs.select.multiple,
                // Choices reads this from the passed element's `.placeholder`
                // *property*, which only exists on <input>/<textarea> - a
                // <select>'s `placeholder="..."` attribute (what the Blade
                // component actually renders) is invisible to it otherwise,
                // silently dropping the placeholder text entirely.
                placeholderValue: this.$refs.select.getAttribute('placeholder'),
            });

            // Choices dispatches its own `change` CustomEvent with
            // `detail: { value, ... }` (metadata, not the bare value) -
            // but Livewire's wire:model treats ANY CustomEvent's `.detail`
            // as the new model value directly (see livewire.js's
            // getInputValue(), which assumes a well-behaved custom
            // element passes the value itself as detail). That mismatch
            // makes Livewire store the whole `{value: ...}` object instead
            // of the string/array inside it - harmless-looking for an
            // array-typed multi-select, but a hard TypeError ("Cannot
            // assign array to property ... of type string") for a plain
            // single-select filter. Intercepting in the capture phase on
            // the wrapping element runs before the event ever reaches the
            // <select> (and Livewire's listener on it), so the bad event
            // never arrives - a clean, non-Custom change event is
            // dispatched in its place, which Livewire reads
            // `event.target.value` from instead.
            this.$el.addEventListener('change', (event) => {
                if (event.target === this.$refs.select && event instanceof CustomEvent) {
                    event.stopPropagation();
                    this.$refs.select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, true);
        },

        destroy() {
            this.instance?.destroy();
        },
    }));

    Alpine.data('datePicker', (range) => ({
        instance: null,

        init() {
            this.instance = flatpickr(this.$refs.input, {
                mode: range ? 'range' : 'single',
                dateFormat: 'Y-m-d',
                onChange: (selectedDates, dateStr, instance) => {
                    // Flatpickr sets the input's value programmatically,
                    // which doesn't fire a native event on its own -
                    // dispatch one so wire:model picks up the change.
                    instance.input.dispatchEvent(new Event('input', { bubbles: true }));
                },
            });
        },

        destroy() {
            this.instance?.destroy();
        },
    }));

    Alpine.data('fileUpload', ({ maxSizeMb, accept, preview }) => ({
        dragging: false,
        previewUrl: preview,
        error: null,

        get hint() {
            return accept === 'image/*' ? `PNG, JPG up to ${maxSizeMb}MB` : `Up to ${maxSizeMb}MB`;
        },

        handleDrop(event) {
            this.dragging = false;

            const file = event.dataTransfer.files[0];

            if (!file) {
                return;
            }

            this.$refs.input.files = event.dataTransfer.files;
            this.processFile(file);
        },

        handleChange(event) {
            const file = event.target.files[0];

            if (file) {
                this.processFile(file);
            }
        },

        processFile(file) {
            this.error = null;

            if (accept === 'image/*' && !file.type.startsWith('image/')) {
                this.error = 'Please choose an image file.';
                this.$refs.input.value = '';

                return;
            }

            if (file.size > maxSizeMb * 1024 * 1024) {
                this.error = `File is too large. Maximum size is ${maxSizeMb}MB.`;
                this.$refs.input.value = '';

                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                this.previewUrl = event.target.result;
            };
            reader.readAsDataURL(file);
        },
    }));
}
