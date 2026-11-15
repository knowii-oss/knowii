import { useEffect, useRef, useState } from 'react';
import type { Community } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import ActionSection from '@/Components/ActionSection';
import Separator from '@/Components/Separator';
import type { BreadcrumbItem } from '@/Components/ui';
import { SpaceLevelsForm } from '@/Pages/Profile/Partials/SpaceLevelsForm';
import { FaBell } from 'react-icons/fa';

interface Props {
  // WARNING: aligned with NotificationSettingsController.php
  communities: Community[];
}

// "Saved" and "Applied to 6 spaces" stay a few seconds: a second "Saved" is then a change of the status text again
const STATUS_MS = 4000;

/**
 * The notification settings of the member (#1497, design 5.9, 9.6): "Your spaces", the level of each space of each of their
 * communities. One status region says "Saved" after a change.
 */
export default function NotificationSettings({ communities }: Props) {
  const [status, setStatus] = useState('');
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(
    () => () => {
      if (timer.current) {
        clearTimeout(timer.current);
      }
    },
    [],
  );

  const announce = (text: string) => {
    if (timer.current) {
      clearTimeout(timer.current);
    }
    setStatus(text);
    timer.current = setTimeout(() => {
      timer.current = null;
      setStatus('');
    }, STATUS_MS);
  };

  const breadcrumbItems: BreadcrumbItem[] = [{ label: 'Notifications', icon: <FaBell /> }];

  return (
    <AppLayout contentWidth="form" browserPageTitle="Notifications" breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <h1 className="sr-only">Notification settings</h1>
      <div
        role="status"
        aria-live="polite"
        className={
          status ? 'sticky top-2 z-10 mb-4 rounded-lg border border-border bg-surface-elevated px-3 py-2 text-sm font-bold' : 'sr-only'
        }
      >
        {status}
      </div>

      {communities.length === 0 ? (
        <p>You do not belong to a community yet.</p>
      ) : (
        communities.map((community, index) => (
          <div key={community.cuid}>
            {index > 0 && <Separator />}
            <ActionSection
              title={communities.length > 1 ? `Your spaces in ${community.name}` : 'Your spaces'}
              description="How close you want to be to each space. Your choice always wins over the default of the space."
            >
              <SpaceLevelsForm communityCuid={community.cuid} onStatus={announce} />
            </ActionSection>
          </div>
        ))
      )}
    </AppLayout>
  );
}
