export type CountBadgePlacement = 'inline' | 'corner';

const placementClasses: Record<CountBadgePlacement, string> = {
  inline: 'shrink-0 rounded-full bg-secondary px-1.5 text-xs font-bold text-white',
  // The ring has the color of the header (`well`), where the icon buttons with a count are
  corner:
    'absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-well bg-secondary px-1 text-xs leading-none font-bold text-white',
};

export interface CountBadgeProps {
  /**
   * The number of new things. Zero or less renders nothing.
   */
  count: number;
  /**
   * Above this number the text is the maximum and "+": 12 with a maximum of 9 shows "9+"
   */
  max?: number | undefined;
  /**
   * The words after the number, for screen readers: "unread" gives "3 unread". Without it the badge is hidden from
   * screen readers: the control around it must hold the number in its own name.
   */
  label?: string | undefined;
  /**
   * inline (default): in the row of a link, after its text. corner: on the top right corner of an icon button, which
   * must be `relative`.
   */
  placement?: CountBadgePlacement | undefined;
}

/**
 * The number of new things on a link or on a button: "3 unread" in a navigation, the count of the bell.
 *
 * Not a label (`Badge`: a state in words), not a state, and never a control: it is a `span` inside the link or the
 * button that it counts for.
 *
 * With `label`, the accessible text is the number and the words ("3 unread"), and it becomes part of the name of the
 * link around it. Without `label`, the badge is `aria-hidden`: the control around it must then hold the number in its
 * own name ("Notifications, 3 new"), or a screen reader never hears it.
 */
export function CountBadge({ count, max, label, placement = 'inline' }: CountBadgeProps) {
  if (count <= 0) {
    return null;
  }

  const text = max !== undefined && count > max ? `${max}+` : String(count);
  const classes = placementClasses[placement];

  if (label === undefined) {
    return (
      <span aria-hidden="true" className={classes}>
        {text}
      </span>
    );
  }

  return (
    <span className={classes}>
      {text} <span className="sr-only">{label}</span>
    </span>
  );
}
