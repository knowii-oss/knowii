import type { InputHTMLAttributes, Ref } from 'react';
import { FaCheck } from 'react-icons/fa';
import { cn } from './cn';

export interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  ref?: Ref<HTMLInputElement>;
}

/**
 * Native checkbox with the Knowii look. Wrap it in a <label> (or give it an id and a <label htmlFor>).
 */
export function Checkbox({ className, ref, ...props }: CheckboxProps) {
  return (
    <span className={cn('relative inline-flex size-5 shrink-0', className)}>
      <input
        ref={ref}
        type="checkbox"
        className={cn(
          'peer size-5 cursor-pointer appearance-none rounded-md border border-border-strong bg-well transition-colors',
          'checked:border-secondary checked:bg-secondary hover:border-overlay/60',
          'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
          'disabled:cursor-not-allowed disabled:opacity-60',
        )}
        {...props}
      />
      <FaCheck aria-hidden="true" className="pointer-events-none absolute inset-0 m-auto hidden size-3 text-white peer-checked:block" />
    </span>
  );
}
