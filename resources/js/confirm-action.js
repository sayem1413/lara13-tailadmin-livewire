import Swal from 'sweetalert2';

/**
 * Presets for `data-confirm="<preset>"` triggers. `{subject}`/`{count}`/
 * `{entityPlural}` are filled in from the trigger's data-confirm-name,
 * data-confirm-entity, data-confirm-count, and data-confirm-entity-plural
 * attributes (see fillTemplate() below) - all optional, so a trigger with
 * none of them still gets a sensible generic message. Add project-specific
 * presets here rather than hand-rolling SweetAlert2 calls per view.
 */
const presets = {
    delete: {
        title: 'Delete {subject}?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        confirmButtonText: 'Delete',
        confirmButtonColor: '#d92d20',
    },
    deactivate: {
        title: 'Deactivate {subject}?',
        text: 'It will no longer be usable until reactivated.',
        icon: 'warning',
        confirmButtonText: 'Deactivate',
        confirmButtonColor: '#d92d20',
    },
    'bulk-activate': {
        title: 'Activate {count} {entityPlural}?',
        icon: 'question',
        confirmButtonText: 'Activate',
    },
    'bulk-deactivate': {
        title: 'Deactivate {count} {entityPlural}?',
        text: 'They will no longer be usable until reactivated.',
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
 * A human description of what's being acted on, from whichever of
 * data-confirm-entity/data-confirm-name a trigger provides - "this item"
 * when it provides neither.
 */
function subjectFrom(trigger) {
    const entity = trigger.dataset.confirmEntity;
    const name = trigger.dataset.confirmName;

    if (entity && name) {
        return `${entity} "${name}"`;
    }

    if (name) {
        return `"${name}"`;
    }

    if (entity) {
        return `this ${entity.toLowerCase()}`;
    }

    return 'this item';
}

function fillTemplate(text, trigger) {
    if (!text) {
        return text;
    }

    const count = Number(trigger.dataset.confirmCount ?? 0);
    const plural = (trigger.dataset.confirmEntityPlural ?? 'items').toLowerCase();
    // Regular plurals only ("users" -> "user") - the only shape this
    // starter's own entities need; an irregular one can pass its own
    // singular via a future data-confirm-entity-singular if that ever
    // comes up.
    const entityPlural = count === 1 ? plural.replace(/s$/, '') : plural;

    return text
        .replace('{subject}', subjectFrom(trigger))
        .replace('{count}', trigger.dataset.confirmCount ?? '')
        .replace('{entityPlural}', entityPlural);
}

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
            title: fillTemplate(preset.title, trigger),
            text: fillTemplate(preset.text, trigger),
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
