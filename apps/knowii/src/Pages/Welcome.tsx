import { Head } from '@inertiajs/react';
import { useTypedPage } from '@knowii/common';
import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import Footer from '@/Components/Footer';
import PageWrapper from '@/Components/PageWrapper';
import WaitlistForm from '@/Components/Waitlist/WaitlistForm';

interface Props {
  canLogin: boolean;
  canRegister: boolean;
  waitlist: {
    enabled: boolean;
    turnstileSiteKey: string | null;
  };
}

export default function WelcomePage(props: Props) {
  const page = useTypedPage();

  const showLogout = props.canLogin && page.props.auth.user !== null;
  const showDashboard = props.canLogin && page.props.auth.user !== null;
  const showLogin = props.canLogin && !page.props.auth.user;
  const showRegister = props.canLogin && !page.props.auth.user;

  return (
    <>
      <Head title="Welcome" />

      <PageWrapper>
        <PageHeader
          compact={false}
          addLinkOnLogo={false}
          showDashboardButton={showDashboard}
          showLogoutButton={showLogout}
          showLoginButton={showLogin}
          showRegisterButton={showRegister}
        />

        <PageContentWrapper>
          <div className="flex flex-col items-center">
            <h1 className="text-center">
              <span className="text-gradient-brand">Coming soon</span>&nbsp;
              <span role={'img'} aria-label="waving hand">
                👋
              </span>
            </h1>
            <h2 className="mt-6 text-center">
              Know<span className="text-secondary-text">ii</span> is a place for your community's Knowledge, Ideas and Inspiration.
            </h2>
            <div className="mt-8 text-center text-xl text-muted">
              Get ready for a <i>revolutionary</i> knowledge-sharing experience!
            </div>
          </div>

          {props.waitlist.enabled && props.waitlist.turnstileSiteKey && (
            <div className="mt-16 flex flex-col items-center">
              <WaitlistForm turnstileSiteKey={props.waitlist.turnstileSiteKey} />
            </div>
          )}
        </PageContentWrapper>
        <Footer />
      </PageWrapper>
    </>
  );
}
