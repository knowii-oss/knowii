import { Head } from '@inertiajs/react';
import { useTypedPage } from '@knowii/common';
import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import Footer from '@/Components/Footer';
import PageWrapper from '@/Components/PageWrapper';
import { useEffect } from 'react';

interface Props {
  canLogin: boolean;
  canRegister: boolean;
}

export default function WelcomePage(props: Props) {
  const page = useTypedPage();

  const showLogout = props.canLogin && page.props.auth.user !== null;
  const showDashboard = props.canLogin && page.props.auth.user !== null;
  const showLogin = props.canLogin && !page.props.auth.user;
  const showRegister = props.canLogin && !page.props.auth.user;

  useEffect(() => {
    const script = document.createElement('script');

    script.src = 'https://eocampaign1.com/form/ab92cdda-764d-11ef-9338-89ce4ce1395d.js';
    script.async = true;
    script.setAttribute('data-form', 'ab92cdda-764d-11ef-9338-89ce4ce1395d');

    document.getElementById('waitlist-container')?.appendChild(script);

    return () => {
      try {
        document.getElementById('waitlist-container')?.removeChild(script);
      } catch (e) {
        /* empty */
      }
    };
  }, []);

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
            <div className="mt-8 text-xl">
              Get ready for a <i>revolutionary</i> knowledge-sharing experience!
            </div>
          </div>

          <div id="waitlist-container" className="mt-20 flex flex-col items-center mt-8 border-primary-500 border-6"></div>
        </PageContentWrapper>
        <Footer />
      </PageWrapper>
    </>
  );
}
