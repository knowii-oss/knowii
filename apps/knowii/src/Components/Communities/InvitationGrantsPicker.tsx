import { useId, useState } from 'react';
import type { InvitationAccessGroup, MemberTag } from '@knowii/common';
import { MAX_MEMBER_TAGS_PER_MEMBER } from '@knowii/common';
import { FaLock, FaTags } from 'react-icons/fa6';
import { Badge, Button, Checkbox, Dialog, DialogCloseButton } from '@/Components/ui';
import { INVITATION_WORDS } from './invitation-words';

/**
 * The cuids that the picker holds: what the person gets at the join
 */
export interface InvitationGrants {
  accessGroups: string[];
  memberTags: string[];
}

export const NO_INVITATION_GRANTS: InvitationGrants = { accessGroups: [], memberTags: [] };

export interface InvitationGrantsPickerProps {
  /**
   * The access groups of the community
   */
  accessGroups: readonly InvitationAccessGroup[];
  /**
   * The member tags of the community, of both visibilities
   */
  memberTags: readonly MemberTag[];
  /**
   * The reader may give access groups (`canManageAccessGroupMembers`): the fieldset "Access groups" shows
   */
  canGiveAccessGroups: boolean;
  /**
   * The reader may give member tags (`canAssignMemberTags`): the fieldset "Member tags" shows
   */
  canGiveMemberTags: boolean;
  value: InvitationGrants;
  onChange: (value: InvitationGrants) => void;
  /**
   * Why a group cannot be chosen, or null when it can (an invite link refuses a group that a rule feeds, #1391). The
   * box of such a group is not in the choice, and the reason shows next to it.
   */
  accessGroupRefusal?: ((group: InvitationAccessGroup) => string | null) | undefined;
  className?: string | undefined;
}

const byName = <T extends { name: string; cuid: string }>(items: readonly T[]): T[] =>
  [...items].sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()) || a.cuid.localeCompare(b.cuid));

/**
 * The access groups and the member tags that a person gets when they join through an invitation (#1390), and
 * later through an invite link (#1391): a secondary button with the count, and a dialog with one check box per group
 * and per tag, by name. A tag shows its color, and a tag of the managers the hidden words "managers only", as
 * `MemberTagList`. An invitation holds at most 20 tags: the 21st cannot be checked, and a polite status says why.
 * "Done" closes the dialog, and the focus goes back to the button (Dialog).
 *
 * Nothing is shown when the community has no group and no tag that the reader may give.
 */
export function InvitationGrantsPicker({
  accessGroups,
  memberTags,
  canGiveAccessGroups,
  canGiveMemberTags,
  value,
  onChange,
  accessGroupRefusal,
  className,
}: InvitationGrantsPickerProps) {
  const [open, setOpen] = useState(false);
  const id = useId();
  const groups = canGiveAccessGroups ? byName(accessGroups) : [];
  const tags = canGiveMemberTags ? byName(memberTags) : [];

  if (groups.length === 0 && tags.length === 0) {
    return null;
  }

  // Only what the reader may give and what still exists counts
  const chosenGroups = value.accessGroups.filter((cuid) => groups.some((group) => group.cuid === cuid));
  const chosenTags = value.memberTags.filter((cuid) => tags.some((tag) => tag.cuid === cuid));
  const count = chosenGroups.length + chosenTags.length;
  const atTagLimit = chosenTags.length >= MAX_MEMBER_TAGS_PER_MEMBER;

  const toggleGroup = (cuid: string, checked: boolean) => {
    onChange({
      accessGroups: checked ? [...chosenGroups, cuid] : chosenGroups.filter((item) => item !== cuid),
      memberTags: chosenTags,
    });
  };

  const toggleTag = (cuid: string, checked: boolean) => {
    // The 21st tag: the box stays empty (aria-disabled keeps it in the tab order, so the status explains it)
    if (checked && atTagLimit) {
      return;
    }
    onChange({
      accessGroups: chosenGroups,
      memberTags: checked ? [...chosenTags, cuid] : chosenTags.filter((item) => item !== cuid),
    });
  };

  return (
    <div className={className}>
      <Button variant="secondary" aria-haspopup="dialog" leftIcon={<FaTags aria-hidden="true" />} onClick={() => setOpen(true)}>
        {count === 0 ? INVITATION_WORDS.pickerButton : INVITATION_WORDS.pickerButtonWithCount(count)}
      </Button>
      <Dialog
        open={open}
        onClose={() => setOpen(false)}
        title={INVITATION_WORDS.pickerTitle}
        description={INVITATION_WORDS.pickerDescription}
        footer={<DialogCloseButton variant="primary">{INVITATION_WORDS.pickerDone}</DialogCloseButton>}
      >
        <div className="flex flex-col gap-6">
          {groups.length > 0 && (
            <fieldset>
              <legend className="mb-2 font-bold">{INVITATION_WORDS.pickerAccessGroups}</legend>
              <ul className="flex flex-col gap-1">
                {groups.map((group) => {
                  const boxId = `${id}-group-${group.cuid}`;
                  const refusal = accessGroupRefusal?.(group) ?? null;
                  const refusalId = `${boxId}-refusal`;
                  return (
                    <li key={group.cuid}>
                      <label htmlFor={boxId} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-1 hover:bg-overlay/5">
                        <Checkbox
                          id={boxId}
                          checked={refusal === null && chosenGroups.includes(group.cuid)}
                          disabled={refusal !== null}
                          aria-describedby={refusal === null ? undefined : refusalId}
                          onChange={(event) => toggleGroup(group.cuid, event.target.checked)}
                        />
                        <span className="min-w-0 break-words">{group.name}</span>
                      </label>
                      {refusal !== null && (
                        <p id={refusalId} className="ml-10 text-sm text-muted">
                          {refusal}
                        </p>
                      )}
                    </li>
                  );
                })}
              </ul>
            </fieldset>
          )}
          {tags.length > 0 && (
            <fieldset>
              <legend className="mb-2 font-bold">{INVITATION_WORDS.pickerMemberTags}</legend>
              <ul className="flex flex-col gap-1">
                {tags.map((tag) => {
                  const boxId = `${id}-tag-${tag.cuid}`;
                  const forManagers = tag.visibility === 'managers';
                  const checked = chosenTags.includes(tag.cuid);
                  const blocked = !checked && atTagLimit;
                  return (
                    <li key={tag.cuid}>
                      <label htmlFor={boxId} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-1 hover:bg-overlay/5">
                        <Checkbox
                          id={boxId}
                          checked={checked}
                          aria-disabled={blocked || undefined}
                          aria-describedby={blocked ? `${id}-tag-limit` : undefined}
                          className={blocked ? 'opacity-60' : undefined}
                          onChange={(event) => toggleTag(tag.cuid, event.target.checked)}
                        />
                        <Badge color={tag.color} icon={forManagers ? <FaLock /> : undefined}>
                          {tag.name}
                          {forManagers && <span className="sr-only"> ({INVITATION_WORDS.managersOnly})</span>}
                        </Badge>
                      </label>
                    </li>
                  );
                })}
              </ul>
              <p id={`${id}-tag-limit`} role="status" aria-live="polite" className="mt-2 text-sm text-muted">
                {atTagLimit ? INVITATION_WORDS.tagLimit(MAX_MEMBER_TAGS_PER_MEMBER) : ''}
              </p>
            </fieldset>
          )}
        </div>
      </Dialog>
    </div>
  );
}
