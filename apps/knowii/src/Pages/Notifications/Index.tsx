import { useCallback } from 'react';
import type { Community } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { NotificationsPanel } from '@/Components/Notifications/NotificationsPanel';

interface Props {
  // WARNING: aligned with NotificationsPageController.php
  communities: Community[];
}

/**
 * The page "Notifications" (#1476, design 5.1 and 5.5): the heading "Notifications", then the "Catch up" card of each
 * community that has lines, "Needs you", "Following" and "Earlier". It reuses the lists of the panel of the bell: the same
 * lines, menus, buttons, focus rules and status text. For a signed-in member: a visitor goes to the sign-in page.
 */
export default function NotificationsPage({ communities }: Props) {
  // The main heading is the one of the layout: the focus goes there when a list is empty
  const focusHeading = useCallback(() => {
    const heading = document.querySelector<HTMLElement>('h1');
    if (heading) {
      heading.tabIndex = -1;
      heading.focus();
    }
  }, []);

  // Opening a line leaves the page: nothing to close here
  const leave = useCallback(() => undefined, []);

  return (
    <AppLayout contentWidth="form" browserPageTitle="Notifications" pageTitle="Notifications">
      <NotificationsPanel
        variant="page"
        onClose={leave}
        focusHeading={focusHeading}
        catchUpCommunities={communities.map((community) => ({ cuid: community.cuid, slug: community.slug, name: community.name }))}
      />
    </AppLayout>
  );
}
