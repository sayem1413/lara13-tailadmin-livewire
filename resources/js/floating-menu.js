import { autoUpdate, computePosition, flip, offset, shift } from '@floating-ui/dom';

/**
 * Registers the Alpine.data() component backing any dropdown that needs
 * to stay inside the viewport on small screens (notifications, the user
 * menu, ...) instead of overflowing off the edge - pass the x-ref names of
 * the trigger button and the panel, and a preferred Floating UI placement
 * to fall back from.
 */
export function initFloatingMenu(Alpine) {
    Alpine.data('floatingMenu', (placement = 'bottom-end') => ({
        open: false,
        stopAutoUpdate: null,

        toggle() {
            this.open = ! this.open;
        },

        close() {
            this.open = false;
        },

        init() {
            this.$watch('open', (open) => {
                if (open) {
                    this.stopAutoUpdate = autoUpdate(this.$refs.trigger, this.$refs.panel, () => this.reposition(placement));
                } else {
                    this.stopAutoUpdate?.();
                    this.stopAutoUpdate = null;
                }
            });
        },

        reposition(placement) {
            computePosition(this.$refs.trigger, this.$refs.panel, {
                strategy: 'fixed',
                placement,
                middleware: [offset(8), flip(), shift({ padding: 8 })],
            }).then(({ x, y }) => {
                Object.assign(this.$refs.panel.style, {
                    position: 'fixed',
                    left: `${x}px`,
                    top: `${y}px`,
                });
            });
        },
    }));
}
