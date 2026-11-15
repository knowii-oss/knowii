import { useEffect, useId, useRef, useState } from 'react';
import { FaChevronDown, FaChevronRight } from 'react-icons/fa6';
import type { SpaceLevel, SpaceNotificationLevel } from '@knowii/common';
import { knowiiApiClient, SPACE_NOTIFICATION_LEVEL_LABELS, spaceNotificationLevelsFor } from '@knowii/common';
import { Radio } from '@/Components/ui';

export interface SpaceLevelControlProps {
  communityCuid: string;
  spaceCuid: string;
  spaceType: string;
}

/**
 * The level of a space for the member (#1497, design 2.2, 9.6): Everything, Only what is for me, Muted, each with its
 * sentence. A chat space offers Everything too (the member chooses it, it is never the default). A link space has no level
 * and shows nothing. For a member of the community only: the caller does not render it for a visitor or an outsider.
 *
 * It opens from a button that names the current level. A choice is saved at once: the page says "Saved" in a status
 * region; on an error the old choice stays and an alert says "Not saved. Retry." with the focus on the choice.
 */
export function SpaceLevelControl({ communityCuid, spaceCuid, spaceType }: SpaceLevelControlProps) {
  const [current, setCurrent] = useState<SpaceLevel | null>(null);
  const [open, setOpen] = useState(false);
  const [saved, setSaved] = useState(false);
  const [failed, setFailed] = useState(false);
  const busyRef = useRef(false);
  const [busy, setBusy] = useState(false);
  const id = useId();
  const levels = spaceNotificationLevelsFor(spaceType);

  useEffect(() => {
    let active = true;
    void knowiiApiClient.notificationLevels.get(communityCuid, spaceCuid).then((result) => {
      if (active && result.ok) {
        setCurrent(result.data);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, spaceCuid]);

  if (levels.length === 0 || current === null) {
    return null;
  }

  const choose = async (level: SpaceNotificationLevel) => {
    if (busyRef.current || level === current.level) {
      return;
    }
    busyRef.current = true;
    setBusy(true);
    setFailed(false);
    setSaved(false);
    const result = await knowiiApiClient.notificationLevels.set(communityCuid, spaceCuid, level);
    busyRef.current = false;
    setBusy(false);
    if (result.ok) {
      setCurrent(result.data);
      setSaved(true);
    } else {
      setFailed(true);
    }
  };

  const Chevron = open ? FaChevronDown : FaChevronRight;

  return (
    <div className="flex flex-col gap-2">
      <button
        type="button"
        aria-expanded={open}
        aria-controls={`${id}-levels`}
        onClick={() => setOpen((value) => !value)}
        className="flex min-h-8 w-fit cursor-pointer items-center gap-2 rounded text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      >
        <Chevron aria-hidden="true" className="size-3" />
        Notifications: {SPACE_NOTIFICATION_LEVEL_LABELS[current.level].name}
      </button>
      <div id={`${id}-levels`} hidden={!open}>
        {open && (
          <fieldset aria-busy={busy || undefined} className="flex flex-col gap-1 rounded-lg border border-border p-3">
            <legend className="px-1 text-sm font-bold">Notifications for this space</legend>
            {levels.map((level) => (
              <label key={level} className="flex min-h-11 cursor-pointer items-start gap-3 py-1">
                <Radio
                  name={`${id}-level`}
                  value={level}
                  checked={current.level === level}
                  aria-describedby={`${id}-${level}`}
                  aria-disabled={busy || undefined}
                  onChange={() => void choose(level)}
                />
                <span>
                  <span className="block text-sm font-bold">{SPACE_NOTIFICATION_LEVEL_LABELS[level].name}</span>
                  <span id={`${id}-${level}`} className="block text-sm text-muted">
                    {SPACE_NOTIFICATION_LEVEL_LABELS[level].description}
                  </span>
                </span>
              </label>
            ))}
          </fieldset>
        )}
      </div>
      <p role="status" aria-live="polite" className={saved ? 'text-sm' : 'sr-only'}>
        {saved ? 'Saved' : ''}
      </p>
      {failed && (
        <p role="alert" className="text-sm text-danger-text">
          Not saved. Retry.
        </p>
      )}
    </div>
  );
}
