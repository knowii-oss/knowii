import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { Community, EventPermissions, EventState, Space, SpaceEvent } from '@knowii/common';
import { EVENT_EDIT_URL, knowiiApiClient, SPACE_URL, useAppData } from '@knowii/common';
import { FaEllipsis, FaPen, FaUsers } from 'react-icons/fa6';
import { Button, Dialog, DropdownMenu, DropdownMenuItem, dropdownMenuItemClasses, LinkButton } from '@/Components/ui';
import { startsInTheFuture } from './event-state';
import { EVENT_WORDS } from './event-words';
import { EventAnswersDialog } from './EventAnswersDialog';

export interface EventActionsProps {
  community: Community;
  space: Space;
  event: SpaceEvent;
  permissions: EventPermissions;
  now: Date;
  /**
   * The event changed on the server: the page gets it again
   */
  onChanged: () => void;
}

type Action = 'publish' | 'cancel' | 'publish-again' | 'draft' | 'duplicate' | 'delete';

/**
 * What the reader may do with an event, on its page (#1514, design 6.3 point 3, ADR 0053 points 4 and 5): "Edit" and
 * "Publish" as buttons, and a menu "Event actions" with "Cancel event", "Publish again", "Back to draft", "Duplicate"
 * and "Delete". Each entry shows only when the state of the event and the rights of the reader allow it: a moderator
 * cancels and deletes the event of another host, and cannot edit it. A cancel and a delete ask first. Every change
 * goes through the API; a refusal of the server shows as an alert, with its words.
 *
 * "Answers" (#1515, design 6.4 point 3) is next to "Edit" for the readers of the answers (`permissions.canViewAnswers`:
 * the host, the owner and the admins, never for a draft, never a moderator). It opens the list in a dialog.
 *
 * "Cancel it first" (#1516, design 7.2 point 3): when the server would refuse the delete (`mustCancelBeforeDelete`: a
 * published event that is not over, with a Yes or a Replay only answer), "Delete" asks "Cancel this event first?" and its
 * "Cancel event" runs the cancel of the menu. A refusal of the server after the page loaded shows in the alert, as today.
 */
