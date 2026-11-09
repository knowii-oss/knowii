import ReactMarkdown from 'react-markdown';
import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import { Head } from '@inertiajs/react';
import FooterGuest from '@/Components/FooterGuest';
import PageWrapper from '@/Components/PageWrapper';

interface Props {
  policy: string;
}

export default function PrivacyPolicyPage(props: Props) {
  return (
    <>
      <Head title="Privacy Policy" />

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
              <ReactMarkdown>{props.policy}</ReactMarkdown>
            </div>
          </div>
          <FooterGuest />
        </PageContentWrapper>
      </PageWrapper>
    </>
  );
}
