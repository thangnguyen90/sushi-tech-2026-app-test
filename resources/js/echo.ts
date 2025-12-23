import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global { interface Window { Pusher: typeof Pusher } }
window.Pusher = Pusher;

const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY as string;
const reverbScheme = (import.meta.env.VITE_REVERB_SCHEME as string) || 'https';
const reverbHost = (import.meta.env.VITE_REVERB_HOST as string) || window.location.hostname;
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT) || 6001;

export const echo: Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbAppKey,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    encrypted: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

const p = echo.connector.pusher;
p.connection.bind('state_change', (s) => console.log('[WS state]', s));
p.connection.bind('connected', () => console.log('[WS] connected'));
p.connection.bind('error', (err) => console.error('[WS error]', err));
