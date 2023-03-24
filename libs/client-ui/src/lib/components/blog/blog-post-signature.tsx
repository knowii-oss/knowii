// FIXME replace once this is solved: https://github.com/PaulieScanlon/mdx-embed/issues/247
/// <reference types="../../../../../../mdx-embed" />
//import { TwitterFollowButton } from '../mdx-embed';
import { TwitterFollowButton } from 'mdx-embed/dist/components/twitter';

const authorBlog = require('../../../../../../libs/common/src/lib/metadata.json').social.blogSebastien;
const authorNewsletter = require('../../../../../../libs/common/src/lib/metadata.json').social.newsletterSebastien;
const authorTwitter = require('../../../../../../libs/common/src/lib/metadata.json').social.twitterSebastien;

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface BlogPostSignatureProps {}

/**
 * Signature for my blog posts
 * @constructor
 */
export function BlogPostSignature(_props: BlogPostSignatureProps) {
  return (
    <>
      <h2>About Sébastien</h2>
      <div className="mb-2">
        Hello everyone! I'm Sébastien Dubois. I'm an author, founder, and CTO. I write books and articles about software development & IT,
        personal knowledge management, personal organization, and productivity. I also craft lovely digital products 🚀
        <br />
        <br />
        If you've enjoyed this article and want to read more like this, then subscribe to <a href={authorNewsletter}>my newsletter</a>, and
        check out my <a href={authorBlog}>blog</a> 🔥.
        <br />
        <br />
        You can <a href={authorTwitter}>follow me on Twitter</a> 🐦
      </div>

      <TwitterFollowButton username="dSebastien" size="large" />
    </>
  );
}

export default BlogPostSignature;
