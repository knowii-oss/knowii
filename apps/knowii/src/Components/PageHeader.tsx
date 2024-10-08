import { PropsWithChildren } from 'react';
import { useRoute } from 'ziggy-js';
import { DASHBOARD_URL, HOME_URL, LOGIN_URL, LOGOUT_URL, REGISTER_URL } from '@knowii/common';
import { Button } from 'primereact/button';
import { Link } from '@inertiajs/react';
import { FaSignOutAlt } from 'react-icons/fa';
import classNames from 'classnames';
import ApplicationMark from '@/Components/ApplicationMark';
import ApplicationName from '@/Components/ApplicationName';

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
      <ApplicationMark className="w-12 md:w-14 lg:w-14" />
      {/* Hide the logo when the screen is too small in compact mode */}
      <ApplicationName className="hidden sm:block sm:w-40 md:w-44 xl:w-48" />
    </div>
  );

  const normalLogoAndMark = (
    <div className="flex flex-col sm:flex-row gap-2 items-center h-full">
      <ApplicationMark className="w-14 md:w-18 lg:w-24" />
      <ApplicationName className="w-40 lg:w-auto" />
    </div>
  );

  return (
    <header
      className={classNames(
        `bg-gray-800 flex flex-col flex-wrap md:flex-row sm:justify-center items-center md:justify-between`,
        { 'p-4 md:p-6 lg:p-12': !props.compact },
        { 'pt-4 pl-0 sm:pl-4 sm:pr-4 pb-2 lg:pb-2': props.compact },
      )}
    >
      {props.addLinkOnLogo ? (
        <a href={HOME_URL}>{props.compact ? compactLogoAndMark : normalLogoAndMark}</a>
      ) : props.compact ? (
        compactLogoAndMark
      ) : (
        normalLogoAndMark
      )}

      <nav className="flex flex-row flex-wrap gap-4 mt-6 md:mt-0 justify-center">
        {props.showDashboardButton && (
          <Link href={route(DASHBOARD_URL)} className="">
            <Button label="Dashboard" className={classNames('font-mono font-bold', { 'min-w-48 min-h-16': !props.compact })} />
          </Link>
        )}
        {props.showLogoutButton && (
          <Link href={route(LOGOUT_URL)} method="post" className="">
            <Button
              aria-label={'Log out'}
              severity="secondary"
              className={classNames('font-mono font-bold min-h-full', { 'flex flex-row justify-center': !props.compact })}
            >
              <FaSignOutAlt />
            </Button>
          </Link>
        )}
        {props.showLoginButton && (
          <Link href={route(LOGIN_URL)} className="">
            <Button
              severity="secondary"
              label="Log in"
              aria-label={'Log in'}
              className={classNames('font-mono font-bold min-w-48 min-h-full')}
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
    </header>
  );
}
