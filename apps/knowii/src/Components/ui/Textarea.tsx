import type { ReactNode, Ref, TextareaHTMLAttributes } from 'react';
import { cn } from './cn';
import { formControlClasses } from './form-field';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  ref?: Ref<HTMLTextAreaElement>;
  /**
   * Decorative icon shown at the top left of the field
   */
  icon?: ReactNode;
  invalid?: boolean | undefined;
  wrapperClassName?: string;
}

export function Textarea({ icon, invalid, className, wrapperClassName, ref, 'aria-invalid': ariaInvalid, ...props }: TextareaProps) {
  return (
    <div className={cn('relative w-full', wrapperClassName)}>
      {icon && (
        <span
          aria-hidden="true"
          className="pointer-events-none absolute top-0 left-0 flex h-11 w-10 items-center justify-center text-muted"
        >
          {icon}
        </span>
      )}
      <textarea
        ref={ref}
        aria-invalid={invalid || ariaInvalid || undefined}
        className={cn(formControlClasses, 'min-h-24 resize-y px-3 py-2.5 text-base', icon ? 'pl-10' : undefined, className)}
        {...props}
      />
    </div>
  );
}
