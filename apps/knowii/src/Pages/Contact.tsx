import { Head } from '@inertiajs/react';
import { useTypedPage } from '@knowii/common';

import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import Footer from '@/Components/Footer';
import PageWrapper from '@/Components/PageWrapper';

/**
 * The contact page shows the contact address of the instance (#1653), from the shared prop `instance`. The route answers
 * 404 when the instance gives no address.
 */
export default function ContactPage() {
  const contactEmail = useTypedPage().props.instance?.contactEmail ?? '';

  return (
    <>
      <Head title="Contact Us" />

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
              <h1>Contact</h1>
              {contactEmail !== '' && (
                <>
                  You can contact us at the following address: <a href={`mailto:${contactEmail}`}>{contactEmail}</a>
                </>
              )}
            </div>
          </div>
          <Footer variant="compact" />
        </PageContentWrapper>
      </PageWrapper>
    </>
  );
}
