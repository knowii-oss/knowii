import type { PropsWithChildren } from 'react';
import { cn } from '@/Components/ui';

interface Props {
  className?: string;
}

export default function CardGroup(props: PropsWithChildren<Props>) {
  return <ul className={cn('flex list-none flex-wrap justify-center gap-6 md:justify-start', props.className)}>{props.children}</ul>;
}
