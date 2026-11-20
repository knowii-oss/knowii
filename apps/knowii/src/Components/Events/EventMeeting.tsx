import type { SpaceEvent } from '@knowii/common';
import { safeHttpUrl } from '@knowii/common';
import { FaArrowUpRightFromSquare, FaLocationDot } from 'react-icons/fa6';
import { SignInPrompt, useIsVisitor } from '@/Components/Reader/Reader';
import { buttonClasses } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';

export interface EventMeetingProps {
  event: Pick<SpaceEvent, 'can_view_location' | 'meeting_url' | 'place' | 'can_answer'>;
  /**
   * The reader is a member of the community (a signed-in person who is not one reads a public space like a visitor)
   */
  isMember: boolean;
}

/**
 * Where the event happens (#1514, design 3.7 and 6.2 point 4). The server decides who gets the meeting address and the
 * place (`can_view_location`): this component hides nothing, it shows what it received.
 *
 * With them: "Join the meeting" (only an absolute http or https address becomes a link; it opens in a new tab and says
 * so), the place as text, or "Meeting address to come" when both are empty. The link stays for a cancelled and for a
 * past event: a member may need it. Without them: a visitor reads who the address is for and gets "Sign in"; a
 * signed-in person who is not a member reads the sentence alone; a member reads that it is for the members who answer
 * Yes (#1515), or who answered Yes when the event takes no answers any more. A member who answers Yes gets the address
 * with the next event that the page receives: nothing is computed here.
 */
export function EventMeeting({ event, isMember }: EventMeetingProps) {
  const isVisitor = useIsVisitor();
  const url = safeHttpUrl(event.meeting_url);
  const place = event.place?.trim() ?? '';

  return (
    <section
      aria-labelledby="event-meeting-title"
      className="flex flex-col items-start gap-3 rounded-xl border border-border bg-surface p-4"
    >
      <h2 id="event-meeting-title" className="text-lg font-extrabold">
        {EVENT_WORDS.meeting}
      </h2>
      {event.can_view_location ? (
        <>
          {url && (
            <a href={url} target="_blank" rel="noopener noreferrer" className={buttonClasses({ variant: 'primary' })}>
              <FaArrowUpRightFromSquare aria-hidden="true" />
              {EVENT_WORDS.joinMeeting} <span className="sr-only">({EVENT_WORDS.opensInNewTab})</span>
            </a>
          )}
          {place !== '' && (
            <p className="flex items-start gap-2 wrap-anywhere">
              <FaLocationDot aria-hidden="true" className="mt-1 shrink-0 text-muted" />
              <span>
                <span className="sr-only">{EVENT_WORDS.place}:</span> {place}
              </span>
            </p>
          )}
          {!url && place === '' && <p className="text-muted">{EVENT_WORDS.meetingToCome}</p>}
        </>
      ) : isVisitor ? (
        <>
          <p>{EVENT_WORDS.meetingForMembers}</p>
          <SignInPrompt>{EVENT_WORDS.signIn}</SignInPrompt>
        </>
      ) : (
        <p>{isMember ? (event.can_answer ? EVENT_WORDS.meetingForYes : EVENT_WORDS.meetingForYesClosed) : EVENT_WORDS.meetingForMembers}</p>
      )}
    </section>
  );
}
