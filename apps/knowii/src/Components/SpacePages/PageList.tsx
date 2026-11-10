import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Community, Page, Space } from '@knowii/common';
import { knowiiApiClient, PAGE_URL, useAppData, useSocket } from '@knowii/common';
import { FaArrowDown, FaArrowRight, FaArrowUp, FaHeading, FaPen, FaPlus, FaTrash, FaVideo } from 'react-icons/fa6';
import { Button, buttonClasses, Dialog, EmptyState } from '@/Components/ui';
import type { PageEditorMode } from './PageEditor';
import { PageEditor } from './PageEditor';

export interface PageListProps {
  community: Community;
  space: Space;
  pages: Page[];
  canManagePages: boolean;
}

/**
 * The pages of a page space (#1179), or the outline of a course space (#1188): sections as headings, numbered lessons
 * with a video mark, and "Start the course". The owner, the admins and the moderators add pages, lessons and sections
 * and move them with the arrow buttons (keyboard friendly, no drag and drop needed). Sections have no page: they are
 * renamed and deleted here.
 */
export function PageList({ community, space, pages: initialPages, canManagePages }: PageListProps) {
  const { showToast } = useAppData();
  const [pages, setPages] = useState<Page[]>(initialPages);
  const [source, setSource] = useState(initialPages);
  const [editor, setEditor] = useState<{ mode: PageEditorMode; page?: Page } | null>(null);
  const [toDelete, setToDelete] = useState<Page | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [moving, setMoving] = useState<string | null>(null);
  const isCourse = space.type === 'course';
  const itemMode: PageEditorMode = isCourse ? 'lesson' : 'page';
  const noun = isCourse ? 'lesson' : 'page';

  // New props (a reload after a live event) replace the list
  if (source !== initialPages) {
    setSource(initialPages);
    setPages(initialPages);
  }

  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );
  useSocket({
    channel,
    event: ['space.page.created', 'space.page.updated', 'space.page.deleted', 'space.pages.reordered'],
    callback: () => router.reload({ only: ['pages'] }),
  });

  const href = (page: Page) => route(PAGE_URL, { communitySlug: community.slug, spaceSlug: space.slug, pageSlug: page.slug });
  const items = pages.filter((page) => page.kind !== 'section');
  const first = items[0];

  // The number of each lesson or page, sections left out
  const numbers = new Map<string, number>();
  items.forEach((page, index) => numbers.set(page.cuid, index + 1));

  const move = async (index: number, offset: -1 | 1) => {
    const target = index + offset;
    const page = pages[index];
    const other = pages[target];
    if (!page || !other) {
      return;
    }
    const order = [...pages];
    order[index] = other;
    order[target] = page;
    setMoving(page.cuid);
    const result = await knowiiApiClient.communities.pages.reorder(
      community.cuid,
      space.cuid,
      order.map((item) => item.cuid),
    );
    setMoving(null);
    if (result.ok) {
      setPages(result.data);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const deleteSection = async () => {
    if (!toDelete) {
      return;
    }
    setDeleting(true);
    const result = await knowiiApiClient.communities.pages.delete(community.cuid, space.cuid, toDelete.cuid);
    setDeleting(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setPages((current) => current.filter((page) => page.cuid !== toDelete.cuid));
    setToDelete(null);
    showToast({ severity: 'success', summary: 'Section deleted' });
  };

  const moveButtons = (page: Page, index: number) => (
    <span className="flex shrink-0 gap-1">
      <Button
        variant="ghost"
        size="sm"
        iconOnly
        aria-label={`Move "${page.title}" up`}
        disabled={index === 0 || moving !== null}
        onClick={() => void move(index, -1)}
      >
        <FaArrowUp aria-hidden="true" />
      </Button>
      <Button
        variant="ghost"
        size="sm"
        iconOnly
        aria-label={`Move "${page.title}" down`}
        disabled={index === pages.length - 1 || moving !== null}
        onClick={() => void move(index, 1)}
      >
        <FaArrowDown aria-hidden="true" />
      </Button>
    </span>
  );

  return (
    <section aria-label={isCourse ? `Lessons of ${space.name}` : `Pages of ${space.name}`} className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-muted">{items.length === 1 ? `1 ${noun}` : `${items.length} ${noun}s`}</p>
        <div className="flex flex-wrap gap-2">
          {isCourse && first && (
            <Link href={href(first)} className={buttonClasses({ variant: canManagePages ? 'secondary' : 'primary' })}>
              Start the course
              <FaArrowRight aria-hidden="true" />
            </Link>
          )}
          {canManagePages && (
            <>
              <Button variant="secondary" onClick={() => setEditor({ mode: 'section' })}>
                <FaHeading aria-hidden="true" />
                Add a section
              </Button>
              <Button onClick={() => setEditor({ mode: itemMode })}>
                <FaPlus aria-hidden="true" />
                {isCourse ? 'Add a lesson' : 'Add a page'}
              </Button>
            </>
          )}
        </div>
      </div>

      {pages.length === 0 ? (
        <EmptyState
          icon={isCourse ? '🎓' : '📄'}
          title={isCourse ? 'No lessons yet' : 'No pages yet'}
          description={
            canManagePages
              ? isCourse
                ? 'Add sections and lessons, with a video each when you want.'
                : 'Write the first page: a welcome, the rules, a guide.'
              : isCourse
                ? 'The lessons of this course will show here, in order.'
                : 'The pages of this space will show here.'
          }
        />
      ) : (
        <ol className="flex flex-col divide-y divide-border rounded-lg border border-border bg-well">
          {pages.map((page, index) =>
            page.kind === 'section' ? (
              <li key={page.cuid} className="flex items-center gap-3 bg-overlay/5 px-4 py-2">
                <h2 className="min-w-0 grow text-sm font-extrabold tracking-wide uppercase">{page.title}</h2>
                {canManagePages && (
                  <>
                    <Button
                      variant="ghost"
                      size="sm"
                      iconOnly
                      aria-label={`Rename the section "${page.title}"`}
                      onClick={() => setEditor({ mode: 'section', page })}
                    >
                      <FaPen aria-hidden="true" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      iconOnly
                      aria-label={`Delete the section "${page.title}"`}
                      onClick={() => setToDelete(page)}
                    >
                      <FaTrash aria-hidden="true" />
                    </Button>
                    {moveButtons(page, index)}
                  </>
                )}
              </li>
            ) : (
              <li key={page.cuid} className="flex items-center gap-3 px-4 py-2">
                <span aria-hidden="true" className="w-6 shrink-0 text-right text-sm text-muted">
                  {numbers.get(page.cuid)}.
                </span>
                <Link href={href(page)} className="touch-target min-w-0 grow font-bold hover:underline">
                  {page.title}
                </Link>
                {page.video && (
                  <span className="flex shrink-0 items-center gap-1 text-xs text-muted">
                    <FaVideo aria-hidden="true" />
                    <span className="sr-only @md:not-sr-only">Video</span>
                  </span>
                )}
                {canManagePages && moveButtons(page, index)}
              </li>
            ),
          )}
        </ol>
      )}

      {canManagePages && (
        <PageEditor
          open={editor !== null}
          communityCuid={community.cuid}
          spaceCuid={space.cuid}
          spaceName={space.name}
          mode={editor?.mode ?? itemMode}
          page={editor?.page}
          onClose={() => setEditor(null)}
          onSaved={(page) => {
            if (page.kind === 'section') {
              router.reload({ only: ['pages'] });
            } else {
              router.visit(href(page));
            }
          }}
        />
      )}

      <Dialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        size="sm"
        title="Delete this section?"
        description={`The heading "${toDelete?.title ?? ''}" goes. The ${noun}s under it stay, in the same order.`}
        footer={
          <>
            <Button variant="secondary" onClick={() => setToDelete(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={() => void deleteSection()} loading={deleting}>
              Delete
            </Button>
          </>
        }
      />
    </section>
  );
}
