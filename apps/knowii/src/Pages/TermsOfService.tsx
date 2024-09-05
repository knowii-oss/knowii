import React from 'react';
import PageHeader from '@/Components/PageHeader';
import { PageContentWrapper } from '@/Components/PageContentWrapper';
import { Head } from '@inertiajs/react';
import FooterGuest from '@/Components/FooterGuest';

interface Props {
  terms: string;
}

export default function TermsOfService(props: Props) {
  return (
    <>
      <Head title="Terms of Service" />

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
            <div
              className="min-w-full md:min-w-[75%] xl:min-w-[50%] p-6 mt-6 overflow-hidden prose bg-white shadow-md sm:max-w-2xl sm:rounded-lg"
              dangerouslySetInnerHTML={{ __html: props.terms }}
            />
          </div>
          <FooterGuest />
        </PageContentWrapper>
      </div>
    </>
  );
}
