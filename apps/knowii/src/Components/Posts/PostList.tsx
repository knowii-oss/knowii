import { useState } from 'react';
import type { Community, Paginated, Post, Space } from '@knowii/common';
import { knowiiApiClient, POST_URL, POSTS_PER_PAGE, useAppData } from '@knowii/common';
import { FaPen } from 'react-icons/fa6';
import { Button, EmptyState } from '@/Components/ui';
import { PostCard } from './PostCard';
import { PostComposer } from './PostComposer';

export interface PostListProps {
  community: Community;
  space: Space;
  initialPosts: Paginated<Post>;
  canContribute: boolean;
  canReact: boolean;
}

/**
 * The posts of a discussion space, pinned first, with "Load more" and the composer for new posts.
 */
export function PostList({ community, space, initialPosts, canContribute, canReact }: PostListProps) {
  const { showToast } = useAppData();
  const [posts, setPosts] = useState<Post[]>(initialPosts.items);
  const [nextOffset, setNextOffset] = useState<number | null>(initialPosts.pagination.nextOffset);
  const [loading, setLoading] = useState(false);
  const [composerOpen, setComposerOpen] = useState(false);

  const href = (post: Post) => route(POST_URL, { communitySlug: community.slug, spaceSlug: space.slug, postSlug: post.slug });

  const loadMore = async () => {
    if (nextOffset === null) {
      return;
    }
    setLoading(true);
    const result = await knowiiApiClient.communities.posts.list(community.cuid, space.cuid, POSTS_PER_PAGE, nextOffset);
    setLoading(false);
    if (result.ok) {
      // A post can move between pages when new posts arrive: skip the ones already shown
      setPosts((current) => [...current, ...result.data.items.filter((post) => !current.some((shown) => shown.cuid === post.cuid))]);
      setNextOffset(result.data.pagination.nextOffset);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const onPublished = (post: Post) => {
    setPosts((current) => {
      const pinned = current.filter((item) => item.is_pinned);
      const others = current.filter((item) => !item.is_pinned);
      return [...pinned, post, ...others];
    });
  };

  return (
    <section aria-label={`Posts in ${space.name}`} className="flex flex-col gap-4">
      {canContribute && (
        <div className="flex justify-end">
          <Button onClick={() => setComposerOpen(true)}>
            <FaPen aria-hidden="true" />
            New post
          </Button>
        </div>
      )}

      {posts.length === 0 ? (
        <EmptyState
          icon="💬"
          title="No posts yet"
          description={canContribute ? 'Start the conversation: write the first post.' : 'Posts and their comments will show here.'}
        />
      ) : (
        <ul className="flex flex-col gap-3">
          {posts.map((post) => (
            <li key={post.cuid}>
              <PostCard post={post} href={href(post)} communityCuid={community.cuid} canReact={canReact} />
            </li>
          ))}
        </ul>
      )}

      {nextOffset !== null && (
        <div className="flex justify-center">
          <Button variant="secondary" onClick={loadMore} loading={loading}>
            Load more posts
          </Button>
        </div>
      )}

      <PostComposer
        open={composerOpen}
        communityCuid={community.cuid}
        spaceCuid={space.cuid}
        spaceName={space.name}
        onClose={() => setComposerOpen(false)}
        onSaved={onPublished}
      />
    </section>
  );
}