export function EventActions({ community, space, event, permissions, now, onChanged }: EventActionsProps) {
  const { showToast } = useAppData();
  const [busy, setBusy] = useState<Action | null>(null);
  const busyRef = useRef(false);
  const [confirming, setConfirming] = useState<'cancel' | 'delete' | 'cancel-first' | null>(null);
  // The dialog "Answers" is open. A load of the page (a live update) keeps it open
  const [showingAnswers, setShowingAnswers] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // A change of state can remove the control that had the focus ("Publish" goes when the draft is published): the
  // focus then goes to the button of the menu, else to "Edit", so that a keyboard user keeps their place
  const shownState = useRef(event.state);
  useEffect(() => {
    if (shownState.current === event.state) {
      return;
    }
    shownState.current = event.state;
    const active = document.activeElement;
    if (active === null || active === document.body) {
      const container = containerRef.current;
      (
        container?.querySelector<HTMLElement>('[data-slot="event-menu"] button') ?? container?.querySelector<HTMLElement>('a, button')
      )?.focus();
    }
  }, [event.state]);
  const ids = [community.cuid, space.cuid, event.cuid] as const;
  const slugs = { communitySlug: community.slug, spaceSlug: space.slug };

  const canPublish = event.state === 'draft' && permissions.canUpdate;
  const canCancel = event.state === 'published' && permissions.canCancel;
  const canPublishAgain = event.state === 'cancelled' && permissions.canUpdate;
  // An event that started cannot go back to draft (ADR 0053 point 4): the server refuses it, so the menu does not offer it
  const canBackToDraft = event.state === 'published' && permissions.canUpdate && startsInTheFuture(event, now);
  const hasMenu = canCancel || canPublishAgain || canBackToDraft || permissions.canDuplicate || permissions.canDelete;

  if (!permissions.canUpdate && !permissions.canViewAnswers && !hasMenu) {
    return null;
  }

  /**
   * Run one call. One at a time: a second click does nothing while the first one works. False when the server refused.
   */
  const run = async <T,>(
    action: Action,
    call: () => Promise<{ ok: true; data: T } | { ok: false; message: string }>,
  ): Promise<T | false> => {
    if (busyRef.current) {
      return false;
    }
    busyRef.current = true;
    setBusy(action);
    setError(null);
    const result = await call();
    busyRef.current = false;
    setBusy(null);
    if (!result.ok) {
      // The words of the server: "An event that started cannot go back to draft. Cancel it instead."
      setError(result.message);
      return false;
    }
    return result.data;
  };

  const setState = async (action: Action, state: EventState, done: string) => {
    const changed = await run(action, () => knowiiApiClient.communities.events.setState(...ids, state));
    setConfirming(null);
    if (changed !== false) {
      showToast({ severity: 'success', summary: done });
      onChanged();
    }
  };

  const duplicate = async () => {
    const copy = await run('duplicate', () => knowiiApiClient.communities.events.duplicate(...ids));
    if (copy !== false) {
      showToast({ severity: 'success', summary: EVENT_WORDS.eventDuplicated });
      // The copy is a draft, 7 days later: its form opens, to check the date
      router.visit(route(EVENT_EDIT_URL, { ...slugs, eventSlug: copy.slug }));
    }
  };

  const remove = async () => {
    const deleted = await run('delete', async () => {
      const result = await knowiiApiClient.communities.events.delete(...ids);
      return result.ok ? { ok: true as const, data: true } : result;
    });
    setConfirming(null);
    if (deleted !== false) {
      showToast({ severity: 'success', summary: EVENT_WORDS.eventDeleted });
      router.visit(route(SPACE_URL, slugs));
    }
  };

  // A question that closes without an answer: the focus goes back to the button of the menu (its entry is gone)
  const focusMenuButton = () => {
    const active = document.activeElement;
    if (active === null || active === document.body) {
      containerRef.current?.querySelector<HTMLElement>('[data-slot="event-menu"] button')?.focus();
    }
  };

  return (
    <div ref={containerRef} className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center gap-2">
        {permissions.canUpdate && (
          <LinkButton variant="secondary" href={route(EVENT_EDIT_URL, { ...slugs, eventSlug: event.slug })}>
            <FaPen aria-hidden="true" />
            {EVENT_WORDS.edit}
          </LinkButton>
        )}
        {permissions.canViewAnswers && (
          <Button
            variant="secondary"
            className="h-11 md:h-10"
            leftIcon={<FaUsers aria-hidden="true" />}
            onClick={() => setShowingAnswers(true)}
          >
            {EVENT_WORDS.answers}
          </Button>
        )}
        {canPublish && (
          <Button loading={busy === 'publish'} onClick={() => void setState('publish', 'published', EVENT_WORDS.eventPublished)}>
            {EVENT_WORDS.publish}
          </Button>
        )}
        {hasMenu && (
          <span data-slot="event-menu" className="flex">
            <DropdownMenu
              label={EVENT_WORDS.actionsMenu}
              align="start"
              trigger={<FaEllipsis aria-hidden="true" className="size-4" />}
              triggerClassName="size-11 justify-center rounded-lg bg-overlay/10 hover:bg-overlay/15 focus-visible:outline-2 focus-visible:outline-primary md:size-10"
            >
              {canCancel && (
                <DropdownMenuItem>
                  <button type="button" className={dropdownMenuItemClasses} onClick={() => setConfirming('cancel')}>
                    {EVENT_WORDS.cancelEvent}
                  </button>
                </DropdownMenuItem>
              )}
              {canPublishAgain && (
                <DropdownMenuItem>
                  <button
                    type="button"
                    className={dropdownMenuItemClasses}
                    onClick={() => void setState('publish-again', 'published', EVENT_WORDS.eventPublished)}
                  >
                    {EVENT_WORDS.publishAgain}
                  </button>
                </DropdownMenuItem>
              )}
              {canBackToDraft && (
                <DropdownMenuItem>
                  <button
                    type="button"
                    className={dropdownMenuItemClasses}
                    onClick={() => void setState('draft', 'draft', EVENT_WORDS.eventBackToDraft)}
                  >
                    {EVENT_WORDS.backToDraft}
                  </button>
                </DropdownMenuItem>
              )}
              {permissions.canDuplicate && (
                <DropdownMenuItem>
                  <button type="button" className={dropdownMenuItemClasses} onClick={() => void duplicate()}>
                    {EVENT_WORDS.duplicate}
                  </button>
                </DropdownMenuItem>
              )}
              {permissions.canDelete && (
                <DropdownMenuItem>
                  <button
                    type="button"
                    className={dropdownMenuItemClasses}
                    onClick={() => setConfirming(permissions.mustCancelBeforeDelete ? 'cancel-first' : 'delete')}
                  >
                    {EVENT_WORDS.delete}
                  </button>
                </DropdownMenuItem>
              )}
            </DropdownMenu>
          </span>
        )}
      </div>

      {/* Always in the page and in the accessibility tree, so that a screen reader reads the refusal when it comes: empty,
          it takes no room, but it is never `display: none` */}
      <div role="alert" data-slot="event-actions-error" className="text-sm font-bold text-danger-text empty:sr-only">
        {error}
      </div>

      {permissions.canViewAnswers && (
        <EventAnswersDialog
          open={showingAnswers}
          onClose={() => setShowingAnswers(false)}
          community={community}
          space={space}
          event={event}
          onGone={onChanged}
        />
      )}

      <Dialog
        open={confirming === 'cancel'}
        onClose={() => setConfirming(null)}
        onAfterClose={focusMenuButton}
        size="sm"
        title={EVENT_WORDS.cancelQuestion}
        description={EVENT_WORDS.cancelExplanation}
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirming(null)} autoFocus data-autofocus>
              {EVENT_WORDS.keep}
            </Button>
            <Button
              variant="danger"
              loading={busy === 'cancel'}
              onClick={() => void setState('cancel', 'cancelled', EVENT_WORDS.eventCancelled)}
            >
              {EVENT_WORDS.cancelConfirm}
            </Button>
          </>
        }
      />
      {/* A published event that members still expect (#1516, design 7.2 point 3): the server refuses its delete, so
          "Delete" offers the cancel, which shows on the page, in "My events" and in the calendar file. It names no member
          and gives no count */}
      <Dialog
        open={confirming === 'cancel-first'}
        onClose={() => setConfirming(null)}
        onAfterClose={focusMenuButton}
        size="sm"
        title={EVENT_WORDS.cancelFirstQuestion}
        description={EVENT_WORDS.cancelFirstExplanation}
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirming(null)} autoFocus data-autofocus>
              {EVENT_WORDS.keep}
            </Button>
            <Button
              variant="danger"
              loading={busy === 'cancel'}
              onClick={() => void setState('cancel', 'cancelled', EVENT_WORDS.eventCancelled)}
            >
              {EVENT_WORDS.cancelConfirm}
            </Button>
          </>
        }
      />
      <Dialog
        open={confirming === 'delete'}
        onClose={() => setConfirming(null)}
        onAfterClose={focusMenuButton}
        size="sm"
        title={EVENT_WORDS.deleteQuestion}
        description={EVENT_WORDS.deleteExplanation}
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirming(null)} autoFocus data-autofocus>
              {EVENT_WORDS.keep}
            </Button>
            <Button variant="danger" loading={busy === 'delete'} onClick={() => void remove()}>
              {EVENT_WORDS.deleteConfirm}
            </Button>
          </>
        }
      />
    </div>
  );
}
