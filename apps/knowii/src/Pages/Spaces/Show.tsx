import { Link } from '@inertiajs/react';
import type {
  ChatMessage,
  Community,
  CommunityResource,
  CourseProgress,
  Page,
  Paginated,
  Post,
  Space,
  SpaceGroup,
  SpacePermissions,
} from '@knowii/common';
import {
  COMMUNITY_SETTINGS_URL,
  COMMUNITY_URL,
  safeHttpUrl,
  SPACE_POSTING_POLICY_LABELS,
  SPACE_TYPE_LABELS,
  useTypedPage,
} from '@knowii/common';
import { FaArrowUpRightFromSquare, FaGear } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import type { BreadcrumbItem } from '@/Components/ui';
import { buttonClasses, EmptyState } from '@/Components/ui';
import { PostList } from '@/Components/Posts/PostList';
import { ResourceList } from '@/Components/Resources/ResourceList';
import { PageList } from '@/Components/SpacePages/PageList';
import { CourseMaterials } from '@/Components/SpacePages/CourseMaterials';
import { ChatSpace } from '@/Components/Chat/ChatSpace';

interface Props {
  // WARNING: aligned with SpaceController.php
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  permissions: SpacePermissions;
  // Discussion spaces only: the first page of posts
  posts: Paginated<Post> | null;
  // Resources spaces only: the first page of resources (ADR 0015)
  resources: Paginated<CommunityResource> | null;
  // Page spaces (#1179): the titles, in their order
  pages: Page[] | null;
  // Course spaces (#1304): the progress of the member, null for a reader who is not a member
  progress: CourseProgress | null;
  // Chat spaces (#1185): the newest messages and where the unread ones start
  chat: { messages: ChatMessage[]; has_more: boolean; first_unread_cuid: string | null } | null;
}

const EMPTY_STATES: Record<Space['type'], { title: string; description: string }> = {
  discussion: { title: 'No posts yet', description: 'Posts and their comments will show here.' },
  chat: { title: 'No messages yet', description: 'Messages and threads will show here, in real time.' },
  pages: { title: 'No pages yet', description: 'The pages of this space will show here, in order.' },
  course: { title: 'No lessons yet', description: 'The lessons of this course will show here, in order.' },
  resources: { title: 'No resources yet', description: 'Curated articles will show here.' },
  link: { title: 'No address', description: 'This link has no valid address. A member who manages the spaces can fix it.' },
};

/**
 * A link space (#1294, ADR 0026) has no content. The navigation opens its page at once; a member who comes to this
 * page from a bookmark sees where the link goes before leaving Knowii (no automatic redirect).
 */
function LinkSpace({ space }: { space: Space }) {
  const url = safeHttpUrl(space.url);
  if (!url) {
    return <EmptyState icon={SPACE_TYPE_LABELS.link.emoji} title={EMPTY_STATES.link.title} description={EMPTY_STATES.link.description} />;
  }
  return (
    <section aria-labelledby="link-space-title" className="flex flex-col items-start gap-3 rounded-xl border border-border p-6">
      <h2 id="link-space-title" className="text-lg font-bold">
        This link opens a page outside Knowii
      </h2>
      <p className="break-all text-muted">{url}</p>
      <a href={url} target="_blank" rel="noopener noreferrer" className={buttonClasses({ variant: 'primary' })}>
        <FaArrowUpRightFromSquare aria-hidden="true" />
        Open the page <span className="sr-only">(in a new tab)</span>
      </a>
    </section>
  );
}

export default function SpacePage(props: Props) {
  const page = useTypedPage();
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
      <CommunityShell
        community={community}
        spaceGroups={props.spaceGroups}
        activeSpaceCuid={space.cuid}
        // A grid of resources uses the width; a list of posts reads better in a column
        contentWidth={space.type === 'resources' ? 'wide' : 'readable'}
      >
        <header className="mb-6 flex flex-col gap-2 border-b border-border pb-4">
          <h1 className="flex items-center gap-3 text-2xl font-extrabold">
            <span aria-hidden="true">{space.emoji || typeLabel.emoji}</span>
            {space.name}
          </h1>
          {space.description && <p className="text-muted">{space.description}</p>}
          <p className="flex flex-wrap gap-2 text-xs text-muted">
            <span className="rounded-full border border-border px-2 py-0.5">{typeLabel.name}</span>
            <span className="rounded-full border border-border px-2 py-0.5">
              {space.visibility === 'public' ? 'Public' : space.visibility === 'groups' ? 'Access groups only' : 'Members only'}
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
            currentUserCuid={page.props.auth.user?.cuid ?? null}
          />
        ) : space.type === 'chat' && props.chat ? (
          <ChatSpace
            community={community}
            space={space}
            initialMessages={props.chat.messages}
            hasMore={props.chat.has_more}
            firstUnreadCuid={props.chat.first_unread_cuid}
            currentUserCuid={page.props.auth.user?.cuid ?? null}
            canWrite={props.permissions.canContribute}
            canReact={props.permissions.canReact}
            canModerate={props.permissions.canModerate}
            presenceEnabled={community.is_member === true}
            showsOnlineStatus={page.props.auth.user?.show_online_status !== false}
          />
        ) : (space.type === 'pages' || space.type === 'course') && props.pages ? (
          <div className="flex flex-col gap-6">
            <PageList
              community={community}
              space={space}
              pages={props.pages}
              canManagePages={props.permissions.canManagePages}
              progress={props.progress}
            />
            {/* The files and links of the course and of its sections (#1336); the ones of a lesson are on its page */}
            {space.type === 'course' && (
              <CourseMaterials
                title="Course materials"
                communityCuid={community.cuid}
                spaceCuid={space.cuid}
                targets={[
                  { pageCuid: null, label: 'Whole course' },
                  ...props.pages.filter((item) => item.kind === 'section').map((item) => ({ pageCuid: item.cuid, label: item.title })),
                ]}
                canManage={props.permissions.canManagePages}
              />
            )}
          </div>
        ) : space.type === 'resources' && props.resources ? (
          <ResourceList
            community={community}
            space={space}
            initialResources={props.resources}
            canContribute={props.permissions.canContribute}
          />
        ) : space.type === 'link' ? (
          <LinkSpace space={space} />
        ) : (
          <EmptyState icon={typeLabel.emoji} title={EMPTY_STATES[space.type].title} description={EMPTY_STATES[space.type].description} />
        )}
      </CommunityShell>
    </AppLayout>
  );
}
