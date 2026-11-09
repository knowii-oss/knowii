import { Link } from '@inertiajs/react';
import type { Community, Paginated, Post, Space, SpaceGroup, SpacePermissions } from '@knowii/common';
import { COMMUNITY_SETTINGS_URL, COMMUNITY_URL, SPACE_POSTING_POLICY_LABELS, SPACE_TYPE_LABELS } from '@knowii/common';
import { FaGear } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { buttonClasses, EmptyState } from '@/Components/ui';
import { PostList } from '@/Components/Posts/PostList';

interface Props {
  // WARNING: aligned with SpaceController.php
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  permissions: SpacePermissions;
  // Discussion spaces only: the first page of posts
  posts: Paginated<Post> | null;
}

const EMPTY_STATES: Record<Space['type'], { title: string; description: string }> = {
  discussion: { title: 'No posts yet', description: 'Posts and their comments will show here.' },
  chat: { title: 'No messages yet', description: 'Messages and threads will show here, in real time.' },
  pages: { title: 'No pages yet', description: 'The pages of this space will show here, in order.' },
  course: { title: 'No lessons yet', description: 'The lessons of this course will show here, in order.' },
};

export default function SpacePage(props: Props) {
  const { community, space } = props;
  const typeLabel = SPACE_TYPE_LABELS[space.type];

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: space.name, icon: <span aria-hidden="true">{space.emoji || typeLabel.emoji}</span> },
  ];

  return (
    <AppLayout
      browserPageTitle={`${space.name} - ${community.name}`}
      breadcrumbItems={breadcrumbItems}
      breadcrumbHome={breadcrumbHome()}
      pageActions={
        props.permissions.canManageSpaces ? (
          <Link
            href={route(COMMUNITY_SETTINGS_URL, { communitySlug: community.slug })}
            preserveState={true}
            className={buttonClasses({ variant: 'ghost', size: 'sm' })}
          >
            <FaGear aria-hidden="true" />
            Manage spaces
          </Link>
        ) : null
      }
    >
      <CommunityShell community={community} spaceGroups={props.spaceGroups} activeSpaceCuid={space.cuid}>
        <header className="mb-6 flex flex-col gap-2 border-b border-border pb-4">
          <h1 className="flex items-center gap-3 text-2xl font-extrabold">
            <span aria-hidden="true">{space.emoji || typeLabel.emoji}</span>
            {space.name}
          </h1>
          {space.description && <p className="text-muted">{space.description}</p>}
          <p className="flex flex-wrap gap-2 text-xs text-muted">
            <span className="rounded-full border border-border px-2 py-0.5">{typeLabel.name}</span>
            <span className="rounded-full border border-border px-2 py-0.5">
              {space.visibility === 'public' ? 'Public' : 'Members only'}
            </span>
            {space.posting_policy === 'moderators' && (
              <span className="rounded-full border border-border px-2 py-0.5">{SPACE_POSTING_POLICY_LABELS.moderators}</span>
            )}
          </p>
        </header>

        {space.type === 'discussion' && props.posts ? (
          <PostList
            community={community}
            space={space}
            initialPosts={props.posts}
            canContribute={props.permissions.canContribute}
            canReact={props.permissions.canReact}
          />
        ) : (
          <EmptyState icon={typeLabel.emoji} title={EMPTY_STATES[space.type].title} description={EMPTY_STATES[space.type].description} />
        )}
      </CommunityShell>
    </AppLayout>
  );
}
