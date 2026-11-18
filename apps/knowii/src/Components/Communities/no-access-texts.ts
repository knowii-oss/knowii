import type { NoAccessKind } from '@knowii/common';

/**
 * The texts of the page without access (#1561): a signed-in person who is not a member opens a page of a community.
 */

export const NO_ACCESS_TITLE = 'You do not have access to this page';

/**
 * The reason, when the person may see the public page of the community
 */
export function noAccessReason(kind: NoAccessKind, communityName: string): string {
  return kind === 'space' ? `This space is for the members of ${communityName}.` : `This page is for the members of ${communityName}.`;
}

/**
 * The reason, when the page may name nothing of the community
 */
export const NO_ACCESS_GENERAL_REASON =
  'This page is for the members of a community. If a member gave you this link, ask that member for an invitation.';

/**
 * The next step in a community by invitation
 */
export const NO_ACCESS_BY_INVITATION = 'This community is by invitation. Ask a member for an invitation.';

/**
 * The link to the public page of the community
 */
export const NO_ACCESS_PUBLIC_PAGE_LINK = 'Go to the community page';

/**
 * The link to the dashboard, where the person sees their own communities
 */
export const NO_ACCESS_HOME_LINK = 'Go to your communities';
