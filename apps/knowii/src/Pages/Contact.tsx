import { Head } from '@inertiajs/react';

import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import FooterGuest from '@/Components/FooterGuest';
import PageWrapper from '@/Components/PageWrapper';

export default function ContactPage() {
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
              You can contact us at the following address: <a href="mailto:contact@knowii.net">contact@knowii.net</a>
            </div>
          </div>
          <FooterGuest />
        </PageContentWrapper>
      </PageWrapper>
    </>
  );
}
