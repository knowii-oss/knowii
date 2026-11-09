import { useState } from 'react';
import { router } from '@inertiajs/react';
import type { Comment, Community, Post, PostPermissions, Space, SpaceGroup } from '@knowii/common';
import { COMMUNITY_URL, knowiiApiClient, SPACE_TYPE_LABELS, SPACE_URL, useAppData, useTypedPage } from '@knowii/common';
import { FaPen, FaThumbtack, FaTrash } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import { PostComposer } from '@/Components/Posts/PostComposer';
import { CommentSection } from '@/Components/Comments/CommentSection';
import { ReactionBar } from '@/Components/Reactions/ReactionBar';
import type { BreadcrumbItem } from '@/Components/ui';
import { Button, Dialog, MarkdownContent, RelativeTime } from '@/Components/ui';

interface Props {
  // WARNING: aligned with PostController.php
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  post: Post;
  comments: Comment[];
  permissions: PostPermissions;
}

export default function PostPage(props: Props) {
  const { community, space } = props;
  const { showToast } = useAppData();
  const page = useTypedPage();
  const [post, setPost] = useState<Post>(props.post);
  const [editing, setEditing] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [busy, setBusy] = useState(false);

  const spaceHref = route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug });

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: space.name, href: spaceHref, icon: <span aria-hidden="true">{space.emoji || SPACE_TYPE_LABELS[space.type].emoji}</span> },
    { label: post.title },
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

  const deletePost = async () => {
    setBusy(true);
    const result = await knowiiApiClient.communities.posts.delete(community.cuid, space.cuid, post.cuid);
    setBusy(false);
    if (result.ok) {
      showToast({ severity: 'success', summary: 'Post deleted' });
      router.visit(spaceHref);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  return (
    <AppLayout browserPageTitle={`${post.title} - ${space.name}`} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <CommunityShell community={community} spaceGroups={props.spaceGroups} activeSpaceCuid={space.cuid}>
        <article aria-labelledby="post-title" className="flex flex-col gap-4">
          <header className="flex flex-col gap-2 border-b border-border pb-4">
            {post.is_pinned && (
              <p className="flex items-center gap-1 text-xs font-bold text-secondary-text">
                <FaThumbtack aria-hidden="true" />
                Pinned
              </p>
            )}
            <h1 id="post-title" className="text-2xl font-extrabold">
              {post.title}
            </h1>
            <p className="text-sm text-muted">
              {post.author ? post.author.name : 'Deleted member'} · <RelativeTime date={post.created_at} />
              {post.edited_at && (
                <>
                  {' '}
                  · edited <RelativeTime date={post.edited_at} />
                </>
              )}
            </p>
            {(props.permissions.canUpdatePost || props.permissions.canDeletePost || props.permissions.canPinPost) && (
              <div className="flex flex-wrap gap-2">
                {props.permissions.canUpdatePost && (
                  <Button size="sm" variant="secondary" onClick={() => setEditing(true)}>
                    <FaPen aria-hidden="true" />
                    Edit
                  </Button>
                )}
                {props.permissions.canPinPost && (
                  <Button size="sm" variant="secondary" onClick={togglePin} loading={busy} aria-pressed={post.is_pinned}>
                    <FaThumbtack aria-hidden="true" />
                    {post.is_pinned ? 'Unpin' : 'Pin'}
                  </Button>
                )}
                {props.permissions.canDeletePost && (
                  <Button size="sm" variant="ghost" onClick={() => setConfirmDelete(true)}>
                    <FaTrash aria-hidden="true" />
                    Delete
                  </Button>
                )}
              </div>
            )}
          </header>

          <MarkdownContent html={post.body_html} />
          <ReactionBar
            target={{ communityCuid: community.cuid, spaceCuid: space.cuid, postCuid: post.cuid }}
            initialReactions={post.reactions}
            canReact={props.permissions.canComment}
            itemLabel="this post"
          />
        </article>

        <CommentSection
          communityCuid={community.cuid}
          spaceCuid={space.cuid}
          postCuid={post.cuid}
          initialComments={props.comments}
          currentUserCuid={page.props.auth.user?.cuid ?? null}
          canComment={props.permissions.canComment}
          canModerate={props.permissions.canModerate}
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
        size="sm"
        title="Delete this post?"
        description="The post and its comments are deleted. This cannot be undone."
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirmDelete(false)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={deletePost} loading={busy}>
              Delete
            </Button>
          </>
        }
      />
    </AppLayout>
  );
}
