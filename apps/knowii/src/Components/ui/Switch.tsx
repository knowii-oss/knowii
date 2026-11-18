import type { ReactNode, Ref } from 'react';
import { useMemo, useRef } from 'react';
import { Description, Field, Label, Switch as HeadlessSwitch } from '@headlessui/react';
import { cn } from './cn';
import { mergeRefs } from './merge-refs';

export interface SwitchProps {
  checked: boolean;
  /**
   * Called with the new value on a click (also on the label) and on Space. Never called while the switch is busy or
   * disabled.
   */
  onChange: (checked: boolean) => void;
  /**
   * The name of the switch: one short line, in bold
   */
  label: ReactNode;
  /**
   * What the switch does, below the label. The switch is described by it (aria-describedby).
   */
  description?: ReactNode;
  /**
   * Shown below the description, for example a `<SaveStatus>`
   */
  status?: ReactNode;
  /**
   * The value is being saved: the switch keeps the focus (it is not disabled), gets aria-busy and aria-disabled, and
   * ignores a click, Space and Enter (#1564)
   */
  busy?: boolean | undefined;
  /**
   * Out of the tab order. Only for a switch that does not have the focus: use `busy` while a value is saved.
   */
  disabled?: boolean | undefined;
  className?: string | undefined;
  ref?: Ref<HTMLButtonElement>;
}

/**
 * A setting that is on or off and that applies at once, without a "Save" button (#1569). Put a `<SaveStatus>` in
 * `status` when the change is saved on the server. In a form with a "Save" button, use a `<Checkbox>`.
 *
 * Built on the Headless UI Switch: a button with role="switch" and aria-checked, named by the label and described by
 * the description. The label toggles it too. On a phone (below `md`) the target is 44 px high and wide.
 */
export function Switch({ checked, onChange, label, description, status, busy = false, disabled = false, className, ref }: SwitchProps) {
  const switchRef = useRef<HTMLButtonElement | null>(null);
  const mergedRef = useMemo(() => mergeRefs(switchRef, ref), [ref]);

  const change = (next: boolean) => {
    // A guard, not `disabled`: a busy switch keeps the focus
    if (busy || disabled) {
      return;
    }
    onChange(next);
  };

  return (
    <Field disabled={disabled} className={cn('flex items-start justify-between gap-4', className)}>
      <div className="min-w-0 flex-1">
        <Label
          // A click on the label takes the focus from the page: the switch gets it, also while it is busy (then Headless UI
          // does not click it)
          onClick={() => {
            if (!disabled) {
              switchRef.current?.focus({ preventScroll: true });
            }
          }}
          className="block cursor-pointer font-bold break-words data-disabled:cursor-not-allowed"
        >
          {label}
        </Label>
        {description !== undefined && description !== null && (
          <Description className="block text-sm break-words text-muted">{description}</Description>
        )}
        {status}
      </div>
      <HeadlessSwitch
        ref={mergedRef}
        checked={checked}
        onChange={change}
        aria-busy={busy}
        aria-disabled={busy}
        className={cn(
          // 44 px on a phone (WCAG 2.5.5), centered on the first line of the label; the size of the track from md
          'group -my-2.5 inline-flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center focus:outline-none md:my-0 md:h-6',
          'aria-busy:cursor-wait data-disabled:cursor-not-allowed',
        )}
      >
        <span
          aria-hidden="true"
          className={cn(
            'inline-flex h-6 w-11 items-center rounded-full border border-border-strong bg-well transition-colors',
            'group-data-hover:border-overlay/60 group-data-checked:border-secondary group-data-checked:bg-secondary',
            'group-data-focus:outline-2 group-data-focus:outline-offset-2 group-data-focus:outline-primary',
            'group-data-disabled:opacity-60 motion-reduce:transition-none',
          )}
        >
          <span
            className={cn(
              'size-4 translate-x-1 rounded-full bg-muted transition-transform',
              'group-data-checked:translate-x-5.5 group-data-checked:bg-white',
              'group-aria-busy:animate-pulse motion-reduce:transition-none motion-reduce:group-aria-busy:animate-none',
            )}
          />
        </span>
      </HeadlessSwitch>
    </Field>
  );
}
