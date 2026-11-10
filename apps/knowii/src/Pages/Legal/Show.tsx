import { Head } from '@inertiajs/react';

import PageHeader from '@/Components/PageHeader';
import PageContentWrapper from '@/Components/PageContentWrapper';
import Footer from '@/Components/Footer';
import PageWrapper from '@/Components/PageWrapper';
import { MarkdownContent } from '@/Components/ui';

export interface LegalPageProps {
  title: string;
  /**
   * Date of the last change (YYYY-MM-DD), from the front matter of the Markdown file
   */
  updated: string | null;
  /**
   * True while Sébastien has not approved the text
   */
  draft: boolean;
  /**
   * HTML rendered on the server from the Markdown file (App\Support\LegalPages, ADR 0013)
   */
  html: string;
}

/**
 * Every legal page (privacy policy, terms of service, imprint, DPA, content policy) uses this page (#904).
 */
export default function LegalPage({ title, updated, draft, html }: LegalPageProps) {
  return (
    <>
      <Head title={title} />

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
          <article className="readable-width mx-auto mt-6 w-full rounded-xl border border-border bg-surface p-6 md:p-8">
            {draft && (
              <p role="note" className="mb-6 rounded-lg border border-warning bg-warning/10 p-3 text-sm text-primary">
                Draft: this text is not final yet. It can change before it applies.
              </p>
            )}
            {updated && (
              <p className="mb-4 text-sm text-muted">
                Last updated: <time dateTime={updated}>{updated}</time>
              </p>
            )}
            <MarkdownContent html={html} className="prose-table:text-sm [&_table]:block [&_table]:overflow-x-auto" />
          </article>
          <Footer variant="compact" />
        </PageContentWrapper>
      </PageWrapper>
    </>
  );
}
