import type { PropsWithChildren } from 'react';

interface Props {
  message?: string | undefined;
  className?: string | undefined;
  /**
   * Lets the field reference the message with aria-describedby
   */
  id?: string | undefined;
}

export default function InputError({ message, className, id, children }: PropsWithChildren<Props>) {
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
