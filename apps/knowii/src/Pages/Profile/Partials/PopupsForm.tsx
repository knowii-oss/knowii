import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { knowiiApiClient } from '@knowii/common';
import { Checkbox } from '@/Components/ui';

export interface PopupsFormProps {
  // The switch of the account: on by default
  popups: boolean;
  // The status region of the page ("Saved")
  onStatus: (text: string) => void;
}

/**
 * The switch "Show pop-ups" (#1476, design 5.3.7): on by default, saved at once, "Saved" in the status region. On an error the
 * switch goes back and an alert says "Not saved. Retry.". A target of 44 px.
 */
export function PopupsForm({ popups, onStatus }: PopupsFormProps) {
  const [on, setOn] = useState(popups);
  const [failed, setFailed] = useState(false);
  const busyRef = useRef(false);

  const change = async (next: boolean) => {
    // A guard, not `disabled`: the switch keeps the focus
    if (busyRef.current) {
      return;
    }
    busyRef.current = true;
    setFailed(false);
    setOn(next);
    const result = await knowiiApiClient.users.setNotificationPopups(next);
    busyRef.current = false;
    if (!result.ok) {
      setOn(!next);
      setFailed(true);
      return;
    }
    onStatus('Saved');
    router.reload({ only: ['auth'] });
  };

  return (
    <div className="flex flex-col gap-2">
      <label htmlFor="notification-popups" className="flex min-h-11 cursor-pointer items-center gap-3">
        <Checkbox id="notification-popups" role="switch" checked={on} onChange={(event) => void change(event.target.checked)} />
        <span>
          <span className="block text-sm font-bold">Show pop-ups</span>
          <span className="block text-sm text-muted">A short message when something new needs you. Nothing else pops up.</span>
        </span>
      </label>
      {failed && (
        <p role="alert" className="text-sm text-danger-text">
          Not saved. Retry.
        </p>
      )}
    </div>
  );
}
