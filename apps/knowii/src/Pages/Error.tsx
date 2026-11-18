import type { ReactNode } from 'react';
import { Head, usePage } from '@inertiajs/react';
import type { Auth } from '@knowii/common';
import { DASHBOARD_URL, HOME_URL } from '@knowii/common';
import { FaCompass, FaLock, FaScrewdriverWrench, FaTriangleExclamation } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import Footer from '@/Components/Footer';
import PageContentWrapper from '@/Components/PageContentWrapper';
import PageHeader from '@/Components/PageHeader';
import PageWrapper from '@/Components/PageWrapper';
import { ERROR_MEMBER_LINK, ERROR_VISITOR_LINK, errorTexts } from '@/Components/Errors/error-texts';
import { LinkButton, NoAccessPanel } from '@/Components/ui';

interface Props {
  /**
   * The HTTP status of the answer (bootstrap/app.php renders this page for 403, 404, 500 and 503 outside local and testing)
   */
  status: number;
}

const ICONS: Record<number, ReactNode> = {
  403: <FaLock />,
  404: <FaCompass />,
  503: <FaScrewdriverWrench />,
};

/**
 * The error page (#1574): the case in words, one line, and one way on. A signed-in person gets the normal layout with
 * the header of a member, and a link to their communities. A visitor gets the layout of the public pages and a link to
 * the home page. The shared props are missing when the request never reached the web middleware (maintenance mode, an
 * API address): the page then shows the visitor version. Reference: https://inertiajs.com/error-handling
 */
export default function ErrorPage({ status }: Props) {
  const auth = usePage<{ auth?: Auth }>().props.auth;
  const signedIn = Boolean(auth?.user);
  const texts = errorTexts(status);

  const panel = (
    <NoAccessPanel
      icon={ICONS[status] ?? <FaTriangleExclamation />}
      title={texts.title}
      reason={texts.reason}
      className="mx-auto mt-6 max-w-3xl"
    >
      {/* 44 px high on a phone (WCAG 2.5.8), like the other links outside running text */}
      <LinkButton href={signedIn ? route(DASHBOARD_URL) : HOME_URL} className="touch-target">
        {signedIn ? ERROR_MEMBER_LINK : ERROR_VISITOR_LINK}
      </LinkButton>
    </NoAccessPanel>
  );

  if (signedIn) {
    return (
      <AppLayout browserPageTitle={texts.title} breadcrumbHome={breadcrumbHome()} contentWidth="form">
        {panel}
      </AppLayout>
    );
  }

  return (
    <>
      <Head title={texts.title} />

      <PageWrapper>
        <PageHeader
          compact={true}
          addLinkOnLogo={true}
          showDashboardButton={false}
          showLogoutButton={false}
          showLoginButton={false}
          showRegisterButton={false}
        />

        <PageContentWrapper width="form">
          {panel}
          <Footer variant="compact" />
        </PageContentWrapper>
      </PageWrapper>
    </>
  );
}
