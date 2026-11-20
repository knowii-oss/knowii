import { useEffect, useRef, useState } from 'react';
import type { Community, EventAnswerCounts, EventAnswerMember, Space, SpaceEvent } from '@knowii/common';
import { EVENT_ANSWERS_PER_PAGE, HttpStatus, knowiiApiClient } from '@knowii/common';
import { Dialog, EmptyState, InputError, LoadMoreButton, SegmentedControl, Skeleton, useFocusAfterRender } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';
import { EventMemberLine } from './EventMemberLine';

/**
 * The two answers whose members are named. The members who answered No are counted, never named
 */
type NamedAnswer = 'yes' | 'replay';

export interface EventAnswersDialogProps {
  open: boolean;
  onClose: () => void;
  community: Pick<Community, 'cuid' | 'slug'>;
  space: Pick<Space, 'cuid'>;
  event: Pick<SpaceEvent, 'cuid' | 'capacity' | 'replay_expected'>;
  /**
   * The list is no longer for this reader (403, 404): the dialog closes and the page loads again
   */
  onGone: () => void;
}

/**
 * "Answers": who answered an event, for its host, the owner and the admins (#1515, ADR 0053, design 6.4 point 3). The
 * names of the members who answered Yes, or Replay only, by name, each a link to the member page, 20 at a time, and
 * the number of the members who answered No, without their names. Read only: no export, no copy, no search and no
 * time of an answer.
 *
 * The names load when the dialog opens, and each time it opens: nothing is in the page before the reader asks, and the
 * list is current then. It does not follow the live signal, which is sent only for the Yes answers of an event with a
 * limit.
 */
export function EventAnswersDialog({ open, onClose, community, space, event, onGone }: EventAnswersDialogProps) {
  return (
    <Dialog open={open} onClose={onClose} title={EVENT_WORDS.answers} description={EVENT_WORDS.answersDescription}>
      {/* In the page only while the dialog is open: each opening starts a new list */}
      <EventAnswersList
        community={community}
        space={space}
        event={event}
        onGone={() => {
          onClose();
          onGone();
        }}
      />
    </Dialog>
  );
}

type EventAnswersListProps = Pick<EventAnswersDialogProps, 'community' | 'space' | 'event' | 'onGone'>;

