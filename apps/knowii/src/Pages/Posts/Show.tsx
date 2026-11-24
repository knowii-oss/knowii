import { useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { Comment, Community, Pagination, Post, PostPermissions, Space, SpaceGroup, SpaceContentPayload } from '@knowii/common';
import {
  COMMUNITY_URL,
  POST_URL,
  knowiiApiClient,
  SPACE_TYPE_LABELS,
  SPACE_URL,
  useAppData,
  useSocket,
  useTypedPage,
} from '@knowii/common';
import { FaPen, FaThumbtack, FaTrash } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import { PostComposer } from '@/Components/Posts/PostComposer';
import { CommentSection } from '@/Components/Comments/CommentSection';
import { countAllComments, postBecomesMarker, postDeletionLine } from '@/Components/Comments/delete-rules';
import { ReactionBar } from '@/Components/Reactions/ReactionBar';
import { ThreadFollowControl } from '@/Components/Notifications/ThreadFollowControl';
import type { BreadcrumbItem } from '@/Components/ui';
import { Button, Checkbox, cn, Dialog, ErrorBoundary, MarkdownContent, RelativeTime, useFocusAfterRender } from '@/Components/ui';

interface Props {
  // WARNING: aligned with PostController.php
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  post: Post;
  comments: Comment[];
  // The first page of the comments (ADR 0073)
  commentsPagination: Pagination;
  permissions: PostPermissions;
}

export default function PostPage(props: Props) {
  const { community, space } = props;
  const { showToast } = useAppData();
  const page = useTypedPage();
  const [post, setPost] = useState<Post>(props.post);
  const [editing, setEditing] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  // "Also delete the comments (N)": the moderators of the space (#1565)
  const [deleteComments, setDeleteComments] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [busy, setBusy] = useState(false);
  // The comments of the page, for the delete dialog (#1565)
  const [comments, setComments] = useState<Comment[]>(props.comments);
  // Every comment is loaded, and the number of every comment of the post (ADR 0073: the comments are paged)
  const [commentsState, setCommentsState] = useState({
    complete: props.commentsPagination.nextOffset === null,
    total: props.commentsPagination.totalCount,
  });
  // After a delete that leaves a marker, the focus goes to its title once the dialog is closed (#1565)
  const focusTitleAfterClose = useRef(false);
  const focusAfterRender = useFocusAfterRender();
  const currentUserCuid = page.props.auth.user?.cuid ?? null;
  // The marker of a deleted post that still has comments of other people (#1344, #1565)
  const isMarker = post.deleted_at !== null;
  const postTitle = isMarker ? 'This post was deleted' : post.title;
  // Nobody edits, pins or comments a marker; only a moderator deletes it, with its comments. The same rules as the
  // policies, so that the page is right without a reload after a delete
  const permissions: PostPermissions = isMarker
    ? { ...props.permissions, canUpdatePost: false, canDeletePost: false, canPinPost: false, canComment: false }
    : props.permissions;
  const canDelete = permissions.canDeletePost || (isMarker && permissions.canDeletePostWithComments);
  const deletesComments = isMarker || (permissions.canDeletePostWithComments && deleteComments);
  const commentCount = commentsState.complete ? countAllComments(comments) : commentsState.total;

  const spaceHref = route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug });

  /**
   * Shows the post as it is now. A marker has a new slug (#1565): the address follows, so that a reload finds it
   */
  const showPost = (next: Post) => {
    setPost(next);
    if (next.slug !== post.slug) {
      router.replace({
        url: route(POST_URL, { communitySlug: community.slug, spaceSlug: space.slug, postSlug: next.slug }),
        props: (current) => ({ ...current, post: next }),
        preserveState: true,
        preserveScroll: true,
      });
    }
  };

  // Live updates (#1180): the post changed elsewhere. Not while the member edits it: their save wins.
  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );
  useSocket({
    channel,
    // A visitor gets a static page: no connection (#1387)
    enabled: !!page.props.auth.user,
    event: ['space.post.updated', 'space.post.deleted', 'space.reactions.changed'],
    callback: (event, payload) => {
      const data = payload as SpaceContentPayload;
      if (data.post_cuid !== post.cuid || (event === 'space.reactions.changed' && data.comment_cuid)) {
        return;
      }
      // The own delete of the reader leaves the page already: its event does not come back to this page
      if (event === 'space.post.deleted') {
        showToast({ severity: 'info', summary: 'This post was deleted' });
        router.visit(spaceHref);
        return;
      }
      if (editing) {
        return;
      }
      void knowiiApiClient.communities.posts.get(community.cuid, space.cuid, post.cuid).then((result) => {
        if (result.ok) {
          // Edited, pinned, or now a marker
          showPost(result.data);
        }
      });
    },
  });

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: space.name, href: spaceHref, icon: <span aria-hidden="true">{space.emoji || SPACE_TYPE_LABELS[space.type].emoji}</span> },
    { label: postTitle },
  ];

  const togglePin = async () => {
    setBusy(true);
    const result = await knowiiApiClient.communities.posts.pin(community.cuid, space.cuid, post.cuid, !post.is_pinned);
    setBusy(false);
    if (result.ok) {
      setPost((current) => ({ ...current, is_pinned: result.data.is_pinned, pinned_at: result.data.pinned_at }));
      showToast({ severity: 'success', summary: result.data.is_pinned ? 'Post pinned' : 'Post unpinned' });
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const askDeletion = () => {
    setDeleteComments(false);
    setConfirmDelete(true);
  };

  const deletePost = async () => {
    if (deleting) {
      return;
    }
    const withComments = deletesComments;
    setDeleting(true);
    const result = await knowiiApiClient.communities.posts.delete(
      community.cuid,
      space.cuid,
      post.cuid,
      withComments ? { delete_comments: true } : {},
    );
    if (!result.ok) {
      setDeleting(false);
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    // A marker stays for the comments of other people (#1565): the person stays here and sees it with the discussion
    if (postBecomesMarker(post, comments, currentUserCuid, withComments, commentsState.complete)) {
      const marker = await knowiiApiClient.communities.posts.get(community.cuid, space.cuid, post.cuid);
      setDeleting(false);
      if (marker.ok && marker.data.deleted_at !== null) {
        showPost(marker.data);
        focusTitleAfterClose.current = true;
        setConfirmDelete(false);
        showToast({ severity: 'success', summary: 'Post deleted' });
        return;
      }
    }
    setDeleting(false);
    showToast({ severity: 'success', summary: 'Post deleted' });
    router.visit(spaceHref);
  };

  const afterDeletionDialog = () => {
    setDeleteComments(false);
    if (focusTitleAfterClose.current) {
      focusTitleAfterClose.current = false;
      // After the unmount of the dialog: Headless UI then gives the focus to the last element it knew, in a microtask
      window.setTimeout(() => focusAfterRender(() => document.getElementById('post-title')), 0);
    }
  };

  return (
    <AppLayout browserPageTitle={`${postTitle} - ${space.name}`} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <CommunityShell community={community} spaceGroups={props.spaceGroups} activeSpaceCuid={space.cuid} contentWidth="readable">
        <article aria-labelledby="post-title" className="flex flex-col gap-4">
          <header className="flex flex-col gap-2 border-b border-border pb-4">
            {post.is_pinned && (
              <p className="flex items-center gap-1 text-xs font-bold text-secondary-text">
                <FaThumbtack aria-hidden="true" />
                Pinned
              </p>
            )}
            {/* The focus goes to the title of the marker after the delete (#1565) */}
            <h1
              id="post-title"
              tabIndex={-1}
              className={cn(
                'rounded-sm text-2xl font-extrabold outline-none focus:outline-2 focus:outline-offset-2 focus:outline-solid focus:outline-primary',
                isMarker && 'text-muted italic',
              )}
            >
              {postTitle}
            </h1>
            {!isMarker && (
              <p className="text-sm text-muted">
                {post.author ? post.author.name : 'Deleted member'} · <RelativeTime date={post.created_at} />
                {post.edited_at && (
                  <>
                    {' '}
                    · edited <RelativeTime date={post.edited_at} />
                  </>
                )}
              </p>
            )}
            {/* The state of the thread for the member, with Follow, Stop following and Mute this thread (#1497) */}
            {!isMarker && community.is_member === true && (
              <ThreadFollowControl communityCuid={community.cuid} spaceCuid={space.cuid} kind="post" cuid={post.cuid} />
            )}
            {(permissions.canUpdatePost || canDelete || permissions.canPinPost) && (
              <div className="flex flex-wrap gap-2">
                {permissions.canUpdatePost && (
                  <Button size="sm" variant="secondary" onClick={() => setEditing(true)}>
                    <FaPen aria-hidden="true" />
                    Edit
                  </Button>
                )}
                {permissions.canPinPost && (
                  <Button size="sm" variant="secondary" onClick={togglePin} loading={busy} aria-pressed={post.is_pinned}>
                    <FaThumbtack aria-hidden="true" />
                    {post.is_pinned ? 'Unpin' : 'Pin'}
                  </Button>
                )}
                {canDelete && (
                  <Button size="sm" variant="ghost" onClick={askDeletion}>
                    <FaTrash aria-hidden="true" />
                    Delete
                  </Button>
                )}
              </div>
            )}
          </header>

          {!isMarker && (
            <ErrorBoundary variant="block" label="this post">
              <MarkdownContent html={post.body_html} />
              <ReactionBar
                target={{ communityCuid: community.cuid, spaceCuid: space.cuid, postCuid: post.cuid }}
                initialReactions={post.reactions}
                canReact={permissions.canComment}
                itemLabel="this post"
              />
            </ErrorBoundary>
          )}
        </article>

        <CommentSection
          subject={{ communityCuid: community.cuid, spaceCuid: space.cuid, postCuid: post.cuid }}
          initialComments={props.comments}
          initialPagination={props.commentsPagination}
          currentUserCuid={currentUserCuid}
          canComment={permissions.canComment}
          canModerate={permissions.canModerate}
          onCommentsChange={(next, complete, total) => {
            setComments(next);
            setCommentsState({ complete, total });
          }}
        />
      </CommunityShell>

      <PostComposer
        open={editing}
        communityCuid={community.cuid}
        spaceCuid={space.cuid}
        spaceName={space.name}
        post={post}
        onClose={() => setEditing(false)}
        onSaved={setPost}
      />

      <Dialog
        open={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        onAfterClose={afterDeletionDialog}
        size="sm"
        title="Delete this post?"
        description={[postDeletionLine(post, comments, currentUserCuid, deletesComments, commentsState.complete), 'This cannot be undone.']
          .filter((line) => line !== null)
          .join(' ')}
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirmDelete(false)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={deletePost} loading={deleting}>
              Delete
            </Button>
          </>
        }
      >
        {/* A moderator chooses to delete the comments of other people too (#1565). A marker always goes with them. */}
        {!isMarker && permissions.canDeletePostWithComments && commentCount > 0 ? (
          <label className="flex cursor-pointer items-start gap-3 text-sm">
            <Checkbox
              checked={deleteComments}
              onChange={(event) => {
                // Busy: the choice stays as it was sent
                if (!deleting) {
                  setDeleteComments(event.target.checked);
                }
              }}
            />
            <span className="pt-0.5">Also delete the comments ({commentCount})</span>
          </label>
        ) : null}
      </Dialog>
    </AppLayout>
  );
}
