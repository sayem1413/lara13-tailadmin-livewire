import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initConfirmActions } from './confirm-action';

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

Livewire.start();
