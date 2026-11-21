import type { EventScope, Paginated, SpaceEvent } from '@knowii/common';
import { useTypedPage } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { EVENT_WORDS } from '@/Components/Events/event-words';
import { MyEventList } from '@/Components/Events/MyEventList';

interface Props {
  // WARNING: aligned with MyEventsController::index
  // The first 20 events of the open list. The server cut the meeting address and the place for a reader who may not see them
  events: Paginated<SpaceEvent>;
  scope: EventScope;
  // The account is in more than one community: each row names the community of its event
  showCommunity: boolean;
}

/**
 * The page "My events" (#1515, design 6.8): the events that the member answered Yes or Replay only, and the events that
 * they host, in all their communities, with "Upcoming" and "Past". Its only heading of the first level is the one of the
 * layout. For a signed-in member: a visitor goes to the sign-in page.
 *
 * The page opens no live connection and sends no request when it renders: it shows what the server gave when it loaded,
 * and the button under the list loads more.
 */
export default function MyEventsPage({ events, scope, showCommunity }: Props) {
  const readerCuid = useTypedPage().props.auth.user?.cuid ?? '';

  return (
    <AppLayout contentWidth="form" browserPageTitle={EVENT_WORDS.myEvents} pageTitle={EVENT_WORDS.myEvents}>
      <div className="flex flex-col gap-6">
        <p className="text-muted">{EVENT_WORDS.myEventsIntro}</p>
        <MyEventList initialEvents={events} scope={scope} showCommunity={showCommunity} readerCuid={readerCuid} />
      </div>
    </AppLayout>
  );
}
