import classNames from 'classnames';
import type { ArgumentArray } from 'classnames';
import { extendTailwindMerge } from 'tailwind-merge';

const twMerge = extendTailwindMerge({
  extend: {
    theme: {
      // Custom shadow token defined in styles.css
      shadow: ['glow'],
    },
  },
});

/**
 * Join class names and resolve Tailwind conflicts: the last class wins (`cn('px-4', 'px-2')` gives `px-2`).
 * Lets callers override the default classes of a component through `className`.
 */
export function cn(...inputs: ArgumentArray): string {
  return twMerge(classNames(inputs));
}
