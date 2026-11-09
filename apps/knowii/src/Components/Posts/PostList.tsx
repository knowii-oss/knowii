import { useCallback, useMemo, useRef, useState } from 'react';
import type { Community, Paginated, Post, Space, SpaceContentPayload } from '@knowii/common';
import { knowiiApiClient, POST_URL, POSTS_PER_PAGE, useAppData, useSocket } from '@knowii/common';
import { FaArrowUp, FaPen } from 'react-icons/fa6';
import { Button, EmptyState, ErrorBoundary } from '@/Components/ui';
import { PostCard } from './PostCard';
import { PostComposer } from './PostComposer';

export interface PostListProps {
  community: Community;
  space: Space;
  initialPosts: Paginated<Post>;
  canContribute: boolean;
  canReact: boolean;
  /**
   * The signed-in member: their own new posts are already in the list (no "new posts" button for them)
   */
  currentUserCuid?: string | null;
}

/**
 * The posts of a discussion space, pinned first, with "Load more" and the composer for new posts.
 */
export function PostList({ community, space, initialPosts, canContribute, canReact, currentUserCuid = null }: PostListProps) {
  const { showToast } = useAppData();
  const [posts, setPosts] = useState<Post[]>(initialPosts.items);
  const [nextOffset, setNextOffset] = useState<number | null>(initialPosts.pagination.nextOffset);
  const [loading, setLoading] = useState(false);
  const [composerOpen, setComposerOpen] = useState(false);

  // Live updates (#1180). New posts wait behind a button, so the list never moves under the reader
  const [newPosts, setNewPosts] = useState<string[]>([]);
  const sectionRef = useRef<HTMLElement>(null);
  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );

  const refreshPost = useCallback(
    async (postCuid: string) => {
      const result = await knowiiApiClient.communities.posts.get(community.cuid, space.cuid, postCuid);
      if (result.ok) {
        setPosts((current) => current.map((post) => (post.cuid === postCuid ? result.data : post)));
      }
    },
    [community.cuid, space.cuid],
  );

  useSocket({
    channel,
    event: [
      'space.post.created',
      'space.post.updated',
      'space.post.deleted',
      'space.comment.created',
      'space.comment.deleted',
      'space.reactions.changed',
    ],
    callback: (event, payload) => {
      const { post_cuid: postCuid, comment_cuid: commentCuid, author_cuid: authorCuid } = payload as SpaceContentPayload;
      if (!postCuid) {
        return;
      }
      if (event === 'space.post.created') {
        if (authorCuid !== currentUserCuid) {
          setNewPosts((current) => (current.includes(postCuid) ? current : [...current, postCuid]));
        }
        return;
      }
      if (event === 'space.post.deleted') {
        setPosts((current) => current.filter((post) => post.cuid !== postCuid));
        setNewPosts((current) => current.filter((cuid) => cuid !== postCuid));
        return;
      }
      // The reactions of a comment do not show in the list
      if (event === 'space.reactions.changed' && commentCuid) {
        return;
      }
      // Edited, pinned, its reactions or its number of comments changed: load it again if it is shown
      setPosts((current) => {
        if (current.some((post) => post.cuid === postCuid)) {
          void refreshPost(postCuid);
        }
        return current;
      });
    },
  });

  const showNewPosts = async () => {
    const result = await knowiiApiClient.communities.posts.list(community.cuid, space.cuid, POSTS_PER_PAGE, 0);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    // The first page again, then the posts already loaded below it
    setPosts((current) => [...result.data.items, ...current.filter((post) => !result.data.items.some((item) => item.cuid === post.cuid))]);
    setNewPosts([]);
    // The button goes away: keyboard users continue on the first post instead of losing the focus
    requestAnimationFrame(() => sectionRef.current?.querySelector<HTMLElement>('article a')?.focus());
  };

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
    <section ref={sectionRef} aria-label={`Posts in ${space.name}`} className="flex flex-col gap-4">
      {canContribute && (
        <div className="flex justify-end">
          <Button onClick={() => setComposerOpen(true)}>
            <FaPen aria-hidden="true" />
            New post
          </Button>
        </div>
      )}

      <div role="status" aria-live="polite" className="flex justify-center empty:hidden">
        {newPosts.length > 0 && (
          <Button variant="secondary" size="sm" onClick={() => void showNewPosts()}>
            <FaArrowUp aria-hidden="true" />
            {newPosts.length === 1 ? '1 new post' : `${newPosts.length} new posts`}
          </Button>
        )}
      </div>

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
              <ErrorBoundary variant="block" label={`the post "${post.title}"`}>
                <PostCard post={post} href={href(post)} communityCuid={community.cuid} canReact={canReact} />
              </ErrorBoundary>
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
