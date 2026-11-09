import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Community, Page, PageNeighbour, Space, SpaceContentPayload, SpaceGroup } from '@knowii/common';
import {
  COMMUNITY_URL,
  knowiiApiClient,
  PAGE_URL,
  SPACE_TYPE_LABELS,
  SPACE_URL,
  useAppData,
  useSocket,
  useTypedPage,
} from '@knowii/common';
import { FaArrowLeft, FaArrowRight, FaPen, FaTrash } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import { PageEditor } from '@/Components/SpacePages/PageEditor';
import type { BreadcrumbItem } from '@/Components/ui';
import { Button, Dialog, MarkdownContent, RelativeTime } from '@/Components/ui';

interface Props {
  // WARNING: aligned with PageController.php
  community: Community;
  space: Space;
  spaceGroups: SpaceGroup[];
  page: Page;
  previous: PageNeighbour | null;
  next: PageNeighbour | null;
  position: { current: number; total: number };
  permissions: { canManagePages: boolean };
}

/**
 * A page of a page space (#1179): the text, then links to the previous and the next page, so members read them in order.
 */
export default function SpacePage(props: Props) {
  const { community, space, page, previous, next, position } = props;
  const { showToast } = useAppData();
  const currentUser = useTypedPage().props.auth.user;
  const [editing, setEditing] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [busy, setBusy] = useState(false);

  const spaceHref = route(SPACE_URL, { communitySlug: community.slug, spaceSlug: space.slug });
  const pageHref = (neighbour: PageNeighbour) =>
    route(PAGE_URL, { communitySlug: community.slug, spaceSlug: space.slug, pageSlug: neighbour.slug });

  // Live updates: another manager changed or deleted this page, or moved the pages
  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );
  useSocket({
    channel,
    event: ['space.page.created', 'space.page.updated', 'space.page.deleted', 'space.pages.reordered'],
    callback: (event, payload) => {
      const data = payload as SpaceContentPayload;
      if (event === 'space.page.deleted' && data.page_cuid === page.cuid) {
        if (data.author_cuid !== currentUser?.cuid) {
          showToast({ severity: 'info', summary: 'This page was deleted' });
          router.visit(spaceHref);
        }
        return;
      }
      if (!editing) {
        router.reload({ only: ['page', 'previous', 'next', 'position'] });
      }
    },
  });

  const deletePage = async () => {
    setBusy(true);
    const result = await knowiiApiClient.communities.pages.delete(community.cuid, space.cuid, page.cuid);
    setBusy(false);
    if (result.ok) {
      showToast({ severity: 'success', summary: 'Page deleted' });
      router.visit(spaceHref);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const breadcrumbItems: BreadcrumbItem[] = [
    {
      label: community.name,
      href: route(COMMUNITY_URL, { communitySlug: community.slug }),
      icon: <CommunityIcon visibility={community.visibility} />,
    },
    { label: space.name, href: spaceHref, icon: <span aria-hidden="true">{space.emoji || SPACE_TYPE_LABELS[space.type].emoji}</span> },
    { label: page.title },
  ];

  return (
    <AppLayout browserPageTitle={`${page.title} - ${space.name}`} breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <CommunityShell community={community} spaceGroups={props.spaceGroups} activeSpaceCuid={space.cuid} contentWidth="readable">
        <article className="flex flex-col gap-4">
          <header className="flex flex-col gap-2">
            <p className="text-sm text-muted">
              Page {position.current} of {position.total}
            </p>
            <h1 className="text-2xl font-extrabold">{page.title}</h1>
            <p className="text-xs text-muted">
              Updated <RelativeTime date={page.updated_at} />
              {page.author ? ` by ${page.author.name}` : ''}
            </p>
            {props.permissions.canManagePages && (
              <div className="flex flex-wrap gap-2">
                <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
                  <FaPen aria-hidden="true" />
                  Edit
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setConfirmDelete(true)}>
                  <FaTrash aria-hidden="true" />
                  Delete
                </Button>
              </div>
            )}
          </header>

          <MarkdownContent html={page.body_html ?? ''} className="break-words" />

          <nav aria-label="Pages of the space" className="mt-6 grid gap-3 border-t border-border pt-4 @md:grid-cols-2">
            {previous ? (
              <Link
                href={pageHref(previous)}
                rel="prev"
                className="touch-target flex flex-col items-start gap-1 rounded-lg border border-border p-3 hover:bg-overlay/10"
              >
                <span className="flex items-center gap-1 text-xs text-muted">
                  <FaArrowLeft aria-hidden="true" />
                  Previous
                </span>
                <span className="font-bold">{previous.title}</span>
              </Link>
            ) : (
              <span />
            )}
            {next && (
              <Link
                href={pageHref(next)}
                rel="next"
                className="touch-target flex flex-col items-end gap-1 rounded-lg border border-border p-3 text-right hover:bg-overlay/10"
              >
                <span className="flex items-center gap-1 text-xs text-muted">
                  Next
                  <FaArrowRight aria-hidden="true" />
                </span>
                <span className="font-bold">{next.title}</span>
              </Link>
            )}
          </nav>
        </article>
      </CommunityShell>

      {props.permissions.canManagePages && (
        <PageEditor
          open={editing}
          communityCuid={community.cuid}
          spaceCuid={space.cuid}
          spaceName={space.name}
          page={page}
          onClose={() => setEditing(false)}
          onSaved={() => router.reload({ only: ['page', 'previous', 'next', 'position'] })}
        />
      )}

      <Dialog
        open={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        size="sm"
        title="Delete this page?"
        description="The page is deleted. This cannot be undone."
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirmDelete(false)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={deletePage} loading={busy}>
              Delete
            </Button>
          </>
        }
      />
    </AppLayout>
  );
}
