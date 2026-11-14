import type { MouseEvent } from 'react';
import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Community, CourseProgress, Page, PageNeighbour, Space, SpaceContentPayload, SpaceGroup } from '@knowii/common';
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
import { FaArrowLeft, FaArrowRight, FaCheck, FaCircleCheck, FaPen, FaTrash } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import CommunityIcon from '@/Components/Communities/CommunityIcon';
import { CommunityShell } from '@/Components/Spaces/CommunityShell';
import { VideoPlayer } from '@/Components/Video/VideoPlayer';
import { CourseMaterials } from '@/Components/SpacePages/CourseMaterials';
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
  // Course spaces (#1188): the section of the lesson, when the course has sections
  section: string | null;
  // Lessons (#1304): whether the member marked this lesson as done, and their progress; null for a reader who is not a member
  progress: { done: boolean; course: CourseProgress } | null;
  permissions: { canManagePages: boolean };
}

/**
 * A page of a page space (#1179), or a lesson of a course space with its video (#1188): the text, then links to the
 * previous and the next one, so members read them in order. In a course, members mark the lesson as done, and going
 * to the next lesson marks it too (#1304).
 */
export default function SpacePage(props: Props) {
  const { community, space, page, previous, next, position } = props;
  const { showToast } = useAppData();
  const currentUser = useTypedPage().props.auth.user;
  const isCourse = space.type === 'course';
  const noun = isCourse ? 'Lesson' : 'Page';
  const [editing, setEditing] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [busy, setBusy] = useState(false);
  const [progress, setProgress] = useState(props.progress);
  const [progressSource, setProgressSource] = useState(props.progress);
  const [savingProgress, setSavingProgress] = useState(false);

  // New props (another lesson, a reload) replace the progress
  if (progressSource !== props.progress) {
    setProgressSource(props.progress);
    setProgress(props.progress);
  }

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
        router.reload({ only: ['page', 'previous', 'next', 'position', 'section', 'progress'] });
      }
    },
  });

  const deletePage = async () => {
    setBusy(true);
    const result = await knowiiApiClient.communities.pages.delete(community.cuid, space.cuid, page.cuid);
    setBusy(false);
    if (result.ok) {
      showToast({ severity: 'success', summary: `${noun} deleted` });
      router.visit(spaceHref);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const setDone = async (done: boolean): Promise<boolean> => {
    setSavingProgress(true);
    const result = done
      ? await knowiiApiClient.communities.pages.complete(community.cuid, space.cuid, page.cuid)
      : await knowiiApiClient.communities.pages.reopen(community.cuid, space.cuid, page.cuid);
    setSavingProgress(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return false;
    }
    setProgress({ done, course: result.data });
    return true;
  };

  // Going to the next lesson marks this one as done (#1304); a click that opens a new tab only marks it
  const goToNext = (event: MouseEvent, href: string) => {
    if (!progress || progress.done) {
      return;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
      void setDone(true);
      return;
    }
    event.preventDefault();
    void setDone(true).then(() => router.visit(href));
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
              {noun} {position.current} of {position.total}
              {props.section ? ` · ${props.section}` : ''}
              {progress && ` · ${progress.course.completed} of ${progress.course.total} done`}
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

          {page.video && <VideoPlayer video={page.video} title={page.title} />}

          <MarkdownContent html={page.body_html ?? ''} className="break-words" />

          {/* The files and links of the lesson (#1336): hidden from members while there is none */}
          {isCourse && (
            <CourseMaterials
              key={page.cuid}
              communityCuid={community.cuid}
              spaceCuid={space.cuid}
              targets={[{ pageCuid: page.cuid }]}
              canManage={props.permissions.canManagePages}
            />
          )}

          {progress && (
            // One button that keeps its place and its focus when the lesson changes state (#1304)
            <div className="flex flex-wrap items-center">
              <p role="status" className={progress.done ? 'mr-3 flex items-center gap-2 font-bold' : ''}>
                {progress.done && (
                  <>
                    <FaCircleCheck aria-hidden="true" className="text-success" />
                    Lesson done
                  </>
                )}
              </p>
              <Button
                variant={progress.done ? 'ghost' : 'primary'}
                size={progress.done ? 'sm' : 'md'}
                aria-busy={savingProgress || undefined}
                onClick={() => {
                  if (!savingProgress) {
                    void setDone(!progress.done);
                  }
                }}
              >
                {!progress.done && <FaCheck aria-hidden="true" />}
                {progress.done ? 'Mark as not done' : 'Mark as done'}
              </Button>
            </div>
          )}

          <nav
            aria-label={isCourse ? 'Lessons of the course' : 'Pages of the space'}
            className="mt-6 grid gap-3 border-t border-border pt-4 @md:grid-cols-2"
          >
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
                onClick={(event) => goToNext(event, pageHref(next))}
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
          mode={isCourse ? 'lesson' : 'page'}
          onClose={() => setEditing(false)}
          onSaved={() => router.reload({ only: ['page', 'previous', 'next', 'position', 'section'] })}
        />
      )}

      <Dialog
        open={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        size="sm"
        title={isCourse ? 'Delete this lesson?' : 'Delete this page?'}
        description={`The ${noun.toLowerCase()} is deleted. This cannot be undone.`}
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
