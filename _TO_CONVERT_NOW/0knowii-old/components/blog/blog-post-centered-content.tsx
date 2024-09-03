import { PropsWithChildren } from 'react';

type BlogPostCenteredContentProps = PropsWithChildren<{}>;

/**
 * Centered content
 * @constructor
 */
export function BlogPostCenteredContent({ children }: BlogPostCenteredContentProps) {
  return <div className="flex justify-around ">{children}</div>;
}

export default BlogPostCenteredContent;
