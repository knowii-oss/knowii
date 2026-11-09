import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Community, Page, Space } from '@knowii/common';
import { knowiiApiClient, PAGE_URL, useAppData, useSocket } from '@knowii/common';
import { FaArrowDown, FaArrowUp, FaPlus } from 'react-icons/fa6';
import { Button, EmptyState } from '@/Components/ui';
import { PageEditor } from './PageEditor';

export interface PageListProps {
  community: Community;
  space: Space;
  pages: Page[];
  canManagePages: boolean;
}

/**
 * The pages of a page space, in their order (#1179). The owner, the admins and the moderators add pages and move them
 * with the arrow buttons (keyboard friendly, no drag and drop needed).
 */
export function PageList({ community, space, pages: initialPages, canManagePages }: PageListProps) {
  const { showToast } = useAppData();
  const [pages, setPages] = useState<Page[]>(initialPages);
  const [source, setSource] = useState(initialPages);
  const [editorOpen, setEditorOpen] = useState(false);
  const [moving, setMoving] = useState<string | null>(null);

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

  return (
    <section aria-label={`Pages of ${space.name}`} className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm text-muted">{pages.length === 1 ? '1 page' : `${pages.length} pages`}</p>
        {canManagePages && (
          <Button onClick={() => setEditorOpen(true)}>
            <FaPlus aria-hidden="true" />
            Add a page
          </Button>
        )}
      </div>

      {pages.length === 0 ? (
        <EmptyState
          icon="📄"
          title="No pages yet"
          description={canManagePages ? 'Write the first page: a welcome, the rules, a guide.' : 'The pages of this space will show here.'}
        />
      ) : (
        <ol className="flex flex-col divide-y divide-border rounded-lg border border-border bg-well">
          {pages.map((page, index) => (
            <li key={page.cuid} className="flex items-center gap-3 px-4 py-2">
              <span aria-hidden="true" className="w-6 shrink-0 text-right text-sm text-muted">
                {index + 1}.
              </span>
              <Link href={href(page)} className="touch-target min-w-0 grow font-bold hover:underline">
                {page.title}
              </Link>
              {canManagePages && (
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
              )}
            </li>
          ))}
        </ol>
      )}

      {canManagePages && (
        <PageEditor
          open={editorOpen}
          communityCuid={community.cuid}
          spaceCuid={space.cuid}
          spaceName={space.name}
          onClose={() => setEditorOpen(false)}
          onSaved={(page) => router.visit(href(page))}
        />
      )}
    </section>
  );
}
