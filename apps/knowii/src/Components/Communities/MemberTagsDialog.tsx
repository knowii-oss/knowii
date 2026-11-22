import { useEffect, useRef, useState } from 'react';
import type { CommunityMember, MemberTag } from '@knowii/common';
import { knowiiApiClient, sortMemberTags } from '@knowii/common';
import { FaLock } from 'react-icons/fa6';
import type { SaveState } from '@/Components/ui';
import { Badge, Checkbox, Dialog, DialogCloseButton, InputError, SaveStatus, Skeleton } from '@/Components/ui';

export interface MemberTagsDialogProps {
  communityCuid: string;
  /**
   * The member whose tags the dialog shows; null closes it
   */
  member: CommunityMember | null;
  onClose: () => void;
  /**
   * After each saved change: the new tags of the member, by name
   */
  onChange: (memberCuid: string, tags: MemberTag[]) => void;
}

/**
 * The tags of one member (#1389, ADR 0060), from the member list of the settings: one check box per tag of the
 * community. Each change saves at once; "Saved" says it, and an error puts the box back and says why. The focus goes
 * back to the button that opened the dialog (Dialog).
 */
export function MemberTagsDialog({ communityCuid, member, onClose, onChange }: MemberTagsDialogProps) {
  const [allTags, setAllTags] = useState<MemberTag[] | null>(null);
  const [selected, setSelected] = useState<MemberTag[]>([]);
  const [saveState, setSaveState] = useState<SaveState>({ status: 'idle' });
  const [error, setError] = useState('');
  // The guard of a second change while one is saved: the state of the render can be late
  const busy = useRef(false);
  const [busyCuid, setBusyCuid] = useState<string | null>(null);
  const memberCuid = member?.cuid ?? null;

  // Another member opens the dialog: start from their tags. The boxes then follow the saves of this dialog
  const [shownFor, setShownFor] = useState<string | null>(null);
  if (memberCuid !== shownFor) {
    setShownFor(memberCuid);
    setSelected(member?.tags ?? []);
    setAllTags(null);
    setSaveState({ status: 'idle' });
    setError('');
  }

  // The tags of the community load each time the dialog opens: a tag may be new or gone
  useEffect(() => {
    if (memberCuid === null) {
      return;
    }
    let active = true;
    void knowiiApiClient.communities.memberTags.list(communityCuid).then((result) => {
      if (!active) {
        return;
      }
      if (!result.ok) {
        setAllTags([]);
        setError(result.message);
        return;
      }
      setAllTags(result.data);
    });
    return () => {
      active = false;
    };
  }, [communityCuid, memberCuid]);

  const toggle = async (tag: MemberTag, checked: boolean) => {
    if (!member || busy.current) {
      return;
    }
    busy.current = true;
    setBusyCuid(tag.cuid);
    const previous = selected;
    const next = checked
      ? sortMemberTags([...selected, { ...tag, members_count: null }])
      : selected.filter((item) => item.cuid !== tag.cuid);
    setSelected(next);
    setError('');
    setSaveState({ status: 'saving' });
    const result = checked
      ? await knowiiApiClient.communities.memberTags.members.add(communityCuid, tag.cuid, { member: member.cuid })
      : await knowiiApiClient.communities.memberTags.members.remove(communityCuid, tag.cuid, member.cuid);
    busy.current = false;
    setBusyCuid(null);
    if (!result.ok) {
      setSelected(previous);
      setSaveState({ status: 'idle' });
      setError(result.message);
      return;
    }
    setSaveState({ status: 'saved' });
    onChange(member.cuid, next);
  };

  const isSelected = (tag: MemberTag) => selected.some((item) => item.cuid === tag.cuid);

  return (
    <Dialog
      open={member !== null}
      onClose={onClose}
      title={`Tags of ${member?.name ?? ''}`}
      description="Each change is saved at once. The member is not told."
      footer={<DialogCloseButton>Done</DialogCloseButton>}
    >
      {allTags === null ? (
        <Skeleton label="Loading the tags" rows={3} lines={1} />
      ) : allTags.length === 0 ? (
        error === '' && (
          <p className="text-sm text-muted">
            This community has no member tags yet. The owner and the admins create them in the section &quot;Member tags&quot; of the
            settings.
          </p>
        )
      ) : (
        <fieldset>
          <legend className="sr-only">Tags of {member?.name ?? ''}</legend>
          <ul className="flex flex-col gap-2">
            {allTags.map((tag) => {
              const id = `member-tag-${tag.cuid}`;
              const forManagers = tag.visibility === 'managers';
              return (
                <li key={tag.cuid}>
                  <label htmlFor={id} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-1 hover:bg-overlay/5">
                    <Checkbox
                      id={id}
                      checked={isSelected(tag)}
                      aria-busy={busyCuid === tag.cuid || undefined}
                      onChange={(event) => void toggle(tag, event.target.checked)}
                    />
                    <Badge color={tag.color} icon={forManagers ? <FaLock /> : undefined}>
                      {tag.name}
                    </Badge>
                    <span className="text-sm text-muted">
                      {/* The name of the box reads "Onboarded, Managers only" */}
                      <span className="sr-only">, </span>
                      {forManagers ? 'Managers only' : 'Members see it'}
                    </span>
                  </label>
                </li>
              );
            })}
          </ul>
        </fieldset>
      )}
      <SaveStatus state={saveState} />
      <InputError message={error} />
    </Dialog>
  );
}
