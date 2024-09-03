import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';

declare global {
  interface Window {
    axios: AxiosInstance;
  }

  let route: typeof ziggyRoute;
}
