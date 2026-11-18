import type { RefObject } from 'react';
import { useMemo } from 'react';

// A dialog holds its own lists: Headless UI renders them inside it, where its focus trap allows them
const DIALOGS = 'dialog, [role="dialog"], [role="alertdialog"]';

// The elements that are a landmark, or can be one (a header or a footer is a landmark only outside main)
const LANDMARKS = [
  'main',
  'header',
  'footer',
  'nav',
  'aside',
  'search',
  'section[aria-label]',
  'section[aria-labelledby]',
  'form[aria-label]',
  'form[aria-labelledby]',
  ...['main', 'banner', 'contentinfo', 'navigation', 'complementary', 'region', 'search', 'form'].map((role) => `[role="${role}"]`),
].join(', ');

const TARGET_ATTRIBUTE = 'data-landmark-portal';

/**
 * Where the open list of a Select or a DropdownMenu renders (#1651): inside the dialog of its button, else in the
 * outermost landmark around its button (main, the header of the page), else at the end of the page. An open list on
 * the body is outside every landmark (axe rule `region`).
 *
 * In a landmark, the list goes in an element of its own, positioned out of the flow, so that it changes nothing in the
 * layout of the landmark (a gap, a margin). Headless UI removes that element when the list closes (it is then empty),
 * and the next list creates it again.
 */
export function landmarkPortalTarget(anchor: Element | null): HTMLElement {
  const document = anchor?.ownerDocument ?? globalThis.document;
  const dialog = anchor?.closest<HTMLElement>(DIALOGS);
  if (dialog) {
    return dialog;
  }

  let landmark: HTMLElement | null = null;
  for (
    let found = anchor?.closest<HTMLElement>(LANDMARKS) ?? null;
    found !== null;
    found = found.parentElement?.closest<HTMLElement>(LANDMARKS) ?? null
  ) {
    landmark = found;
  }
  const container = landmark ?? document.body;

  const existing = Array.from(container.children).find((child) => child.hasAttribute(TARGET_ATTRIBUTE));
  if (existing instanceof HTMLElement) {
    return existing;
  }
  const target = document.createElement('div');
  target.setAttribute(TARGET_ATTRIBUTE, '');
  target.className = 'absolute top-0 left-0';
  container.append(target);
  return target;
}

/**
 * The target of a Headless UI `Portal.Group` for the list of the element in `anchor`: it is found when the list opens,
 * so that it follows the place of the element in the page (a dialog, a landmark).
 */
export function useLandmarkPortalTarget(anchor: RefObject<HTMLElement | null>): RefObject<HTMLElement | null> {
  return useMemo(
    () => ({
      get current() {
        return landmarkPortalTarget(anchor.current);
      },
    }),
    [anchor],
  );
}
