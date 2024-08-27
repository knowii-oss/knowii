import './global.scss';

import 'primereact/resources/themes/lara-light-blue/theme.css';
import 'primeicons/primeicons.css';

import { Logo, ProgressBar } from '@knowii/client-ui';

import { PrimeReactProvider } from 'primereact/api';
import { Metadata } from 'next';
import React from 'react';

export const metadata: Metadata = {
  title: 'Knowii',
  description: 'Knowii',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <main className="knowii-app-wrapper">
          <PrimeReactProvider value={{ ripple: true }}>
            <ProgressBar />
            {/* FIXME add nav component */}
            <Logo />
            <div className="knowii-content-wrapper">{children}</div>
            {/* FIXME add footer */}
            <div>Footer</div>
          </PrimeReactProvider>
        </main>
      </body>
    </html>
  );
}
