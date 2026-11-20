import { cn } from './cn';

/**
 * new: the item has content that the person did not see. online: the person is here now.
 */
export type DotTone = 'new' | 'online';

const toneClasses: Record<DotTone, string> = {
  // The border has the color of the text: the dot keeps its shape in forced colors
  new: 'size-2.5 rounded-full border-2 border-current bg-secondary',
  // The ring has the color of the surface, so the dot stands out on the avatar under it
  online: 'size-2.5 rounded-full bg-success ring-2 ring-surface',
};

export interface DotProps {
  tone: DotTone;
  /**
   * The text for screen readers, next to the shape: ", New content". It becomes part of the name of the link around
   * the dot. Without it the dot is decoration.
   */
  label?: string | undefined;
  /**
   * The place of the dot: `shrink-0` in a row, `absolute right-0 bottom-0` for the corner of an avatar
   */
  className?: string | undefined;
}

/**
 * A small round mark next to an item: new content in a space, a member who is online. Put it in a flex row, or in the
 * corner of a `relative` box.
 *
 * Never a control, and never the only sign of a state: a color and a shape do not reach every person. Not a count
 * (`CountBadge`) and not a state in words (`Badge`).
 *
 * The round shape is always hidden from screen readers. With `label`, the text sits next to it for screen readers
 * only. Without `label` the dot is decoration: the item must then say the same thing in words elsewhere (the heading
 * "Online now" of a list, the name of a link).
 */
export function Dot({ tone, label, className }: DotProps) {
  if (label === undefined) {
    return <span aria-hidden="true" className={cn('block', toneClasses[tone], className)} />;
  }

  return (
    <span className={className}>
      <span aria-hidden="true" className={cn('block', toneClasses[tone])} />
      <span className="sr-only">{label}</span>
    </span>
  );
}
