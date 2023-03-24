import { PropsWithChildren } from 'react';
import { FaQuoteLeft } from 'react-icons/fa';

type BlogPostQuote = PropsWithChildren<{
  quote: string;
  by: string;
}>;

/**
 * Quote embedded in blog posts
 * @constructor
 */
export function BlogPostQuote({ quote, by }: BlogPostQuote) {
  return (
    <blockquote className="relative p-4 rounded-md text-xl italic border-l-8 bg-gray-100 border-gray-500 quote">
      <div className="stylistic-quote-mark" aria-hidden="true">
        <FaQuoteLeft className="inline" size="24" />
      </div>
      <p className="mb-4 text-pink-500">{quote}</p>
      <cite className="flex items-center text-gray-500">{by}</cite>
    </blockquote>
  );
}

export default BlogPostQuote;
