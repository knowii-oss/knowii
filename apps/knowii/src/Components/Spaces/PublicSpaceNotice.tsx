import type { Space } from '@knowii/common';

/**
 * What the platform tells a member about a public space (#1387, ADR 0044). The form of the notice is not final (the
 * owner confirms it): the texts and the rules are all here, so that changing it touches this file only.
 */

/**
 * Whether anyone on the internet can read the space, without an account. A chat space never: it stays for signed-in
 * people, also when its visibility is "public".
 */
export function isReadableByVisitors(space: Pick<Space, 'visibility' | 'type'>): boolean {
  return space.visibility === 'public' && space.type !== 'chat';
}

/**
 * The "Public" label next to the name of a space that anyone can read
 */
export function PublicLabel({ space }: { space: Pick<Space, 'visibility' | 'type'> }) {
  if (!isReadableByVisitors(space)) {
    return null;
  }
  return (
    <span
      title="Anyone on the internet can read this space, without an account"
      className="rounded-full border border-border px-2 py-0.5 align-middle text-xs font-normal text-muted"
    >
      Public
    </span>
  );
}

/**
 * The label of the submit button of a composer: it says "publicly" in a space that anyone can read
 */
export function publicSubmitLabel(space: Pick<Space, 'visibility' | 'type'> | undefined, kind: 'post' | 'reply', fallback: string): string {
  if (!space || !isReadableByVisitors(space)) {
    return fallback;
  }
  return kind === 'post' ? 'Post publicly' : 'Reply publicly';
}

/**
 * Shown in the space form when a manager chooses "public"
 */
export const PUBLIC_SPACE_FORM_WARNING =
  'Anyone on the internet can read this space, without an account, with the names and pictures of the authors.';

/**
 * Shown in the space form when a manager chooses "public" for a chat space
 */
export const PUBLIC_CHAT_SPACE_FORM_NOTE = 'A chat space stays for signed-in people.';
