import { Head } from '@inertiajs/react';
import { useTypedPage } from '@knowii/common';
import ApplicationHeader from '@/Components/ApplicationHeader';
import { PageContentWrapper } from '@/Components/PageContentWrapper';

interface WelcomePageProps {
  canLogin: boolean;
  canRegister: boolean;
}

export default function Welcome(props: WelcomePageProps) {
  const page = useTypedPage();

  const showLogout = props.canLogin && page.props.auth.user !== null;
  const showDashboard = props.canLogin && page.props.auth.user !== null;
  const showLogin = props.canLogin && !page.props.auth.user;
  const showRegister = props.canLogin && !page.props.auth.user;

  return (
    <>
      <Head title="Welcome" />

      <div className="bg-gray-50 full-page">
        <ApplicationHeader
          addLinkOnLogo={false}
          showDashboardButton={showDashboard}
          showLogoutButton={showLogout}
          showLoginButton={showLogin}
          showRegisterButton={showRegister}
        />

        <PageContentWrapper>
          <div className="flex flex-col items-center">
            <h1 className="text-primary-500">Welcome&nbsp;👋</h1>
            <h2 className="mt-4">
              Know<span className="text-primary-500">ii</span> is a place for your community's Knowledge, Ideas and
              Inspiration.
            </h2>
          </div>

          <div className="mt-16 flex flex-row justify-center text-5xl font-bold">Coming soon...</div>
        </PageContentWrapper>
      </div>
    </>
  );
}
