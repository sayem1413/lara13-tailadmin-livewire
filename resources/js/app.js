import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initConfirmActions } from './confirm-action';
import { showToast } from './toast';

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

// Lets any Livewire component show feedback the same way, without wiring a
// listener per-component: $this->dispatch('toast', type: 'error', message: '...').
Livewire.on('toast', ({ type, message }) => showToast(type, message));

Livewire.start();
