import './global.scss';

import 'primereact/resources/themes/lara-light-blue/theme.css';
import 'primeicons/primeicons.css';

import {ProgressBar} from '@knowii/client-ui';

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
            {/* FIXME extract logo into separate component */}
            <div className="font-bold text-4xl md:text-5xl lg:text-6xl leading-none tracking-tight p-4 bg-gray-800 text-white text-3xl font-bold">
              Know<span className="text-primary-500 font-extrabold">ii</span>
            </div>
            <div className="knowii-content-wrapper">{children}</div>
            {/* FIXME add footer */}
            <div>Footer</div>
          </PrimeReactProvider>
        </main>
      </body>
    </html>
  );
}
