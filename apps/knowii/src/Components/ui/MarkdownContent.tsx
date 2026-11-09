import { cn } from './cn';

export interface MarkdownContentProps {
  /**
   * HTML rendered by the server from Markdown (App\Support\Markdown): raw HTML from members is escaped there.
   * Never pass HTML from another source.
   */
  html: string;
  className?: string;
  id?: string;
}

/**
 * Shows member content (posts, comments, messages) with the typography of the theme.
 */
export function MarkdownContent({ html, className, id }: MarkdownContentProps) {
  return (
    <div
      id={id}
      className={cn(
        'prose max-w-none text-primary dark:prose-invert',
        'prose-a:text-secondary-text prose-a:underline-offset-2 prose-code:before:content-none prose-code:after:content-none',
        'prose-img:rounded-lg',
        className,
      )}
      // Safe: the server renders the Markdown with raw HTML escaped and unsafe links removed (ADR 0013)
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
