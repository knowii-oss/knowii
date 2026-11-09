import type { AxiosInstance } from 'axios';
import type { route as ziggyRoute } from 'ziggy-js';
import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';

declare global {
  interface Window {
    axios: AxiosInstance;

    // WARNING: Required for WebSockets (Laravel Reverb/Pusher protocol/Laravel Echo)
    Pusher: typeof Pusher;
    Echo: Echo;
  }

  let route: typeof ziggyRoute;
}
