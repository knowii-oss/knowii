import { PropsWithChildren } from 'react';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo';

export default function AuthenticationCard({ children }: PropsWithChildren<Record<string, unknown>>) {
  return (
    <div className="mt-0 pt-0 sm:mt-16 flex flex-col sm:justify-center items-center">
      <div className="bg-gray-800 w-full sm:max-w-md sm:rounded-lg flex justify-center">
        <AuthenticationCardLogo />
      </div>

      <div className="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">{children}</div>
    </div>
  );
}
