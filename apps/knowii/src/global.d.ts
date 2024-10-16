import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    axios: AxiosInstance;

    // WARNING: Required for WebSockets (Laravel Reverb/Pusher protocol/Laravel Echo)
    Pusher: typeof Pusher;
    Echo: Echo;
  }

  let route: typeof ziggyRoute;
}
