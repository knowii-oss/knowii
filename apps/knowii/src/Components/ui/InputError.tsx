import type { PropsWithChildren } from 'react';

export interface InputErrorProps {
  message?: string | undefined;
  className?: string | undefined;
  /**
   * Lets the field reference the message with aria-describedby
   */
  id?: string | undefined;
}

/**
 * The text of the error of one form field, under the field. It renders nothing without a message and without
 * children, so a form always renders it. `message` comes first; the children show only without it.
 *
 * Not the status of a save (`SaveStatus`), not a hint under a field, and not an error of the whole page.
 *
 * Accessibility: a paragraph with `role="alert"`, so a screen reader says it when it shows. Give it an `id` and name
 * that id in `aria-describedby` of the field: the message is then the description of the field.
 */
export function InputError({ message, className, id, children }: PropsWithChildren<InputErrorProps>) {
  if (!message && !children) {
    return null;
  }
  return (
    <div className={className}>
      <p id={id} className="text-sm text-danger-text" role="alert">
        {message || children}
      </p>
    </div>
  );
}
