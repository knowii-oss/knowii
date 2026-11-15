import { useEffect, useMemo, useRef, useState } from 'react';
import type { CommunitySpaceLevel, SpaceNotificationLevel } from '@knowii/common';
import {
  knowiiApiClient,
  SPACE_NOTIFICATION_LEVEL_LABELS,
  SPACE_NOTIFICATION_LEVELS_ORDER,
  spaceNotificationLevelsFor,
} from '@knowii/common';
import { Select } from '@/Components/ui';

export interface SpaceLevelsFormProps {
  communityCuid: string;
  // The status region of the page ("Saved", "Applied to 6 spaces")
  onStatus: (text: string) => void;
}

interface SpaceGroupLevels {
  cuid: string;
  name: string;
  spaces: CommunitySpaceLevel[];
}

const optionsFor = (spaceType: string) =>
  spaceNotificationLevelsFor(spaceType).map((level) => ({ value: level, label: SPACE_NOTIFICATION_LEVEL_LABELS[level].name }));

// The groups of the list, in the order of the navigation (the server sends the spaces in that order)
function groupsOf(levels: CommunitySpaceLevel[]): SpaceGroupLevels[] {
  const groups: SpaceGroupLevels[] = [];
  for (const level of levels) {
    const group = groups.find((item) => item.cuid === level.space_group_cuid);
    if (group) {
      group.spaces.push(level);
    } else {
      groups.push({ cuid: level.space_group_cuid, name: level.space_group_name, spaces: [level] });
    }
  }
  return groups;
}

/**
 * "Your spaces" for one community (#1497, design 5.9.6, 9.6.2, 9.6.4, 9.6.5): one fieldset with a legend per space group, one
 * choice per space labelled "Notifications for <space name>", and "Same for all spaces of this group". One column, the label
 * above the control, controls of 44 px. A change is saved at once and the page says "Saved". On an error the old value stays,
 * an alert next to the control says "Not saved. Retry." and the focus stays on the control.
 */
