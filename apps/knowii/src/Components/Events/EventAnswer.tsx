import { useEffect, useId, useRef, useState } from 'react';
import type { ApiResult, Community, EventAnswerValue, Space, SpaceEvent } from '@knowii/common';
import { HttpStatus, knowiiApiClient, toDate } from '@knowii/common';
import { SignInPrompt, useIsVisitor } from '@/Components/Reader/Reader';
import type { SaveState, SegmentedControlOption } from '@/Components/ui';
import { Button, Dialog, focusInView, InputError, SaveStatus, SegmentedControl, useFocusAfterRender } from '@/Components/ui';
import { EVENT_WORDS } from './event-words';

export interface EventAnswerProps {
  community: Pick<Community, 'cuid'>;
  space: Pick<Space, 'cuid'>;
  /**
   * The event of the page. The component reads the answer of the reader from it and sends no request to show it
   */
  event: SpaceEvent;
  /**
   * The reader is a member of the community: only a member answers
   */
  isMember: boolean;
  now: Date;
  /**
   * A save starts (true) and ends (false): the page waits with its own load of the event while one runs
   */
  onSaving?: (saving: boolean) => void;
  /**
   * The server took the answer, or refused it: the page gets the event again (the places, the meeting address)
   */
  onSaved: () => void;
  /**
   * The event is no longer for this reader (403, 404): the page loads again, and the server decides what it shows
   */
  onGone: () => void;
}

type Failure = Extract<ApiResult<unknown>, { ok: false }>;

/**
 * The choice that waits behind the question "Give up your place?": an answer, or null for "Clear my answer"
 */
interface WaitingChoice {
  target: EventAnswerValue | null;
}

/**
 * Where the focus goes when the question is closed: the radio that is checked, or the first radio after a clear
 */
type FocusAfterQuestion = 'checked' | 'first';

/**
 * Tells when the question is out of the page. It renders nothing, inside the dialog: the dialog gives the focus back to
 * the element that had it before (the radio that the member pressed) when it leaves the page, and this runs after it
 */
function AfterTheQuestion({ onGone }: { onGone: () => void }) {
  const latest = useRef(onGone);
  useEffect(() => {
    latest.current = onGone;
  });
  useEffect(
    () => () => {
      // The dialog moves the focus in a microtask of its own, which started before this one
      queueMicrotask(() => latest.current());
    },
    [],
  );
  return null;
}

/**
 * The answer of a member to an event, on its page (#1515, ADR 0053, design 3.6, 6.4, 7.6 and 11.4): "Yes", "Replay
 * only" (when the host says that a replay will exist) or "No", in one group of radios that saves at once.
 *
 * The group: a `fieldset` with the visible legend "Your answer". The radio group inside it takes the legend as its
 * name, and the fieldset itself is no second group for a screen reader, so the name is said one time. Who sees the
 * answer is the description of the group, at all times. With a limit of places, a line says how many are left, in
 * plain text. "Yes" on a full event cannot be chosen: `aria-disabled`, with the line "Full" as its reason.
 *
 * A save: the new choice shows at once, the group is busy (`aria-busy`, never `disabled`: the focus stays), and a
 * polite status says "Saving", then "Saved: Yes". A choice that changes while a save runs waits, and only the last
 * one is sent after it: the arrow keys pass "Replay only" on the way from "Yes" to "No". A refusal checks the answer
 * that the server holds again and shows its words in an alert. After each save the page gets the event again. A save
 * that ends after the member left the page calls nothing.
 *
 * One choice asks first (the decision of the owner of 2026-11-20): a member with a Yes on a full event who chooses
 * "Replay only", "No" or "Clear my answer" gives the place away, and another member can take it before a correction.
 * The question "Give up your place?" opens, and nothing is sent before "Give up my place". "Keep my place", Escape and
 * a click outside send nothing: "Yes" is checked again and takes the focus. The question never closes by itself: an
 * event that changes while it is open (a place that frees up, a cancel) saves nothing, and the choice is then saved
 * against the event as it is, with the alert of a refusal as for every save.
 *
 * The event of the page can change at any time (a load, a second tab of the member). While a save runs, the choice
 * that is being saved stays on the screen, and the answer of the server shows after it. An answer that this page did
 * not save empties the status: "Saved: Yes" is never next to another answer.
 *
 * A member reads their old answer as text when the event takes no answers any more (cancelled, over). A visitor reads
 * "Sign in to answer". A person who is not a member, and every reader of a draft, get nothing.
 *
 * The alert of a refusal stays when the event that the page then loads takes the group away ("This event is cancelled.
 * It takes no answers."): it shows under the heading, with the old answer when one exists. The focus was in the block,
 * and it stays there: on the heading when the group went away, on a radio of the group when its own radio went away
 * ("Replay only" after "This event has no replay.").
 */
