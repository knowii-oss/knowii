import { router } from '@inertiajs/react';
import { knowiiApiClient } from '@knowii/common';
import { Switch, useInstantSave } from '@/Components/ui';

export interface PopupsFormProps {
  // The switch of the account: on by default
  popups: boolean;
  // The status region of the page ("Saved")
  onStatus: (text: string) => void;
}

/**
 * The switch "Show pop-ups" (#1476, design 5.3.7): on by default, saved at once, "Saved" in the status region of the
 * page, like the other settings of the page. On an error the switch goes back and an alert next to it says "Not saved.
 * Retry.". A real switch (#1569), busy while it saves, with a target of 44 px on a phone.
 */
export function PopupsForm({ popups, onStatus }: PopupsFormProps) {
  const setting = useInstantSave(popups);

  const change = async (next: boolean) => {
    const result = await setting.save(next, () => knowiiApiClient.users.setNotificationPopups(next));
    if (!result?.ok) {
      return;
    }
    onStatus('Saved');
    router.reload({ only: ['auth'] });
  };

  return (
    <div className="flex flex-col gap-2">
      <Switch
        checked={setting.value}
        busy={setting.busy}
        onChange={(next) => void change(next)}
        label={<span className="text-sm">Show pop-ups</span>}
        description="A short message when something new needs you. Nothing else pops up."
      />
      {setting.saveState.status === 'error' && (
        <p role="alert" className="text-sm text-danger-text">
          Not saved. Retry.
        </p>
      )}
    </div>
  );
}
