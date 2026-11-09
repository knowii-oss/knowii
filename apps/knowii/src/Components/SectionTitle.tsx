import type { ReactNode } from 'react';

interface Props {
  title: string;
  description: string | ReactNode;
}

export default function SectionTitle({ title, description }: Props) {
  return (
    <div className="md:col-span-1">
      <div className="px-4 sm:px-0">
        <h2 className="text-lg font-extrabold md:text-xl">{title}</h2>
        <span aria-hidden="true" className="mt-2 block h-1 w-12 rounded-full bg-secondary" />
        <div className="mt-3 text-sm text-muted">{description}</div>
      </div>
    </div>
  );
}
