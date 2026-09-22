import Swal from 'sweetalert2';

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

/**
 * Fire a small, auto-dismissing SweetAlert2 toast. The single place both
 * server-flashed messages (see x-app-layout) and Livewire-dispatched
 * "toast" events (see app.js) route through, so every part of the app
 * shows feedback the same way.
 *
 * @param {'success'|'error'|'warning'|'info'|'question'} icon
 * @param {string} message
 */
export function showToast(icon, message) {
    toast.fire({ icon, title: message });
}
