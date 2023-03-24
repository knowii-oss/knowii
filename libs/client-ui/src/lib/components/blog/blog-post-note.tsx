import { PropsWithChildren } from 'react';
import { RiErrorWarningLine } from 'react-icons/ri';

type BlogPostNoteProps = PropsWithChildren<{}>;

/**
 * Warning embedded in blog posts
 * @constructor
 */
export function BlogPostNote({ children }: BlogPostNoteProps) {
  return (
    <div className="bg-blue-100 border-l-8 border-blue-500 text-blue-800 p-4 rounded-md" role="alert">
      <div className="font-bold mb-2">
        <RiErrorWarningLine className="inline" size="24" />
        <span className="ml-2">NOTE</span>
      </div>
      <div>{children}</div>
    </div>
  );
}

export default BlogPostNote;
