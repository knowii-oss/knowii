import { Description } from '@headlessui/react';
import { formatFullDateTime } from '@knowii/common';
import { Button, useAccountTimeZone } from '@/Components/ui';
import type { KeptTextState } from './use-kept-text';

export interface KeptTextNoticeProps {
  state: KeptTextState;
  /**
   * The time of the last change of the kept text, in milliseconds
   */
  changedAt: number | null;
  /**
   * The word for the item in "The page changed after that": page or lesson
   */
  noun?: string;
  /**
   * "Use my text", for the state "changed" (an edit)
   */
  onUse?: (() => void) | undefined;
  onDiscard: () => void;
}

/**
 * The line above a form that shows a kept text (#1622). It is a description of the dialog that holds it, so a screen
 * reader reads it when the dialog opens, without an alert and without taking the focus. The handlers of the buttons
 * move the focus into the form: the line goes away after them.
 */
export function KeptTextNotice({ state, changedAt, noun = 'page', onUse, onDiscard }: KeptTextNoticeProps) {
  const zone = useAccountTimeZone();
  if (state === 'none') {
    return null;
  }
  const text =
    state === 'kept' || changedAt === null
      ? 'We kept the text that you did not send.'
      : `You have unsent text from ${formatFullDateTime(new Date(changedAt), zone)}. The ${noun} changed after that.`;

  return (
    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-lg border border-border bg-well px-3 py-2">
      <Description as="p" className="text-sm">
        {text}
      </Description>
      <div className="flex flex-wrap gap-2">
        {state === 'changed' && onUse && (
          <Button size="sm" variant="secondary" onClick={onUse}>
            Use my text
          </Button>
        )}
        <Button size="sm" variant="secondary" onClick={onDiscard}>
          Discard
        </Button>
      </div>
    </div>
  );
}
