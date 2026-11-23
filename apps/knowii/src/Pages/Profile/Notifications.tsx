import { useEffect, useRef, useState } from 'react';
import type { Community } from '@knowii/common';
import { useTypedPage } from '@knowii/common';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import ActionSection from '@/Components/ActionSection';
import { EmailStatusBox } from '@/Components/Notifications/EmailStatusBox';
import type { BreadcrumbItem } from '@/Components/ui';
import { Divider } from '@/Components/ui';
import { PopupsForm } from '@/Pages/Profile/Partials/PopupsForm';
import { SpaceLevelsForm } from '@/Pages/Profile/Partials/SpaceLevelsForm';
import { TimeZoneForm } from '@/Pages/Profile/Partials/TimeZoneForm';
import { FaBell } from 'react-icons/fa';

interface Props {
  // WARNING: aligned with NotificationSettingsController.php
  communities: Community[];
}

// "Saved" and "Applied to 6 spaces" stay a few seconds: a second "Saved" is then a change of the status text again
const STATUS_MS = 4000;

/**
 * The notification settings of the member (#1497, design 5.9, 9.6): "Your time zone" and "Pop-ups" (#1476), then "Your spaces",
 * the level of each space of each of their communities. One status region says "Saved" after a change.
 */
export default function NotificationSettings({ communities }: Props) {
  const user = useTypedPage().props.auth?.user ?? null;
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

      {/* The notification emails of the address stopped after a report of the mail provider (#1478) */}
      <EmailStatusBox />

      <ActionSection
        title="Your time zone"
        description="The zone in which Knowii shows the date and the hour of what happens. Only you can see it and change it."
      >
        <TimeZoneForm timeZone={user?.time_zone ?? null} onStatus={announce} />
      </ActionSection>
      <Divider className="my-10" />
      <ActionSection title="Pop-ups" description="When something new needs you, a short message can appear at the top of the page.">
        <PopupsForm popups={user?.notification_popups !== false} onStatus={announce} />
      </ActionSection>
      <Divider className="my-10" />

      {communities.length === 0 ? (
        <p>You do not belong to a community yet.</p>
      ) : (
        communities.map((community, index) => (
          <div key={community.cuid}>
            {index > 0 && <Divider className="my-10" />}
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
