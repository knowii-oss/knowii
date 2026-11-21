import type { SpaceEvent } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { FaRegCalendarPlus } from 'react-icons/fa6';
import { buttonClasses, cn } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';

export interface AddToCalendarProps {
  communityCuid: string;
  spaceCuid: string;
  event: Pick<SpaceEvent, 'cuid' | 'title' | 'state'>;
  /**
   * The reader is a member of the community. A visitor and a person who is not a member get no file (design 6.5)
   */
  isMember: boolean;
  /**
   * On a row of a list: the title of the event follows the visible words in the name of the link ("Add to calendar:
   * Weekly review office hour"), so the name starts with the visible words. On the page of the event the title is the
   * heading of the page.
   */
  withTitle?: boolean;
}

/**
 * "Add to calendar" (#1515, design 6.5): one plain link that downloads the calendar file of the event, like the link of a
 * course material. Not a button and not an Inertia link: the browser loads a file from an address, the session of the
 * browser signs the request, and no script reads the file. It looks like a secondary button, 44 px high.
 *
 * Only for a member, and never for a draft (a draft has no file). It stays on a cancelled event (the file then says that
 * the event is cancelled) and on an event that ended. A refusal of the server after the page loaded (the access is
 * gone, the event went back to draft, too many files in a minute) is a download that failed in the browser.
 */
export function AddToCalendar({ communityCuid, spaceCuid, event, isMember, withTitle = false }: AddToCalendarProps) {
  if (!isMember || event.state === 'draft') {
    return null;
  }

  return (
    <a
      href={knowiiApiClient.communities.events.calendarFileUrl(communityCuid, spaceCuid, event.cuid)}
      download
      // On a row the name holds the title after the visible words. Not a visually hidden span: a browser puts a space
      // before the text of an element out of the flow ("Add to calendar : Weekly review")
      {...(withTitle ? { 'aria-label': `${EVENT_WORDS.addToCalendar}${EVENT_WORDS.addToCalendarOfTitle(event.title)}` } : {})}
      className={cn(buttonClasses({ variant: 'secondary' }), 'h-11 self-start')}
    >
      <FaRegCalendarPlus aria-hidden="true" />
      {EVENT_WORDS.addToCalendar}
    </a>
  );
}
