import { Head } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { DASHBOARD_URL, LOGIN_URL, REGISTER_URL, useTypedPage } from '@knowii/common';
import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import Footer from '@/Components/Footer';
import PageWrapper from '@/Components/PageWrapper';
import { LinkButton } from '@/Components/ui';
import {
  HOME_HEADING,
  HOME_LINES,
  HOME_OPEN_DASHBOARD,
  HOME_SIGN_IN,
  HOME_SIGN_UP,
  HOME_SIGN_UP_CLOSED,
  HOME_TITLE,
} from '@/Components/Home/home-texts';

interface Props {
  canLogin: boolean;
  /**
   * False when the instance does not take new accounts (the Fortify registration feature is off)
   */
  canRegister: boolean;
}

/**
 * The home page (#1574): what Knowii is, what a person can do, and the way in. A visitor gets "Sign up" and "Sign in", a
 * signed-in person gets the dashboard.
 */
export default function WelcomePage({ canLogin, canRegister }: Props) {
  const page = useTypedPage();
  const route = useRoute();
  const signedIn = page.props.auth.user !== null;

  return (
    <>
      <Head title={HOME_TITLE} />

      <PageWrapper>
        <PageHeader
          compact={false}
          addLinkOnLogo={false}
          showDashboardButton={false}
          showLogoutButton={signedIn}
          accountCuid={page.props.auth.user?.cuid}
          showLoginButton={false}
          showRegisterButton={false}
        />

        <PageContentWrapper>
          <div className="readable-width mx-auto flex flex-col items-center gap-6 py-8 text-center md:py-16">
            <h1 className="text-3xl leading-tight break-words sm:text-4xl lg:text-5xl">
              <span className="text-gradient-brand">{HOME_HEADING}</span>
            </h1>
            <div className="flex flex-col gap-2 text-lg text-muted">
              {HOME_LINES.map((line) => (
                <p key={line}>{line}</p>
              ))}
            </div>

            {signedIn ? (
              <LinkButton href={route(DASHBOARD_URL)} size="lg" className="mt-2">
                {HOME_OPEN_DASHBOARD}
              </LinkButton>
            ) : (
              <div className="mt-2 flex flex-col items-center gap-4">
                {!canRegister && <p className="text-base">{HOME_SIGN_UP_CLOSED}</p>}
                <div className="flex flex-col items-center gap-3 sm:flex-row">
                  {canRegister && (
                    <LinkButton href={route(REGISTER_URL)} size="lg" className="min-w-48">
                      {HOME_SIGN_UP}
                    </LinkButton>
                  )}
                  {canLogin && (
                    <LinkButton href={route(LOGIN_URL)} variant="secondary" size="lg" className="min-w-48">
                      {HOME_SIGN_IN}
                    </LinkButton>
                  )}
                </div>
              </div>
            )}
          </div>
        </PageContentWrapper>
        <Footer />
      </PageWrapper>
    </>
  );
}
