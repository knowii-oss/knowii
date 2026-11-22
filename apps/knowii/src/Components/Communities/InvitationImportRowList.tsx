import { useCallback, useEffect, useId, useRef, useState } from 'react';
import type { InvitationImportOutcome, InvitationImportRow } from '@knowii/common';
import { COMMUNITY_ROLE_LABELS, knowiiApiClient, MAX_INVITATION_IMPORT_ROWS } from '@knowii/common';
import { LoadMoreButton, useFocusAfterRender } from '@/Components/ui';
import { INVITATION_WORDS } from './invitation-words';

/**
 * The rows of a list load this many at a time
 */
export const INVITATION_IMPORT_ROWS_PAGE_SIZE = 50;

export interface InvitationImportRowListProps {
  communityCuid: string;
  importCuid: string;
  title: string;
  outcomes: readonly InvitationImportOutcome[];
  /**
   * Changes when the rows changed (a send, a send again, a new state of the import): the list loads again from the
   * start, with as many rows as it shows, and the focus stays on its row
   */
  refreshKey?: number;
}

/**
 * One list of the rows of an import of invitations (#1390, ADR 0061): "Refused rows", "Rows to invite", "Not sent",
 * "Sent". A real list with its name, by row number, 50 at a time with "Load more": the status says how many came, and
 * the focus goes to the first new row. A reload keeps the rows that "Load more" added, and the focus on its row; when
 * that row left the list, the focus goes to the name of the list. Each row says its outcome and its reason in words,
 * never with a color alone, and wraps at a phone width.
 */
export function InvitationImportRowList({ communityCuid, importCuid, title, outcomes, refreshKey = 0 }: InvitationImportRowListProps) {
  const headingId = useId();
  const [rows, setRows] = useState<InvitationImportRow[] | null>(null);
  const [total, setTotal] = useState(0);
  const [nextOffset, setNextOffset] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const listRef = useRef<HTMLUListElement>(null);
  const headingRef = useRef<HTMLHeadingElement>(null);
  // The rows shown now, for a reload: it reads them without running again when they change
  const shownRef = useRef<InvitationImportRow[] | null>(null);
  // Declared before the effect of the load, so it runs first in the same commit
  useEffect(() => {
    shownRef.current = rows;
  }, [rows]);
  const focusAfterRender = useFocusAfterRender();
  const outcomeKey = outcomes.join(',');

  const load = useCallback(
    (offset: number, limit: number = INVITATION_IMPORT_ROWS_PAGE_SIZE) =>
      knowiiApiClient.communities.invitationImports.rows(
        communityCuid,
        importCuid,
        outcomeKey.split(',') as InvitationImportOutcome[],
        limit,
        offset,
      ),
    [communityCuid, importCuid, outcomeKey],
  );

  useEffect(() => {
    let active = true;
    const shown = shownRef.current?.length ?? 0;
    const limit = Math.min(MAX_INVITATION_IMPORT_ROWS, Math.max(INVITATION_IMPORT_ROWS_PAGE_SIZE, shown));
    const focused = document.activeElement;
    const focusedRow =
      focused instanceof HTMLElement && listRef.current?.contains(focused) ? focused.closest<HTMLElement>('[data-row]') : null;
    const focusedCuid = focusedRow?.dataset['row'];
    void load(0, limit).then((result) => {
      if (!active) {
        return;
      }
      if (!result.ok) {
        setRows([]);
        setError(result.message);
        return;
      }
      setError('');
      setRows(result.data.items);
      setTotal(result.data.pagination.totalCount ?? result.data.items.length);
      setNextOffset(result.data.pagination.nextOffset ?? null);
      // A row that stays keeps its element, and so the focus. A row that left the list gives the focus to its name
      if (focusedCuid !== undefined && !result.data.items.some((row) => row.cuid === focusedCuid)) {
        focusAfterRender(() => headingRef.current);
      }
    });
    return () => {
      active = false;
    };
  }, [load, refreshKey, focusAfterRender]);

  const loadMore = async () => {
    if (nextOffset === null || loading || rows === null) {
      return;
    }
    setLoading(true);
    const result = await load(nextOffset);
    setLoading(false);
    if (!result.ok) {
      setError(result.message);
      return;
    }
    const added = result.data.items.filter((row) => !rows.some((shown) => shown.cuid === row.cuid));
    setRows((current) => [...(current ?? []), ...added.filter((row) => !(current ?? []).some((shown) => shown.cuid === row.cuid))]);
    setNextOffset(result.data.pagination.nextOffset ?? null);
    setStatus(INVITATION_WORDS.loaded(added.length));
    const first = added[0];
    if (first) {
      focusAfterRender(() =>
        Array.from(listRef.current?.querySelectorAll<HTMLElement>('[data-row]') ?? []).find((item) => item.dataset['row'] === first.cuid),
      );
    }
  };

  if (rows !== null && rows.length === 0 && error === '') {
    return null;
  }

  return (
    <section aria-labelledby={headingId} className="flex flex-col gap-2">
      <h2 id={headingId} ref={headingRef} tabIndex={-1} className="text-lg font-bold focus-visible:outline-2 focus-visible:outline-primary">
        {title} <span className="text-base font-normal text-muted">({total})</span>
      </h2>
      {error !== '' && (
        <p role="alert" className="text-sm text-danger-text">
          {error}
        </p>
      )}
      {rows !== null && rows.length > 0 && (
        <ul ref={listRef} aria-labelledby={headingId} className="flex flex-col divide-y divide-border rounded-lg border border-border">
          {rows.map((row) => (
            <li
              key={row.cuid}
              data-row={row.cuid}
              tabIndex={-1}
              className="flex flex-col gap-1 px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-primary"
            >
              <span className="flex flex-wrap items-baseline gap-x-3">
                <span className="text-muted">{INVITATION_WORDS.rowLabel(row.row_number)}</span>
                <span className="font-bold break-all">{row.email}</span>
                <span>{INVITATION_WORDS.outcomes[row.outcome]}</span>
                {row.still_invited && row.outcome === 'sent' && <span className="text-muted">({INVITATION_WORDS.stillInvited})</span>}
              </span>
              {row.reason_text !== null && <span className="break-words">{row.reason_text}</span>}
              {row.reason_text === null && (
                <span className="break-words text-muted">
                  {[
                    row.role === null ? null : COMMUNITY_ROLE_LABELS[row.role],
                    row.access_groups.length > 0 ? INVITATION_WORDS.pendingAccessGroups(row.access_groups.join(', ')) : null,
                    row.member_tags.length > 0 ? `${INVITATION_WORDS.pickerMemberTags}: ${row.member_tags.join(', ')}` : null,
                  ]
                    .filter((part) => part !== null)
                    .join(' · ')}
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
      {nextOffset !== null && (
        <LoadMoreButton loading={loading} onClick={() => void loadMore()} aria-label={`${INVITATION_WORDS.loadMore}: ${title}`}>
          {INVITATION_WORDS.loadMore}
        </LoadMoreButton>
      )}
      <p role="status" aria-live="polite" className="sr-only">
        {status}
      </p>
    </section>
  );
}
