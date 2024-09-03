import { Head, Link } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import React from 'react';
import { DASHBOARD_URL, HOME_URL, LOGIN_URL, REGISTER_URL, useTypedPage } from '@knowii/common';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from 'primereact/button';
import ApplicationHeader from '@/Components/ApplicationHeader';

interface WelcomePageProps {
  canLogin: boolean;
  canRegister: boolean;
}

export default function Welcome({ canLogin, canRegister }: WelcomePageProps) {
  const route = useRoute();
  const page = useTypedPage();

  return (
    <>
      <Head title="Welcome" />

      <div className="bg-gray-50 text-black/80 full-page">
        <ApplicationHeader canLogin={canLogin} canRegister={canRegister} showAuthenticationLinks={true} />

        <main className="mt-8 md:mt-12 lg:mt-16 px-8 md:px-0">
          <div className="block flex flex-col items-center">
            <h1 className="text-primary-500">Welcome&nbsp;👋</h1>
            <h2 className="mt-4">
              Know<span className="text-primary-500">ii</span> is a place for your community's Knowledge, Ideas and Inspiration.
            </h2>
          </div>

          <div className="mt-16 flex flex-row justify-center text-5xl font-bold">Coming soon...</div>
        </main>
        <footer className=""></footer>
      </div>
    </>
  );
}
