import './bootstrap';
// Noto Sans is served by Knowii, never by a font service (#1341): Vite bundles the WOFF2 files of the package
import '@fontsource-variable/noto-sans/wght.css';
import '@fontsource-variable/noto-sans/wght-italic.css';
import './styles.css';

import { StrictMode } from 'react';
import * as ReactDOM from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { AppDataProvider, removeExpiredKeptTexts } from '@knowii/common';
import { ErrorBoundary, Toaster } from '@/Components/ui';
import { ApplicationToolkitProvider } from '@/Components/ApplicationToolkit';
import { pageComponents } from './page-components';
import { startPageViews } from './statistics';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// The texts that were not sent and whose last change is more than 7 days ago leave the browser, for every account (#1622)
removeExpiredKeptTexts();

// One page view per visit of a page, with the address that the server cleaned (#1316, ADR 0064): before the application
// starts, so that the first load counts. Nothing is sent when the instance sets no statistics
startPageViews();

// Per-request CSP nonce for the <style> elements that Inertia injects (progress bar, error dialog), see app.blade.php
const styleNonce = document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')?.nonce;

createInertiaApp({
  ...(styleNonce ? { nonce: styleNonce } : {}),
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, pageComponents),
  setup({ el, App, props }) {
    const root = ReactDOM.createRoot(el);

    root.render(
      <StrictMode>
        {/* The toolkit imports nothing of the application (#657): its links are Inertia links, its videos the player */}
        <ApplicationToolkitProvider>
          <AppDataProvider>
            {/* The single application toaster lives here (not in a page layout), so toasts survive Inertia page visits */}
            <Toaster />
            {/* Last resort: a page that fails outside its content area (the layout) shows this instead of a blank screen */}
            <ErrorBoundary className="mt-10">
              <App {...props} />
            </ErrorBoundary>
          </AppDataProvider>
        </ApplicationToolkitProvider>
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
