import type { PropsWithChildren } from 'react';

interface Props {
  value?: string;
  htmlFor?: string;
  id?: string;
}

export default function InputLabel({ value, htmlFor, id, children }: PropsWithChildren<Props>) {
  return (
    <label className="block text-sm font-bold text-primary" htmlFor={htmlFor} id={id}>
      {value || children}
    </label>
  );
}
