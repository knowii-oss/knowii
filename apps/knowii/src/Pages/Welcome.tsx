import { Head } from '@inertiajs/react';
import { useTypedPage } from '@knowii/common';
import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import Footer from '@/Components/Footer';
import PageWrapper from '@/Components/PageWrapper';

interface Props {
  canLogin: boolean;
  canRegister: boolean;
}

export default function Welcome(props: Props) {
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
            <h1 className="text-primary-500">
              Coming soon&nbsp;
              <span role={'img'} aria-label="waving hand">
                👋
              </span>
            </h1>
            <h2 className="mt-4">
              Know<span className="text-primary-500">ii</span> is a place for your community's Knowledge, Ideas and Inspiration.
            </h2>
            <div className="mt-8 text-xl">Get ready for a revolutionary knowledge-sharing experience!</div>
          </div>

          {/*<div className="mt-16 flex flex-col items-center mt-8">*/}
          {/*  <button className="px-6 py-2 bg-primary-500 text-white rounded-full hover:bg-primary-600 transition-colors duration-300">*/}
          {/*    Join the waitlist*/}
          {/*  </button>*/}
          {/*</div>*/}
        </PageContentWrapper>
        <Footer />
      </PageWrapper>
    </>
  );
}
