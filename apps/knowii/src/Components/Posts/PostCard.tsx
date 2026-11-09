import { Link } from '@inertiajs/react';
import type { Post } from '@knowii/common';
import { FaThumbtack } from 'react-icons/fa6';
import { Card, MarkdownContent, RelativeTime } from '@/Components/ui';

export interface PostCardProps {
  post: Post;
  href: string;
}

/**
 * A post in the list of a space: title (link to the post), author, date, the beginning of the text.
 */
export function PostCard({ post, href }: PostCardProps) {
  const headingId = `post-${post.cuid}-title`;
  return (
    <Card as="article" aria-labelledby={headingId} className="flex flex-col gap-2 p-4">
      <header className="flex flex-col gap-1">
        {post.is_pinned && (
          <p className="flex items-center gap-1 text-xs font-bold text-secondary-text">
            <FaThumbtack aria-hidden="true" />
            Pinned
          </p>
        )}
        <h2 id={headingId} className="text-lg font-extrabold">
          <Link href={href} preserveState={true} className="rounded hover:underline focus-visible:outline-2 focus-visible:outline-primary">
            {post.title}
          </Link>
        </h2>
        <p className="text-xs text-muted">
          {post.author ? post.author.name : 'Deleted member'} · <RelativeTime date={post.created_at} />
          {post.edited_at && ' · edited'}
          {post.comments_count !== undefined && ` · ${post.comments_count === 1 ? '1 comment' : `${post.comments_count} comments`}`}
        </p>
      </header>
      <MarkdownContent html={post.body_html} className="line-clamp-4 text-sm" />
    </Card>
  );
}
