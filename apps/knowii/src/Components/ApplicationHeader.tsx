import React, { PropsWithChildren } from 'react';
import SectionTitle from '@/Components/SectionTitle';
import { useRoute } from 'ziggy-js';
import { DASHBOARD_URL, LOGIN_URL, LOGOUT_URL, REGISTER_URL, useTypedPage } from '@knowii/common';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from 'primereact/button';
import { Link } from '@inertiajs/react';

interface Props {
  canLogin: boolean;
  canRegister: boolean;
  showAuthenticationLinks: boolean;
}

export default function ApplicationHeader(props: PropsWithChildren<Props>) {
  const route = useRoute();
  const page = useTypedPage();

  return (
    <header className="p-4 md:p-6 lg:p-12 bg-gray-800 flex flex-col md:flex-row flex-wrap items-center justify-center md:justify-between">
      <ApplicationLogo />
      {props.showAuthenticationLinks && props.canLogin ? (
        <>
          <div className="flex flex-col sm:flex-row flex-wrap gap-4 mt-6 md:mt-0">
            {page.props.auth.user ? (
              <>
                <Link href={route(LOGOUT_URL)} method="POST" className="">
                  <Button severity="secondary" label="Logout" className="min-w-48 min-h-16" />
                </Link>
                <Link href={route(DASHBOARD_URL)} className="">
                  <Button label="Dashboard" className="font-mono font-bold min-w-48 min-h-16" />
                </Link>
              </>
            ) : (
              <>
                <Link href={route(LOGIN_URL)} className="">
                  <Button severity="secondary" label="Login" className="font-mono min-w-48 min-h-16" />
                </Link>
                {props.canRegister && (
                  <Link href={route(REGISTER_URL)} className="">
                    <Button label="Register" className="font-mono font-bold min-w-48 min-h-16" />
                  </Link>
                )}
              </>
            )}
          </div>
        </>
      ) : null}
    </header>
  );
}
