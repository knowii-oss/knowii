import React, { PropsWithChildren } from 'react';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo';
import ApplicationHeader from '@/Components/ApplicationHeader';

export default function AuthenticationCard({ children }: PropsWithChildren<Record<string, unknown>>) {
  return (
    <div className="mt-16 flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
      <div className="bg-gray-800 w-full sm:max-w-md sm:rounded-lg flex justify-center">
        <AuthenticationCardLogo />
      </div>

      <div className="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">{children}</div>
    </div>
  );
}