export function EventAnswer({ community, space, event, isMember, now, onSaving, onSaved, onGone }: EventAnswerProps) {
  const isVisitor = useIsVisitor();
  const id = useId();
  const sectionRef = useRef<HTMLElement>(null);
  const groupRef = useRef<HTMLFieldSetElement>(null);
  // The heading of the block when it shows no group: it takes the focus when a refusal took the group away
  const headingRef = useRef<HTMLHeadingElement>(null);
  const focusAfterRender = useFocusAfterRender();

  // The choice on the screen: the one that is being saved, else the answer of the event
  const [shown, setShown] = useState<EventAnswerValue | null>(event.my_answer);
  const [propAnswer, setPropAnswer] = useState<EventAnswerValue | null>(event.my_answer);
  const [busy, setBusy] = useState(false);
  const [saveState, setSaveState] = useState<SaveState>({ status: 'idle' });
  // What the status says after a save: "Saved: Yes", or "Answer cleared"
  const [savedText, setSavedText] = useState('');
  const [error, setError] = useState<string | null>(null);
  // The guard of the saves: the state of the render can be late
  const running = useRef(false);
  // The last choice of the member, and the answer that the server holds
  const wanted = useRef<EventAnswerValue | null>(event.my_answer);
  const confirmed = useRef<EventAnswerValue | null>(event.my_answer);
  // The answer of the last event that the page gave
  const latest = useRef<EventAnswerValue | null>(event.my_answer);
  // The member left the page: a save that still runs calls nothing
  const mounted = useRef(true);
  // A refusal left the focus in the block: it stays in the block when the next event removes the element that holds it
  const keepFocus = useRef(false);
  // The question "Give up your place?" is open. The ref holds the choice that waits, and stops every other choice
  const [asking, setAsking] = useState(false);
  const waiting = useRef<WaitingChoice | null>(null);
  // The question closes: the focus is still in it, and it goes to the block once the question is gone
  const focusAfterQuestion = useRef<FocusAfterQuestion | null>(null);

  const over = event.is_past || toDate(event.ends_at).getTime() <= now.getTime();
  // The event takes the answer of this reader: the group shows
  const open = isMember && event.state !== 'draft' && event.can_answer && !over;
  const [wasOpen, setWasOpen] = useState(open);

  // The page got the event again: the group follows it, but never under a save that runs
  if (propAnswer !== event.my_answer) {
    setPropAnswer(event.my_answer);
    if (!busy && shown !== event.my_answer) {
      setShown(event.my_answer);
      // Not the answer that this page saved (a second tab of the member): "Saved: Yes" is no longer true
      setSaveState({ status: 'idle' });
      setSavedText('');
    }
  }
  // The group is back (the event was published again): the refusal of before is about another state of the event
  if (wasOpen !== open) {
    setWasOpen(open);
    if (open) {
      setError(null);
    }
  }
  useEffect(() => {
    latest.current = event.my_answer;
    if (!running.current) {
      wanted.current = event.my_answer;
      confirmed.current = event.my_answer;
    }
  }, [event.my_answer]);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);
  // After each render: the event of the page can remove the element that holds the focus
  useEffect(() => {
    if (!keepFocus.current || focusAfterQuestion.current !== null) {
      // Nothing to keep, or the question still holds the focus: it gives the focus to the block when it is gone
      return;
    }
    const active = document.activeElement;
    if (active !== null && active !== document.body) {
      // The focus is still on an element. Outside the block: the member moved on
      keepFocus.current = sectionRef.current?.contains(active) === true;
      return;
    }
    keepFocus.current = false;
    const target =
      headingRef.current ??
      groupRef.current?.querySelector<HTMLElement>('input[type="radio"]:checked') ??
      groupRef.current?.querySelector<HTMLElement>('input[type="radio"]');
    if (target) {
      focusInView(target);
    }
  });

  const ids = [community.cuid, space.cuid, event.cuid] as const;
  const placesId = `${id}-places`;
  // The answer of the event, not the choice on the screen: a member who leaves a Yes keeps "Yes" until the server says
  // that the place went to another member
  const yesIsFull = event.places_left === 0 && event.my_answer !== 'yes';
  const values: EventAnswerValue[] =
    event.replay_expected || event.my_answer === 'replay' || shown === 'replay' ? ['yes', 'replay', 'no'] : ['yes', 'no'];
  const options: SegmentedControlOption<EventAnswerValue>[] = values.map((value) => ({
    value,
    label: EVENT_WORDS.answerNames[value],
    ...(value === 'yes' && yesIsFull ? { ariaDisabled: true, describedBy: placesId } : {}),
  }));

  /**
   * Send the last choice, then the one that came while it was saved, until the server holds what the member wants
   */
  const run = async () => {
    running.current = true;
    keepFocus.current = false;
    setBusy(true);
    setError(null);
    setSaveState({ status: 'saving' });
    onSaving?.(true);
    let failure: Failure | null = null;
    let savedOne = false;
    while (wanted.current !== confirmed.current) {
      const target = wanted.current;
      const result =
        target === null
          ? await knowiiApiClient.communities.events.answer.clear(...ids)
          : await knowiiApiClient.communities.events.answer.set(...ids, target);
      if (!result.ok) {
        failure = result;
        break;
      }
      confirmed.current = target;
      savedOne = true;
    }
    running.current = false;
    if (!mounted.current) {
      // The member left the page: the next page has no event to load again, and nothing here shows any more
      return;
    }
    setBusy(false);
    onSaving?.(false);

    if (failure !== null) {
      // The old choice: the answer that the server holds. When no choice was saved, an event that the page got while
      // the save ran is newer than the answer of before the save
      if (!savedOne) {
        confirmed.current = latest.current;
      }
      wanted.current = confirmed.current;
      setShown(confirmed.current);
      setSaveState({ status: 'idle' });
      if (failure.status === HttpStatus.FORBIDDEN || failure.status === HttpStatus.NOT_FOUND) {
        onGone();
        return;
      }
      // The words of the server: "This event is full.", or the error of the field ("This event has no replay.")
      setError(failure.errors['answer']?.[0] ?? failure.message);
      // The question that closes still holds the focus: it comes to the block
      keepFocus.current = focusAfterQuestion.current !== null || sectionRef.current?.contains(document.activeElement) === true;
      onSaved();
      return;
    }
    setSavedText(confirmed.current === null ? EVENT_WORDS.answerCleared : EVENT_WORDS.saved(confirmed.current));
    setSaveState({ status: 'saved' });
    onSaved();
  };

  const save = (target: EventAnswerValue | null) => {
    wanted.current = target;
    setShown(target);
    if (!running.current && target !== confirmed.current) {
      void run();
    }
  };

  /**
   * A choice of the member. True when it waits behind the question: nothing is sent before the member confirms
   */
  const choose = (target: EventAnswerValue | null): boolean => {
    if (waiting.current !== null) {
      // The question is open: no second choice, the first one waits for its answer
      return true;
    }
    // The server holds a Yes on a full event, and the member leaves it: the place goes to the next member who asks.
    // While a save runs, the member already left the Yes (and confirmed it when the event was full)
    if (target !== 'yes' && !running.current && confirmed.current === 'yes' && event.places_left === 0) {
      waiting.current = { target };
      // The choice shows behind the question, as the arrow key moved it
      setShown(target);
      setAsking(true);
      return true;
    }
    save(target);
    return false;
  };

  const clear = () => {
    if (!choose(null)) {
      // The button goes away with the answer: the first radio takes the focus
      focusAfterRender(() => groupRef.current?.querySelector<HTMLElement>('input[type="radio"]'));
    }
  };

  /**
   * "Give up my place": the choice that waited is saved like every other choice
   */
  const giveUpPlace = () => {
    const choice = waiting.current;
    if (choice === null) {
      return;
    }
    waiting.current = null;
    focusAfterQuestion.current = choice.target === null ? 'first' : 'checked';
    setAsking(false);
    save(choice.target);
  };

  /**
   * "Keep my place", Escape, a click outside: nothing is sent, and the answer that the server holds is checked again
   */
  const keepPlace = () => {
    if (waiting.current === null) {
      return;
    }
    waiting.current = null;
    focusAfterQuestion.current = 'checked';
    setAsking(false);
    setShown(confirmed.current);
  };

  /**
   * The question is gone: the focus goes back to the block. After "Keep my place" the checked radio is "Yes". When the
   * event took the group away meanwhile, the heading takes it
   */
  const focusAfterTheQuestion = () => {
    const where = focusAfterQuestion.current;
    focusAfterQuestion.current = null;
    if (where === null) {
      return;
    }
    const group = groupRef.current;
    const target =
      (where === 'checked' ? group?.querySelector<HTMLElement>('input[type="radio"]:checked') : null) ??
      group?.querySelector<HTMLElement>('input[type="radio"]') ??
      headingRef.current;
    if (target) {
      focusInView(target);
    }
  };

  const question = (
    <Dialog
      open={asking}
      onClose={keepPlace}
      size="sm"
      title={EVENT_WORDS.giveUpPlaceQuestion}
      description={EVENT_WORDS.giveUpPlaceExplanation}
      footer={
        <>
          {/* 44 px of height on a small screen, like the radios. The safe button takes the focus first */}
          <Button variant="secondary" className="h-11 md:h-10" onClick={keepPlace} autoFocus data-autofocus>
            {EVENT_WORDS.keepPlace}
          </Button>
          <Button variant="danger" className="h-11 md:h-10" onClick={giveUpPlace}>
            {EVENT_WORDS.giveUpPlaceConfirm}
          </Button>
          <AfterTheQuestion onGone={focusAfterTheQuestion} />
        </>
      }
    />
  );

  if (!isMember) {
    // A person who is not a member gets nothing. A visitor can sign in, while the event still takes answers
    if (!isVisitor || event.state !== 'published' || over) {
      return null;
    }
    return (
      <section aria-labelledby={`${id}-title`} data-slot="event-answer" className="flex flex-col gap-2">
        <h2 id={`${id}-title`} className="text-sm font-bold text-muted">
          {EVENT_WORDS.yourAnswer}
        </h2>
        <SignInPrompt>{EVENT_WORDS.signInToAnswer}</SignInPrompt>
      </section>
    );
  }

  if (!open) {
    // A cancelled event and an event that ended keep the answer, as text. A draft shows no answer
    const oldAnswer = event.state === 'draft' ? null : event.my_answer;
    // The alert of a refusal stays: the event that the page loaded after it took the group away. The question keeps its
    // place in the tree when the group goes away: an event that changes while it is open does not close it
    return (
      <>
        {(oldAnswer !== null || error !== null) && (
          <section ref={sectionRef} aria-labelledby={`${id}-title`} data-slot="event-answer" className="flex flex-col gap-2">
            <h2
              id={`${id}-title`}
              ref={headingRef}
              tabIndex={-1}
              className="text-sm font-bold text-muted outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
              {EVENT_WORDS.yourAnswer}
            </h2>
            {oldAnswer !== null && <p className="font-bold">{EVENT_WORDS.oldAnswer(oldAnswer)}</p>}
            <InputError message={error ?? undefined} />
          </section>
        )}
        {question}
      </>
    );
  }

  return (
    <>
      <section ref={sectionRef} data-slot="event-answer" className="flex flex-col items-start gap-2">
        {/* The fieldset holds the legend that the eye reads. For a screen reader the radio group is the one group, with
            the legend as its name: a named fieldset around it would say "Your answer" two times */}
        <fieldset role="presentation" ref={groupRef} className="min-w-0">
          <legend id={`${id}-legend`} className="mb-2 text-sm font-bold text-muted">
            {EVENT_WORDS.yourAnswer}
          </legend>
          <SegmentedControl<EventAnswerValue>
            aria-labelledby={`${id}-legend`}
            // 44 px of height for each radio on a small screen
            className="[&_label]:min-h-11 md:[&_label]:min-h-0"
            options={options}
            value={shown}
            busy={busy}
            onChange={choose}
            description={EVENT_WORDS.answerPrivacy}
          />
        </fieldset>
        {event.places_left !== null && (
          <p id={placesId} data-slot="event-places" className="text-sm text-muted">
            {event.places_left === 0 ? EVENT_WORDS.full : EVENT_WORDS.placesLeft(event.places_left)}
          </p>
        )}
        <SaveStatus state={saveState} savedText={savedText} className="mt-0" />
        <InputError message={error ?? undefined} />
        {shown !== null && (
          <Button variant="secondary" size="sm" className="h-11 md:h-8" onClick={clear}>
            {EVENT_WORDS.clearAnswer}
          </Button>
        )}
      </section>
      {question}
    </>
  );
}
