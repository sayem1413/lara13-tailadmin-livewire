import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initConfirmActions } from './confirm-action';
import { initFormComponents } from './form-components';
import { showToast } from './toast';

import 'choices.js/public/assets/styles/choices.min.css';
import 'flatpickr/dist/flatpickr.min.css';

window.showToast = showToast;

Alpine.data('darkMode', () => ({
    enabled: localStorage.getItem('dark-mode') === 'true',

    init() {
        this.apply();
    },

    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('dark-mode', this.enabled ? 'true' : 'false');
        this.apply();
    },

    apply() {
        document.documentElement.classList.toggle('dark', this.enabled);
    },
}));

initConfirmActions();
initFormComponents(Alpine);

// Lets any Livewire component show feedback the same way, without wiring a
// listener per-component: $this->dispatch('toast', type: 'error', message: '...').
Livewire.on('toast', ({ type, message }) => showToast(type, message));

// Lets x-ui.button's submitGuard() reset a Livewire form's submit button
// after its request settles (success or validation failure) - a plain
// form never needs this since submitting it always navigates away.
Livewire.hook('request', ({ succeed, fail }) => {
    const done = () => window.dispatchEvent(new Event('livewire-request-finished'));
    succeed(done);
    fail(done);
});

Livewire.start();