function EventAnswersList({ community, space, event, onGone }: EventAnswersListProps) {
  const [answer, setAnswer] = useState<NamedAnswer>('yes');
  const [members, setMembers] = useState<EventAnswerMember[]>([]);
  const [nextOffset, setNextOffset] = useState<number | null>(null);
  const [counts, setCounts] = useState<EventAnswerCounts | null>(null);
  // The first names of an answer load
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState('');
  const listRef = useRef<HTMLDivElement>(null);
  const focusAfterRender = useFocusAfterRender();
  // The number of the last request: an answer of the server for an older one (another choice) is dropped
  const request = useRef(0);
  // The dialog closed: an answer that comes after it changes nothing
  const closed = useRef(false);

  type Part = Awaited<ReturnType<typeof knowiiApiClient.communities.events.answers.list>>;

  /**
   * Ask for one part of the names of an answer. The number says which request it is
   */
  const ask = (which: NamedAnswer, offset: number): { mine: number; part: Promise<Part> } => ({
    mine: ++request.current,
    part: knowiiApiClient.communities.events.answers.list(community.cuid, space.cuid, event.cuid, which, EVENT_ANSWERS_PER_PAGE, offset),
  });

  /**
   * What the server answered: the names of the part. Null: the answer came too late, the load failed, or the list is no
   * longer for this reader
   */
  const take = (mine: number, result: Part): EventAnswerMember[] | null => {
    if (closed.current || mine !== request.current) {
      return null;
    }
    if (!result.ok) {
      if (result.status === HttpStatus.FORBIDDEN || result.status === HttpStatus.NOT_FOUND) {
        onGone();
        return null;
      }
      // The words of the server
      setError(result.message);
      setLoading(false);
      setLoadingMore(false);
      return null;
    }
    setCounts(result.data.counts);
    setNextOffset(result.data.pagination.nextOffset);
    return result.data.items;
  };

  const loadFirst = (which: NamedAnswer) => {
    const { mine, part } = ask(which, 0);
    void part.then((result) => {
      const items = take(mine, result);
      if (items !== null) {
        setMembers(items);
        setLoading(false);
      }
    });
  };

  // The dialog opened
  useEffect(() => {
    closed.current = false;
    loadFirst('yes');
    return () => {
      closed.current = true;
    };
    // Once, when the list shows: the event of an open dialog is the same event
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const show = (which: NamedAnswer) => {
    if (which === answer) {
      return;
    }
    setAnswer(which);
    setLoading(true);
    setLoadingMore(false);
    setError(null);
    setStatus('');
    loadFirst(which);
  };

  const loadMore = async () => {
    if (nextOffset === null || loadingMore || loading) {
      return;
    }
    setLoadingMore(true);
    setError(null);
    setStatus('');
    const { mine, part } = ask(answer, nextOffset);
    const items = take(mine, await part);
    if (items === null) {
      return;
    }
    setLoadingMore(false);
    // A member can move between two loads when the list changes on the server: never two rows for one member
    const added = items.filter((member) => !members.some((shown) => shown.cuid === member.cuid));
    setMembers((current) => [...current, ...added.filter((member) => !current.some((shown) => shown.cuid === member.cuid))]);
    setStatus(EVENT_WORDS.loaded(added.length));
    const first = added[0];
    if (first) {
      // The button may go away with the last names: the reader continues on the first new name
      focusAfterRender(() =>
        Array.from(listRef.current?.querySelectorAll<HTMLElement>('[data-member-link]') ?? []).find(
          (link) => link.dataset['memberLink'] === first.cuid,
        ),
      );
    }
  };

  const showsFilter = counts !== null && (event.replay_expected || counts.replay > 0 || answer === 'replay');

  return (
    <div className="flex flex-col gap-4">
      {counts !== null && event.capacity !== null && (
        <p data-slot="event-answers-places" className="text-sm font-bold">
          {EVENT_WORDS.placesTaken(counts.yes, event.capacity)}
        </p>
      )}
      {showsFilter && (
        <SegmentedControl<NamedAnswer>
          aria-label={EVENT_WORDS.answersFilter}
          // 44 px of height for each radio on a small screen
          className="self-start [&_label]:min-h-11 md:[&_label]:min-h-0"
          options={[
            { value: 'yes', label: EVENT_WORDS.answersOf('yes', counts.yes) },
            { value: 'replay', label: EVENT_WORDS.answersOf('replay', counts.replay) },
          ]}
          value={answer}
          onChange={show}
        />
      )}

      {/* The place of the names. The skeleton is next to it, never inside: what a busy element holds may not be read */}
      <div ref={listRef} aria-busy={loading} data-slot="event-answers-list">
        {!loading && members.length > 0 && (
          <ul aria-label={EVENT_WORDS.answersList(answer)} className="flex flex-col gap-3">
            {members.map((member) => (
              <li key={member.cuid}>
                <EventMemberLine member={member} communitySlug={community.slug} link />
              </li>
            ))}
          </ul>
        )}
        {!loading && members.length === 0 && error === null && <EmptyState title={EVENT_WORDS.nobodyAnswered(answer)} />}
      </div>
      {loading && <Skeleton label={EVENT_WORDS.answersLoading} rows={5} lines={1} avatar />}

      {!loading && nextOffset !== null && (
        <LoadMoreButton onClick={() => void loadMore()} loading={loadingMore}>
          {EVENT_WORDS.loadMore}
        </LoadMoreButton>
      )}

      {counts !== null && (
        <p data-slot="event-answers-no" className="text-sm text-muted">
          {EVENT_WORDS.answeredNo(counts.no)}
        </p>
      )}

      {/* "20 more loaded": polite, and in the dialog from its start so that a screen reader reads each change */}
      <p role="status" aria-live="polite" className="sr-only">
        {status}
      </p>
      <InputError message={error ?? undefined} />
    </div>
  );
}
