import type { PropsWithChildren } from 'react';
import { cn } from '@/Components/ui';

interface Props {
  className?: string;
}

export default function CardGroup(props: PropsWithChildren<Props>) {
  return (
    <ul className={cn('grid list-none grid-cols-1 gap-6 sm:grid-cols-[repeat(auto-fill,minmax(20rem,1fr))]', props.className)}>
      {props.children}
    </ul>
  );
}