export function SpaceLevelsForm({ communityCuid, onStatus }: SpaceLevelsFormProps) {
  const [levels, setLevels] = useState<CommunitySpaceLevel[] | null>(null);
  const [loadFailed, setLoadFailed] = useState(false);
  // The controls that did not save: a space cuid, or `group:<cuid>`
  const [failed, setFailed] = useState<ReadonlySet<string>>(new Set());
  const [busy, setBusy] = useState<ReadonlySet<string>>(new Set());
  const busyRef = useRef(new Set<string>());
  const form = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let active = true;
    void knowiiApiClient.notificationLevels.forCommunity(communityCuid).then((result) => {
      if (!active) {
        return;
      }
      if (result.ok) {
        setLevels(result.data);
      } else {
        setLoadFailed(true);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid]);

  // "Notification settings for <space>" links here with #notifications-space-<cuid>: the focus goes to that choice
  const linked = levels !== null;
  useEffect(() => {
    if (!linked) {
      return;
    }
    const id = window.location.hash.slice(1);
    if (id.startsWith('notifications-space-')) {
      form.current?.querySelector<HTMLElement>(`[id="${id}"]`)?.focus();
    }
  }, [linked]);

  const groups = useMemo(() => groupsOf(levels ?? []), [levels]);

  const mark = (key: string, set: 'busy' | 'failed', on: boolean) => {
    const update = (current: ReadonlySet<string>) => {
      const next = new Set(current);
      if (on) {
        next.add(key);
      } else {
        next.delete(key);
      }
      return next;
    };
    if (set === 'busy') {
      if (on) {
        busyRef.current.add(key);
      } else {
        busyRef.current.delete(key);
      }
      setBusy(update);
    } else {
      setFailed(update);
    }
  };

  const choose = async (space: CommunitySpaceLevel, level: SpaceNotificationLevel) => {
    if (busyRef.current.has(space.space_cuid)) {
      return;
    }
    mark(space.space_cuid, 'busy', true);
    mark(space.space_cuid, 'failed', false);
    const result = await knowiiApiClient.notificationLevels.set(communityCuid, space.space_cuid, level);
    mark(space.space_cuid, 'busy', false);
    if (!result.ok) {
      mark(space.space_cuid, 'failed', true);
      return;
    }
    setLevels((current) =>
      (current ?? []).map((item) =>
        item.space_cuid === space.space_cuid ? { ...item, level: result.data.level, chosen: result.data.chosen } : item,
      ),
    );
    onStatus('Saved');
  };

  // "Same for all spaces of this group": the spaces that can take the level get it, one request each
  const chooseForGroup = async (group: SpaceGroupLevels, level: SpaceNotificationLevel) => {
    const key = `group:${group.cuid}`;
    if (busyRef.current.has(key)) {
      return;
    }
    const targets = group.spaces.filter((space) => spaceNotificationLevelsFor(space.space_type).includes(level));
    mark(key, 'busy', true);
    mark(key, 'failed', false);
    const results = await Promise.all(
      targets.map(async (space) => ({
        space,
        result: await knowiiApiClient.notificationLevels.set(communityCuid, space.space_cuid, level),
      })),
    );
    mark(key, 'busy', false);
    const done = results.filter((item) => item.result.ok).map((item) => item.space.space_cuid);
    if (done.length > 0) {
      setLevels((current) => (current ?? []).map((item) => (done.includes(item.space_cuid) ? { ...item, level, chosen: true } : item)));
    }
    if (done.length < targets.length) {
      mark(key, 'failed', true);
      return;
    }
    const others = group.spaces.length - targets.length;
    onStatus(
      `Applied to ${targets.length} ${targets.length === 1 ? 'space' : 'spaces'}.` +
        (others > 0
          ? ` Everything exists only for discussion and chat spaces, so ${others} ${others === 1 ? 'space kept its' : 'spaces kept their'} choice.`
          : ''),
    );
  };

  if (loadFailed) {
    return <p role="alert">We could not load your spaces. Reload the page to try again.</p>;
  }
  if (levels === null) {
    return (
      <p aria-busy="true" className="text-sm text-muted">
        Loading your spaces.
      </p>
    );
  }
  if (groups.length === 0) {
    return <p className="text-sm">This community has no space with notifications yet.</p>;
  }

  const groupOptions = SPACE_NOTIFICATION_LEVELS_ORDER.map((level) => ({
    value: level,
    label: SPACE_NOTIFICATION_LEVEL_LABELS[level].name,
  }));

  return (
    <div ref={form} className="flex flex-col gap-6">
      {groups.map((group) => {
        const groupKey = `group:${group.cuid}`;
        return (
          <fieldset key={group.cuid} className="flex min-w-0 flex-col gap-4 rounded-lg border border-border p-4">
            <legend className="px-1 text-base font-extrabold">{group.name}</legend>
            {group.spaces.map((space) => {
              const id = `notifications-space-${space.space_cuid}`;
              return (
                <div key={space.space_cuid} className="flex flex-col gap-1">
                  <label htmlFor={id} className="text-sm font-bold">
                    Notifications for {space.space_name}
                  </label>
                  <Select<SpaceNotificationLevel>
                    id={id}
                    options={optionsFor(space.space_type)}
                    value={space.level}
                    onChange={(level) => void choose(space, level)}
                  />
                  {busy.has(space.space_cuid) && <span className="sr-only">Saving</span>}
                  {failed.has(space.space_cuid) && (
                    <p role="alert" className="text-sm text-danger-text">
                      Not saved. Retry.
                    </p>
                  )}
                </div>
              );
            })}
            <div className="flex flex-col gap-1 border-t border-border pt-4">
              <label htmlFor={`notifications-group-${group.cuid}`} className="text-sm font-bold">
                Same for all spaces of this group
              </label>
              <Select<SpaceNotificationLevel>
                id={`notifications-group-${group.cuid}`}
                options={groupOptions}
                value={null}
                placeholder="Choose a level"
                onChange={(level) => void chooseForGroup(group, level)}
              />
              {failed.has(groupKey) && (
                <p role="alert" className="text-sm text-danger-text">
                  Not saved. Retry.
                </p>
              )}
            </div>
          </fieldset>
        );
      })}
    </div>
  );
}
