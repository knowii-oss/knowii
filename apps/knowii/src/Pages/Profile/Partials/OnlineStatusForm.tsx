import { router } from '@inertiajs/react';
import { knowiiApiClient, leaveAllPresenceChannels } from '@knowii/common';
import ActionSection from '@/Components/ActionSection';
import { SaveStatus, Switch, useInstantSave } from '@/Components/ui';

interface Props {
  showOnlineStatus: boolean;
}

/**
 * Show or hide when the member is online (#1187, ADR 0027). A switch, saved at once, with its status (#1569). Hidden,
 * the member never appears online and sends no typing indicator, and still sees who is online.
 */
export default function OnlineStatusForm({ showOnlineStatus }: Props) {
  const setting = useInstantSave(showOnlineStatus);

  const change = async (show: boolean) => {
    const result = await setting.save(show, () => knowiiApiClient.users.updateOnlineStatus(show));
    if (!result?.ok) {
      return;
    }
    if (!show) {
      // Gone from the online lists now, not when the next page loads
      leaveAllPresenceChannels();
    }
    router.reload({ only: ['auth'] });
  };

  return (
    <ActionSection title="Online status" description="Who can see that you are online in your communities.">
      <Switch
        checked={setting.value}
        busy={setting.busy}
        onChange={(show) => void change(show)}
        label="Show the other members when I am online"
        description="When this is off, you never appear online and the others do not see when you type. You still see who is online."
        status={<SaveStatus state={setting.saveState} />}
      />
    </ActionSection>
  );
}
