import { Head } from '@inertiajs/react';
import { tabTitle } from '@knowii/common';
import { useNotificationsOptional } from './NotificationsProvider';

/**
 * The title of the browser tab (#1476, design 5.4): "(3) <title of the page>", with the bell number plus the number of
 * "Messages", "(9+)" above 9, and no number at zero. It follows the count of the provider (the server value). No animation,
 * no flashing, no change of the icon. A visitor has no provider and so no number.
 */
export function TabTitle({ title }: { title: string }) {
  const notifications = useNotificationsOptional();
  return <Head title={notifications ? tabTitle(title, notifications.count, notifications.messages) : title} />;
}
