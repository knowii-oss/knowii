import './bootstrap';
import './styles.css';

import { StrictMode } from 'react';
import * as ReactDOM from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { PrimeReactProvider } from 'primereact/api';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
  setup({ el, App, props }) {
    const root = ReactDOM.createRoot(el);

    root.render(
      <StrictMode>
        <PrimeReactProvider
          value={{
            ripple: true,
          }}
        >
          <App {...props} />
        </PrimeReactProvider>
      </StrictMode>,
    );
  },
  progress: {
    color: '#4B5563',
  },
}).then((_createdApp) => {
  console.log('Welcome to Knowii ❤️');
});
