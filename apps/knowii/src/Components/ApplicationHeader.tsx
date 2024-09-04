import { PropsWithChildren } from 'react';
import { useRoute } from 'ziggy-js';
import { DASHBOARD_URL, LOGIN_URL, LOGOUT_URL, REGISTER_URL } from '@knowii/common';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from 'primereact/button';
import { Link } from '@inertiajs/react';
import { FaSignOutAlt } from 'react-icons/fa';

interface Props {
  showDashboardButton: boolean,
  showLoginButton: boolean;
  showLogoutButton: boolean;
  showRegisterButton: boolean;
}

export default function ApplicationHeader(props: PropsWithChildren<Props>) {
  const route = useRoute();

  return (
    <header className="p-4 md:p-6 lg:p-12 bg-gray-800 flex flex-col md:flex-row flex-wrap items-center justify-center md:justify-between">
      <ApplicationLogo />

      {props.showDashboardButton || props.showLoginButton || props.showLogoutButton || props.showRegisterButton ? (
          <nav className="flex flex-col sm:flex-row flex-wrap gap-4 mt-6 md:mt-0">
            {props.showDashboardButton && (
              <Link href={route(DASHBOARD_URL)} className="">
                <Button label="Dashboard" className="font-mono font-bold min-w-48 min-h-16" />
              </Link>
            )}
            {props.showLogoutButton && (
              <Link href={route(LOGOUT_URL)} method="post" className="">
                <Button severity="secondary" label="Log out" className="font-mono font-bold min-w-48 min-h-16">
                  <FaSignOutAlt />
                </Button>
              </Link>
            )}
            {props.showLoginButton && (
              <Link href={route(LOGIN_URL)} className="">
                <Button severity="secondary" label="Login" className="font-mono min-w-48 min-h-16" />
              </Link>
            )}

            {props.showRegisterButton && (
              <Link href={route(REGISTER_URL)} className="">
                <Button label="Register" className="font-mono font-bold min-w-48 min-h-16" />
              </Link>
            )}

            {props.children}
          </nav>
      ) : null}
    </header>
  );
}
