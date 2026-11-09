import type { InputHTMLAttributes, ReactNode, Ref } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { cn } from './cn';
import type { FormControlSize } from './form-field';
import { formControlClasses, formControlIconPaddingClasses, formControlSizeClasses } from './form-field';
import { mergeRefs } from './merge-refs';

export interface TextInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> {
  ref?: Ref<HTMLInputElement>;
  size?: FormControlSize;
  /**
   * Decorative icon shown at the start of the field
   */
  icon?: ReactNode;
  /**
   * Marks the field as invalid (sets aria-invalid)
   */
  invalid?: boolean | undefined;
  /**
   * Only text that matches this regular expression can be typed or pasted (for example /^[0-9+ ]+$/)
   */
  allowedCharacters?: RegExp | undefined;
  /**
   * Element shown at the end of the field, inside its border (for example a button)
   */
  trailing?: ReactNode;
  /**
   * Classes for the wrapper element
   */
  wrapperClassName?: string;
}

export function TextInput({
  size = 'md',
  icon,
  invalid,
  allowedCharacters,
  trailing,
  className,
  wrapperClassName,
  type = 'text',
  ref,
  'aria-invalid': ariaInvalid,
  ...props
}: TextInputProps) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const mergedRef = useMemo(() => mergeRefs(inputRef, ref), [ref]);

  // Native listener: React's onBeforeInput is not the native "beforeinput" event and does not see pasted text
  useEffect(() => {
    const input = inputRef.current;
    if (!input || !allowedCharacters) {
      return;
    }

    const onBeforeInput = (event: InputEvent) => {
      const text = event.data ?? event.dataTransfer?.getData('text/plain') ?? null;
      if (text && !allowedCharacters.test(text)) {
        event.preventDefault();
      }
    };

    input.addEventListener('beforeinput', onBeforeInput);
    return () => input.removeEventListener('beforeinput', onBeforeInput);
  }, [allowedCharacters]);

  return (
    <div className={cn('relative w-full', wrapperClassName)}>
      {icon && (
        <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-muted">
          {icon}
        </span>
      )}
      <input
        ref={mergedRef}
        type={type}
        aria-invalid={invalid || ariaInvalid || undefined}
        className={cn(
          formControlClasses,
          formControlSizeClasses[size],
          icon ? formControlIconPaddingClasses[size] : undefined,
          trailing ? 'pr-12' : undefined,
          className,
        )}
        {...props}
      />
      {trailing && <span className="absolute inset-y-0 right-0 flex items-center pr-1">{trailing}</span>}
    </div>
  );
}
