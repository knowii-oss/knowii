import type { PropsWithChildren } from 'react';
import { cn } from './cn';

export interface CardGroupProps {
  className?: string;
}

/**
 * The grid of a list of cards (the communities of the dashboard): one column on a phone, then as many columns of
 * 20 rem or more as the width holds.
 *
 * Not a `Card`, and not for a list of rows.
 *
 * Accessibility: a `ul`, a list for screen readers. Each child must be an `li` that holds one card.
 */
export function CardGroup(props: PropsWithChildren<CardGroupProps>) {
  return (
    <ul className={cn('grid list-none grid-cols-1 gap-6 sm:grid-cols-[repeat(auto-fill,minmax(20rem,1fr))]', props.className)}>
      {props.children}
    </ul>
  );
}
