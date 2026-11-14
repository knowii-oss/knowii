import type { InputHTMLAttributes, Ref } from 'react';
import { cn } from './cn';

export interface RadioProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  ref?: Ref<HTMLInputElement>;
}

/**
 * Native radio button with the Knowii look. Wrap it in a <label> (or give it an id and a <label htmlFor>), and give
 * the radios of one group the same `name`: Tab moves into the group and the arrow keys change the choice.
 * 24 px: the minimum target size of WCAG 2.5.8 (#1264).
 */
export function Radio({ className, ref, ...props }: RadioProps) {
  return (
    <span className={cn('relative inline-flex size-6 shrink-0', className)}>
      <input
        ref={ref}
        type="radio"
        className={cn(
          'peer size-6 cursor-pointer appearance-none rounded-full border border-border-strong bg-well transition-colors',
          'checked:border-secondary hover:border-overlay/60',
          'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
          'disabled:cursor-not-allowed disabled:opacity-60',
        )}
        {...props}
      />
      <span
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 m-auto hidden size-3 rounded-full bg-secondary peer-checked:block"
      />
    </span>
  );
}
