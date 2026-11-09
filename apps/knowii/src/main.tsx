import './bootstrap';
import './styles.css';

import { StrictMode } from 'react';
import * as ReactDOM from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { AppDataProvider } from '@knowii/common';
import { Toaster } from '@/Components/ui';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) =>
    resolvePageComponent<ResolvedComponent>(
      `./Pages/${name}.tsx`,
      import.meta.glob<ResolvedComponent>('./Pages/**/*.tsx', { import: 'default' }),
    ),
  setup({ el, App, props }) {
    const root = ReactDOM.createRoot(el);

    root.render(
      <StrictMode>
        <AppDataProvider>
          {/* The single application toaster lives here (not in a page layout), so toasts survive Inertia page visits */}
          <Toaster />
          <App {...props} />
        </AppDataProvider>
      </StrictMode>,
    );
  },
  progress: {
    // Knowii magenta
    color: '#e5007d',
  },
}).then((_createdApp) => {
  console.log('Welcome to Knowii ❤️');
});
