import type { PropsWithChildren } from 'react';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo';
import { Card, ThemeToggle } from '@/Components/ui';

export default function AuthenticationCard({ children }: PropsWithChildren<Record<string, unknown>>) {
  return (
    <main className="relative flex flex-col items-center px-0 pt-6 sm:justify-center sm:px-4 sm:pt-16">
      <ThemeToggle className="absolute top-4 right-4" />
      <div className="flex w-48 justify-center sm:w-60">
        <AuthenticationCardLogo />
      </div>

      <Card as="div" className="mt-6 w-full overflow-hidden rounded-none border-x-0 px-6 py-6 sm:max-w-md sm:rounded-xl sm:border-x">
        {children}
      </Card>
    </main>
  );
}
