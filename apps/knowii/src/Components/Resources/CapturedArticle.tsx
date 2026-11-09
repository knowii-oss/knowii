import { FaBookOpen } from 'react-icons/fa6';
import { MarkdownContent, RelativeTime } from '@/Components/ui';

/**
 * The captured article of a resource (#1247), rendered by the server (ADR 0013: raw HTML escaped). WARNING: aligned
 * with CommunityResourceController::article()
 */
export interface CapturedArticleData {
  html: string;
  word_count: number;
  reading_time: number;
  source: 'server' | 'extension';
  captured_at: string;
}

interface Props {
  article: CapturedArticleData | null;
  // The excerpt of the page, shown when there is no article
  excerpt?: string | null | undefined;
}

/**
 * The readable copy of an article, for the members of the community (ADR 0018). Its images come from the Knowii
 * origin. Without a captured article, the excerpt of the page.
 */
export function CapturedArticle({ article, excerpt }: Props) {
  if (!article) {
    return excerpt ? (
      <section aria-labelledby="resource-excerpt-title" className="mt-6">
        <h2 id="resource-excerpt-title" className="mb-2 text-sm font-bold text-muted">
          Excerpt
        </h2>
        <p className="border-l-4 border-border pl-4 text-muted italic">{excerpt}</p>
      </section>
    ) : null;
  }

  return (
    <section aria-labelledby="captured-article-title" className="mt-8 border-t border-border pt-6">
      <h2 id="captured-article-title" className="flex items-center gap-2 text-lg font-extrabold">
        <FaBookOpen aria-hidden="true" className="text-secondary-text" />
        The article
      </h2>
      <p className="mt-1 text-xs text-muted">
        {article.reading_time} min read · {article.word_count.toLocaleString()} words · captured{' '}
        {article.source === 'extension' ? 'from a browser' : 'by Knowii'} <RelativeTime date={article.captured_at} /> · for the members of
        this community only
      </p>
      <MarkdownContent
        html={article.html}
        className="mt-6 break-words prose-pre:overflow-x-auto prose-table:block prose-table:overflow-x-auto"
      />
    </section>
  );
}
