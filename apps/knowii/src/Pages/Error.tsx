import PageHeader from '@/Components/PageHeader';
import { PageContentWrapper } from '@/Components/PageContentWrapper';
import { Head, Link } from '@inertiajs/react';
import FooterGuest from '@/Components/FooterGuest';
import { HOME_URL, LOGOUT_URL } from '@knowii/common';
import { Button } from 'primereact/button';
import classNames from 'classnames';
import { FaHome, FaSignOutAlt } from 'react-icons/fa';
import { useRoute } from 'ziggy-js';

interface Props {
  status: string;
}

export default function Contact(props: Props) {
  const route = useRoute();

  const errorTitle = {
    503: '503: Service Unavailable',
    500: '500: Server Error',
    404: '404: Page Not Found',
    403: '403: Forbidden',
  }[props.status];

  const errorDescription = {
    503: 'Sorry, we are doing some maintenance. Please check back soon. 😔',
    500: 'Whoops, something went wrong on our servers. 😔',
    404: 'Sorry, the page you are looking for could not be found. 😭',
    403: 'Sorry, you are forbidden from accessing this page. ⛔',
  }[props.status];

  return (
    <>
      <Head title={`Error: ${errorTitle}`} />

      <div className="page-wrapper">
        <PageHeader
          compact={true}
          addLinkOnLogo={true}
          showDashboardButton={false}
          showLogoutButton={false}
          showLoginButton={false}
          showRegisterButton={false}
        />

        <PageContentWrapper>
          <div className="flex flex-col items-center">
            <div className="min-w-full md:min-w-[75%] xl:min-w-[50%] p-6 mt-6 overflow-hidden prose bg-white shadow-md sm:max-w-2xl sm:rounded-lg">
              <h1>{errorTitle}</h1>
              <h2>{errorDescription}</h2>
              <div className="mt-12 flex flex-row justify-center">
                <a href={HOME_URL} className="">
                  <Button aria-label={'Go back to the homepage'} severity="primary" className="">
                    <FaHome />
                    &nbsp;Go back to the homepage
                  </Button>
                </a>
              </div>
              <p className="mt-12 text-center">
                If the problem persists, you can contact us at the following address:{' '}
                <a href="mailto:contact@knowii.net">contact@knowii.net</a>
              </p>
            </div>
          </div>
          <FooterGuest />
        </PageContentWrapper>
      </div>
    </>
  );
}
