import { PropsWithChildren } from 'react';
import { FcIdea } from 'react-icons/fc';

type BlogPostTipProps = PropsWithChildren<{}>;

/**
 * Tip embedded in blog posts
 * @constructor
 */
export function BlogPostTip({ children }: BlogPostTipProps) {
  return (
    <div className="bg-green-100 border-l-8 border-green-500 text-green-800 p-4 rounded-md" role="comment">
      <div className="font-bold mb-2">
        <FcIdea className="inline" size="24" />
        <span className="ml-2">TIP</span>
      </div>
      <div>{children}</div>
    </div>
  );
}

export default BlogPostTip;
