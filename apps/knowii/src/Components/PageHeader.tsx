import { PropsWithChildren } from 'react';
import { useRoute } from 'ziggy-js';
import { DASHBOARD_URL, HOME_URL, LOGIN_URL, LOGOUT_URL, REGISTER_URL } from '@knowii/common';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from 'primereact/button';
import { Link } from '@inertiajs/react';
import { FaSignOutAlt } from 'react-icons/fa';
import classNames from 'classnames';
import ApplicationMark from '@/Components/ApplicationMark';

interface Props {
  compact: boolean;
  addLinkOnLogo: boolean;
  showDashboardButton: boolean;
  showLoginButton: boolean;
  showLogoutButton: boolean;
  showRegisterButton: boolean;
}

export default function PageHeader(props: PropsWithChildren<Props>) {
  const route = useRoute();

  const compactLogoAndMark = (
    <div className="flex flex-col sm:flex-row gap-2 items-center h-full">
      <ApplicationMark className="w-12 md:w-10" />
      {/* Hide the logo when the screen is too small in compact mode */}
      <ApplicationLogo className="hidden sm:block text-2xl md:text-4xl lg:text-4xl" />
    </div>
  );

  const normalLogoAndMark = (
    <div className="flex flex-col sm:flex-row gap-2 items-center h-full">
      <ApplicationMark className="w-14 md:w-18 lg:w-24" />
      <ApplicationLogo />
    </div>
  );

  return (
    <header
      className={classNames(
        `bg-gray-800 flex flex-col md:flex-row flex-wrap items-center justify-center md:justify-between`,
        { 'p-4 md:p-6 lg:p-12': !props.compact },
        { 'p-2 lg:p-2': props.compact },
      )}
    >
      {props.addLinkOnLogo ? (
        <a href={HOME_URL}>{props.compact ? compactLogoAndMark : normalLogoAndMark}</a>
      ) : props.compact ? (
        compactLogoAndMark
      ) : (
        normalLogoAndMark
      )}

      {props.showDashboardButton || props.showLoginButton || props.showLogoutButton || props.showRegisterButton ? (
        <nav className="flex flex-col sm:flex-row flex-wrap gap-4 mt-6 md:mt-0">
          {props.showDashboardButton && (
            <Link href={route(DASHBOARD_URL)} className="">
              <Button label="Dashboard" className={classNames('font-mono font-bold min-w-48', { 'min-h-16': !props.compact })} />
            </Link>
          )}
          {props.showLogoutButton && (
            <Link href={route(LOGOUT_URL)} method="post" className="">
              <Button
                severity="secondary"
                label="Log out"
                className={classNames('font-mono font-bold min-w-48', { 'min-h-16': !props.compact })}
              >
                <FaSignOutAlt />
              </Button>
            </Link>
          )}
          {props.showLoginButton && (
            <Link href={route(LOGIN_URL)} className="">
              <Button
                severity="secondary"
                label="Login"
                className={classNames('font-mono font-bold min-w-48', { 'min-h-16': !props.compact })}
              />
            </Link>
          )}

          {props.showRegisterButton && (
            <Link href={route(REGISTER_URL)} className="">
              <Button label="Register" className={classNames('font-mono font-bold min-w-48', { 'min-h-16': !props.compact })} />
            </Link>
          )}

          {props.children}
        </nav>
      ) : null}
    </header>
  );
}
