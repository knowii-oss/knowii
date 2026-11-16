import { useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { browserTimeZone, knowiiApiClient, knownTimeZones, timeZoneInWords } from '@knowii/common';
import { Select } from '@/Components/ui';

export interface TimeZoneFormProps {
  // The zone of the account, null when it is not known
  timeZone: string | null;
  // The status region of the page ("Saved")
  onStatus: (text: string) => void;
}

/**
 * "Your time zone" (#1476, ADR 0048, design 6.7 and 9.9): the zone of the account in words, and a choice of every zone that
 * the browser knows (with UTC). A change is saved at once and the page says "Saved". On an error the old value stays, an alert
 * says "Not saved. Retry." and the focus stays on the control. With no zone: "Set your time zone", and the zone that applies
 * until then (the one of the browser). Only the member sets it.
 */
export function TimeZoneForm({ timeZone, onStatus }: TimeZoneFormProps) {
  const [zone, setZone] = useState<string | null>(timeZone);
  const [failed, setFailed] = useState(false);
  const [busy, setBusy] = useState(false);
  const busyRef = useRef(false);
  const options = useMemo(() => knownTimeZones([zone]).map((name) => ({ value: name, label: name })), [zone]);
  const applying = browserTimeZone() ?? 'UTC';

  const choose = async (next: string) => {
    if (busyRef.current || next === zone) {
      return;
    }
    busyRef.current = true;
    setBusy(true);
    setFailed(false);
    const result = await knowiiApiClient.users.setTimeZone(next);
    busyRef.current = false;
    setBusy(false);
    if (!result.ok) {
      setFailed(true);
      return;
    }
    setZone(next);
    onStatus('Saved');
    // The times of the page show in the new zone
    router.reload({ only: ['auth'] });
  };

  return (
    <div aria-busy={busy || undefined} className="flex flex-col gap-3">
      {zone ? (
        <p id="time-zone-current">Times show in {timeZoneInWords(zone)}.</p>
      ) : (
        <p id="time-zone-current">
          <strong>Set your time zone.</strong> Until you do, times show in {timeZoneInWords(applying)}.
        </p>
      )}
      <div className="flex flex-col gap-1">
        <label htmlFor="time-zone-select" className="text-sm font-bold">
          Time zone
        </label>
        <Select<string>
          id="time-zone-select"
          options={options}
          value={zone}
          placeholder="Choose a time zone"
          onChange={(next) => void choose(next)}
        />
        {busy && <span className="sr-only">Saving</span>}
        {failed && (
          <p role="alert" className="text-sm text-danger-text">
            Not saved. Retry.
          </p>
        )}
      </div>
    </div>
  );
}
