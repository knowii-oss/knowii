import { useState } from 'react';
import { router } from '@inertiajs/react';
import { knowiiApiClient, leaveAllPresenceChannels, useAppData } from '@knowii/common';
import ActionSection from '@/Components/ActionSection';
import { Checkbox } from '@/Components/ui';

interface Props {
  showOnlineStatus: boolean;
}

/**
 * Show or hide when the member is online (#1187, ADR 0027). Saved at once. Hidden, the member never appears online and
 * sends no typing indicator, and still sees who is online.
 */
export default function OnlineStatusForm({ showOnlineStatus }: Props) {
  const { showToast } = useAppData();
  const [shown, setShown] = useState(showOnlineStatus);
  const [busy, setBusy] = useState(false);

  const change = async (show: boolean) => {
    setShown(show);
    setBusy(true);
    const result = await knowiiApiClient.users.updateOnlineStatus(show);
    setBusy(false);
    if (!result.ok) {
      setShown(!show);
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    if (!show) {
      // Gone from the online lists now, not when the next page loads
      leaveAllPresenceChannels();
    }
    showToast({ severity: 'success', summary: show ? 'The other members see when you are online' : 'You appear offline' });
    router.reload({ only: ['auth'] });
  };

  return (
    <ActionSection title="Online status" description="Who can see that you are online in your communities.">
      <label className="flex cursor-pointer items-start gap-3">
        <Checkbox
          name="show_online_status"
          checked={shown}
          disabled={busy}
          onChange={(event) => void change(event.target.checked)}
          aria-describedby="online-status-hint"
        />
        <span>
          <span className="font-bold">Show the other members when I am online</span>
          <span id="online-status-hint" className="block text-sm text-muted">
            When this is off, you never appear online and the others do not see when you type. You still see who is online.
          </span>
        </span>
      </label>
    </ActionSection>
  );
}
