import './global.scss';
import 'primereact/resources/themes/lara-light-blue/theme.css';
import 'primeicons/primeicons.css';

import { PrimeReactProvider } from 'primereact/api';
import { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Knowii',
  description: 'Knowii',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <PrimeReactProvider value={{ ripple: true }}>{children}</PrimeReactProvider>
      </body>
    </html>
  );
}
