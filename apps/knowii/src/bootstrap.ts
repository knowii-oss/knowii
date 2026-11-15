import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// WARNING: Required by Inertia
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// WARNING: Required for WebSockets (Laravel Reverb/Pusher protocol/Laravel Echo)
window.Pusher = Pusher;
// Echo opens its connection when it is created: it is created on first use, so a visitor of a public space, whose
// pages subscribe to nothing (useSocket enabled=false), never opens a WebSocket connection (#1387)
let echo: Echo<'reverb'> | undefined;
Object.defineProperty(window, 'Echo', {
  configurable: true,
  get: (): Echo<'reverb'> => {
    echo ??= new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
      enabledTransports: ['ws', 'wss'],
    });
    return echo;
  },
  set: (value: Echo<'reverb'>) => {
    echo = value;
  },
});
