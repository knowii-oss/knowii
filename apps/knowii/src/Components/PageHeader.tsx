import type { PropsWithChildren } from 'react';
import { useRoute } from 'ziggy-js';
import { DASHBOARD_URL, HOME_URL, LOGIN_URL, LOGOUT_URL, REGISTER_URL } from '@knowii/common';
import { FaSignOutAlt } from 'react-icons/fa';
import ApplicationMark from '@/Components/ApplicationMark';
import ApplicationName from '@/Components/ApplicationName';
import { cn, LinkButton, ThemeToggle } from '@/Components/ui';

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
    <div className="flex h-full flex-row items-center gap-3">
      <ApplicationMark className="w-10 md:w-12" />
      {/* Hide the name when the screen is too small in compact mode */}
      <ApplicationName className="hidden sm:block sm:w-32 md:w-36" />
    </div>
  );

  const normalLogoAndMark = (
    <div className="flex h-full flex-col items-center gap-3 sm:flex-row">
      <ApplicationMark className="w-14 md:w-18 lg:w-24" />
      <ApplicationName className="w-40 lg:w-56" />
    </div>
  );

  const logoAndMark = props.compact ? compactLogoAndMark : normalLogoAndMark;

  return (
    <header
      className={cn(
        'flex flex-col flex-wrap items-center border-b border-border bg-well sm:justify-center md:flex-row md:justify-between',
        props.compact ? 'flex-row justify-between gap-3 px-4 py-3 sm:justify-between sm:px-6' : 'p-4 md:p-6 lg:p-12',
      )}
    >
      {props.addLinkOnLogo ? (
        <a href={HOME_URL} aria-label="Knowii home page" className="rounded-lg">
          {logoAndMark}
        </a>
      ) : (
        logoAndMark
      )}

      <nav aria-label="Main" className={cn('flex flex-row flex-wrap items-center justify-center gap-3', !props.compact && 'mt-6 md:mt-0')}>
        <ThemeToggle />
        {props.showDashboardButton && (
          <LinkButton
            href={route(DASHBOARD_URL)}
            preserveState={true}
            size={props.compact ? 'md' : 'lg'}
            className={cn(!props.compact && 'min-w-48')}
          >
            Dashboard
          </LinkButton>
        )}
        {props.showLogoutButton && (
          <LinkButton
            href={route(LOGOUT_URL)}
            method="post"
            variant="secondary"
            size={props.compact ? 'md' : 'lg'}
            iconOnly
            aria-label="Log out"
          >
            <FaSignOutAlt aria-hidden="true" />
          </LinkButton>
        )}
        {props.showLoginButton && (
          <LinkButton
            href={route(LOGIN_URL)}
            variant="secondary"
            size={props.compact ? 'md' : 'lg'}
            className={cn(!props.compact && 'min-w-48')}
          >
            Log in
          </LinkButton>
        )}
        {props.showRegisterButton && (
          <LinkButton href={route(REGISTER_URL)} size={props.compact ? 'md' : 'lg'} className={cn(!props.compact && 'min-w-48')}>
            Register
          </LinkButton>
        )}

        {props.children}
      </nav>
    </header>
  );
}
