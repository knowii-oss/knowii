import type { SpaceEvent } from '@knowii/common';
import { safeHttpUrl, toDate } from '@knowii/common';
import { FaArrowUpRightFromSquare, FaPen } from 'react-icons/fa6';
import { ErrorBoundary, LinkButton, MarkdownContent } from '@/Components/ui';
import { VideoPlayer } from '@/Components/Video/VideoPlayer';
import { EVENT_WORDS } from './event-words';

export interface EventAfterProps {
  event: Pick<
    SpaceEvent,
    'title' | 'state' | 'ends_at' | 'can_view_replay' | 'replay_url' | 'replay' | 'notes' | 'notes_html' | 'replay_expected'
  >;
  /**
   * The reader changes the event (`permissions.canUpdate`): the link to the form
   */
  canUpdate: boolean;
  /**
   * The address of the form of the event
   */
  editHref: string;
  now: Date;
}

/**
 * The section "After the event" of the page of an event (#1516, design 2.4 and 6.2 point 2), after the description. It
 * shows on a published event whose end passed, when there is something in it: a replay that the reader may see, notes,
 * a replay that the host plans (for a member: the server gives `can_view_replay` to the members only), or the link of the
 * people who change the event. Never before the end, never on a draft or a cancelled event.
 *
 * In this order: the replay, the notes, the link of the host. The server decides who gets the replay (`can_view_replay`,
 * `replay_url` and `replay` are null for a visitor and a person who is not a member): this component hides nothing that
 * it received. A link of one of the two video hosts of lessons comes as `replay` and shows the click-to-load player of a
 * lesson (nothing loads from the host before Play); any other address is a plain link that opens in a new tab, and only
 * an absolute http or https address becomes a link (`safeHttpUrl`).
 */
export function EventAfter({ event, canUpdate, editHref, now }: EventAfterProps) {
  const ended = toDate(event.ends_at).getTime() <= now.getTime();
  if (event.state !== 'published' || !ended) {
    return null;
  }

  const replayLink = event.replay === null ? safeHttpUrl(event.replay_url) : null;
  const hasReplay = event.can_view_replay && (event.replay !== null || replayLink !== null);
  // Only a member reads that a replay will come: a visitor and a person who is not a member read nothing about it
  const plansReplay = event.can_view_replay && !hasReplay && event.replay_expected;
  const notesHtml = event.notes_html ?? '';
  const hasNotes = notesHtml !== '';
  if (!hasReplay && !plansReplay && !hasNotes && !canUpdate) {
    return null;
  }
  const hasContent = (event.replay_url ?? '') !== '' || (event.notes ?? '') !== '';

  return (
    <section aria-labelledby="event-after-title" className="flex flex-col gap-4">
      <h2 id="event-after-title" className="text-lg font-extrabold">
        {EVENT_WORDS.afterEvent}
      </h2>
      {event.can_view_replay && event.replay !== null && <VideoPlayer video={event.replay} title={event.title} />}
      {event.can_view_replay && replayLink !== null && (
        <p>
          <a
            href={replayLink}
            target="_blank"
            rel="noopener noreferrer"
            className="touch-target inline-flex items-center gap-2 font-bold text-secondary-text underline underline-offset-2 hover:no-underline focus-visible:outline-2 focus-visible:outline-primary"
          >
            <FaArrowUpRightFromSquare aria-hidden="true" />
            {EVENT_WORDS.watchReplay} <span className="sr-only">({EVENT_WORDS.opensInNewTab})</span>
          </a>
        </p>
      )}
      {plansReplay && <p className="text-muted">{EVENT_WORDS.replayPlanned}</p>}
      {hasNotes && (
        <div className="flex flex-col gap-2">
          <h3 className="text-base font-extrabold">{EVENT_WORDS.notes}</h3>
          <ErrorBoundary variant="block" label="the notes of this event">
            <MarkdownContent html={notesHtml} />
          </ErrorBoundary>
        </div>
      )}
      {canUpdate && (
        <div>
          <LinkButton variant="secondary" href={editHref}>
            <FaPen aria-hidden="true" />
            {hasContent ? EVENT_WORDS.editReplayAndNotes : EVENT_WORDS.addReplayAndNotes}
          </LinkButton>
        </div>
      )}
    </section>
  );
}
