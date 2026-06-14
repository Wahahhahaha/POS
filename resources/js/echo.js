import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const appUrl = document.querySelector('meta[name="app-url"]')?.getAttribute('content') || window.location.origin;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    enabledTransports: ['ws', 'wss', 'xhr_streaming', 'xhr_polling'],
    // Use the full appUrl to avoid subfolder path issues
    authEndpoint: appUrl.replace(/\/$/, '') + '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        },
    },
});