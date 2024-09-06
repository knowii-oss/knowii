import PageHeader from '@/Components/PageHeader';
import { PageContentWrapper } from '@/Components/PageContentWrapper';
import { Head } from '@inertiajs/react';
import FooterGuest from '@/Components/FooterGuest';

export default function Contact() {
  return (
    <>
      <Head title="Contact Us" />

      <div className="bg-gray-50 full-page">
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
              <h1>Contact</h1>
              You can contact us at the following address: <a href="mailto:contact@knowii.net">contact@knowii.net</a>
            </div>
          </div>
          <FooterGuest />
        </PageContentWrapper>
      </div>
    </>
  );
}
