import Swal from 'sweetalert2';

/**
 * Presets for `data-confirm="<preset>"` triggers. Add project-specific
 * presets here rather than hand-rolling SweetAlert2 calls per view.
 */
const presets = {
    delete: {
        title: 'Delete this item?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        confirmButtonText: 'Delete',
        confirmButtonColor: '#d92d20',
    },
    deactivate: {
        title: 'Deactivate this record?',
        text: 'It will no longer be usable until reactivated.',
        icon: 'warning',
        confirmButtonText: 'Deactivate',
        confirmButtonColor: '#d92d20',
    },
    logout: {
        title: 'Log out?',
        text: 'You will need to sign in again to continue.',
        icon: 'question',
        confirmButtonText: 'Log out',
    },
    default: {
        title: 'Are you sure?',
        icon: 'warning',
        confirmButtonText: 'Confirm',
    },
};

/**
 * Wire up a single delegated click listener that intercepts any
 * `[data-confirm]` trigger (link, button, or form) and only lets the
 * original action through once the user confirms a SweetAlert2 dialog.
 */
export function initConfirmActions() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-confirm]');

        if (!trigger || trigger.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const preset = presets[trigger.dataset.confirm] ?? presets.default;

        Swal.fire({
            ...preset,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            trigger.dataset.confirmed = 'true';

            if (trigger.tagName === 'BUTTON' && trigger.form) {
                trigger.form.requestSubmit(trigger);
            } else if (trigger.tagName === 'FORM') {
                trigger.requestSubmit();
            } else if (trigger.hasAttribute('href')) {
                window.location.href = trigger.getAttribute('href');
            } else {
                trigger.click();
            }
        });
    }, true);
}
