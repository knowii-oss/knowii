import { PropsWithChildren } from 'react';
import { RiErrorWarningLine } from 'react-icons/ri';

type BlogPostWarningProps = PropsWithChildren<{}>;

/**
 * Warning embedded in blog posts
 * @constructor
 */
export function BlogPostWarning({ children }: BlogPostWarningProps) {
  return (
    <div className="bg-orange-100 border-l-8 border-orange-500 text-orange-800 p-4 rounded-md" role="alert">
      <div className="font-bold mb-2">
        <RiErrorWarningLine className="inline" size="24" />
        <span className="ml-2">WARNING</span>
      </div>
      <div>{children}</div>
    </div>
  );
}

export default BlogPostWarning;
