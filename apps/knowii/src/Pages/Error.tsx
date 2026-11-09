import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import { Head } from '@inertiajs/react';
import Footer from '@/Components/Footer';
import { HOME_URL } from '@knowii/common';
import { buttonClasses } from '@/Components/ui';
import { FaHome } from 'react-icons/fa';
import PageWrapper from '@/Components/PageWrapper';

interface Props {
  status: string;
}

// Generic error page
// Reference: // https://inertiajs.com/error-handling
export default function ErrorPage(props: Props) {
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

      <PageWrapper>
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
            <div className="prose dark:prose-invert mt-6 w-full max-w-none overflow-hidden rounded-xl border border-border bg-surface p-6 prose-a:text-primary prose-a:underline-offset-4 md:w-3/4 md:p-8 xl:w-1/2">
              <h1>{errorTitle}</h1>
              <h2>{errorDescription}</h2>
              <div className="mt-12 flex flex-row justify-center">
                <a href={HOME_URL} className={buttonClasses({ size: 'lg' }) + ' no-underline'}>
                  <FaHome aria-hidden="true" />
                  Go back to the homepage
                </a>
              </div>
              <p className="mt-12 text-center">
                If the problem persists, you can contact us at the following address:{' '}
                <a href="mailto:contact@knowii.net">contact@knowii.net</a>
              </p>
            </div>
          </div>
          <Footer variant="compact" />
        </PageContentWrapper>
      </PageWrapper>
    </>
  );
}
