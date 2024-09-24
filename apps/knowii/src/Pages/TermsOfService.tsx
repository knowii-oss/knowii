import ReactMarkdown from 'react-markdown';
import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import { Head } from '@inertiajs/react';
import FooterGuest from '@/Components/FooterGuest';
import PageWrapper from '@/Components/PageWrapper';

interface Props {
  terms: string;
}

export default function TermsOfServicePage(props: Props) {
  return (
    <>
      <Head title="Terms of Service" />

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
            <div className="min-w-full md:min-w-[75%] xl:min-w-[50%] p-6 mt-6 overflow-hidden prose bg-white shadow-md sm:max-w-2xl sm:rounded-lg">
              <ReactMarkdown>{props.terms}</ReactMarkdown>
            </div>
          </div>
          <FooterGuest />
        </PageContentWrapper>
      </PageWrapper>
    </>
  );
}
