import { useCallback, useEffect, useRef, useState } from 'react';
import type { AccessGroup, AccessGroupForm, AccessGroupMember, CommunityPermissions, Pagination, SpaceGroup } from '@knowii/common';
import {
  accessGroupFormSchema,
  COMMUNITY_MEMBERS_PAGE_SIZE,
  entitlementKeysError,
  knowiiApiClient,
  MAX_ENTITLEMENT_RULES_PER_GROUP,
  parseEntitlementKeys,
  useAppData,
} from '@knowii/common';
import { FaKey, FaLayerGroup, FaPen, FaTrash, FaUserLock, FaUserMinus, FaUserPlus, FaUsers } from 'react-icons/fa6';
import {
  Avatar,
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
  Dialog,
  EmptyState,
  InputError,
  InputLabel,
  LoadMoreButton,
  Textarea,
  TextInput,
} from '@/Components/ui';

export interface AccessGroupsManagerProps {
  communityCuid: string;
  permissions: Pick<CommunityPermissions, 'canManageAccessGroups' | 'canManageAccessGroupMembers'> &
    Partial<Pick<CommunityPermissions, 'canManageSpaces' | 'canManageEntitlementRules'>>;
  /**
   * Changes when the access groups changed elsewhere (real-time event): the list loads again
   */
  refreshKey?: number;
}

type FieldErrors = { name?: string | undefined; description?: string | undefined };

function validate(form: AccessGroupForm): FieldErrors {
  const parsed = accessGroupFormSchema.safeParse(form);
  if (parsed.success) {
    return {};
  }
  const errors: FieldErrors = {};
  for (const issue of parsed.error.issues) {
    const field = issue.path[0];
    if ((field === 'name' || field === 'description') && !errors[field]) {
      errors[field] = issue.message;
    }
  }
  return errors;
}

/**
 * The answer of the server to a moderator who adds a member by email address
 */
const EMAIL_SELECTOR_MESSAGE = 'Only the owner and the administrators add a member by email address. Use the username.';

function serverErrors(errors: Record<string, string[]>): FieldErrors {
  return { name: errors['name']?.[0], description: errors['description']?.[0] };
}

/**
 * The name and description fields of a group, for the creation form and the edit dialog
 */
function GroupFields(props: { idPrefix: string; value: AccessGroupForm; errors: FieldErrors; onChange: (value: AccessGroupForm) => void }) {
  const { idPrefix, value, errors, onChange } = props;
  return (
    <>
      <div className="flex-1">
        <InputLabel htmlFor={`${idPrefix}-name`}>Name</InputLabel>
        <TextInput
          id={`${idPrefix}-name`}
          wrapperClassName="mt-1"
          value={value.name}
          onChange={(event) => onChange({ ...value, name: event.target.value })}
          placeholder="For example: Paid members"
          invalid={!!errors.name}
          aria-describedby={errors.name ? `${idPrefix}-name-error` : undefined}
        />
        <InputError id={`${idPrefix}-name-error`} className="mt-2" message={errors.name} />
      </div>
      <div className="flex-1">
        <InputLabel htmlFor={`${idPrefix}-description`}>Description</InputLabel>
        <TextInput
          id={`${idPrefix}-description`}
          wrapperClassName="mt-1"
          value={value.description}
          onChange={(event) => onChange({ ...value, description: event.target.value })}
          placeholder="Optional"
          invalid={!!errors.description}
        />
        <InputError className="mt-2" message={errors.description} />
      </div>
    </>
  );
}

/**
 * Where a membership comes from (#1386, ADR 0047)
 */
function membershipSource(member: AccessGroupMember): string {
  const byHand = member.sources.includes('manual');
  const byRule = member.sources.includes('by_rule');
  if (byHand && byRule) {
    return 'Added by hand and by an entitlement rule';
  }
  return byRule ? 'By an entitlement rule' : 'Added by hand';
}

/**
 * The entitlement keys that give a group (#1386, ADR 0047): "a member who holds one of these keys at the sign-in provider is
 * in this group". Owner and admins of a community that the instance lists.
 */
function GroupRulesDialog(props: { communityCuid: string; group: AccessGroup | null; onClose: () => void; onSaved: () => void }) {
  const { communityCuid, group, onClose, onSaved } = props;
  const { showToast } = useAppData();
  // What the manager typed; null: nothing yet, the dialog shows the current keys of the group
  const [edited, setEdited] = useState<string | null>(null);
  const [error, setError] = useState<string | undefined>(undefined);
  const [saving, setSaving] = useState(false);
  const text = edited ?? (group?.entitlement_keys ?? []).join('\n');

  const close = () => {
    setEdited(null);
    setError(undefined);
    onClose();
  };

  const save = async () => {
    if (!group) {
      return;
    }
    const keys = parseEntitlementKeys(text);
    const problem = entitlementKeysError(keys, MAX_ENTITLEMENT_RULES_PER_GROUP);
    if (problem) {
      setError(problem);
      return;
    }
    setSaving(true);
    const result = await knowiiApiClient.communities.accessGroups.rules.set(communityCuid, group.cuid, keys);
    setSaving(false);
    if (!result.ok) {
      setError(Object.values(result.errors)[0]?.[0] ?? result.message);
      return;
    }
    showToast({ severity: 'success', summary: `Rules of ${group.name} saved` });
    onSaved();
    close();
  };

  return (
    <Dialog
      open={group !== null}
      onClose={close}
      size="md"
      title={`Entitlement rules of ${group?.name ?? ''}`}
      description="A member who holds one of these keys at the sign-in provider is in this group. Members you added by hand stay in it either way."
      footer={
        <>
          <Button variant="secondary" onClick={close}>
            Cancel
          </Button>
          <Button type="submit" form="access-group-rules-form" loading={saving}>
            Save
          </Button>
        </>
      }
    >
      <form
        id="access-group-rules-form"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void save();
        }}
      >
        <InputLabel htmlFor="access-group-rules">Entitlement keys, one per line</InputLabel>
        <Textarea
          id="access-group-rules"
          data-autofocus
          wrapperClassName="mt-1"
          value={text}
          onChange={(event) => setEdited(event.target.value)}
          placeholder="example.paid"
          invalid={!!error}
          aria-describedby={error ? 'access-group-rules-error' : undefined}
        />
        <InputError id="access-group-rules-error" className="mt-2" message={error} />
        <p className="mt-2 text-xs text-muted">Letters, digits, dots, dashes and underscores. Leave it empty to remove every rule.</p>
      </form>
    </Dialog>
  );
}

/**
 * The members of one group (#1241): add a member of the community by username or email, remove members. Only the owner
 * and the admins add by email address: a moderator adds by username.
 */
function GroupMembersDialog(props: { communityCuid: string; group: AccessGroup | null; byEmail: boolean; onClose: () => void }) {
  const { communityCuid, group, byEmail, onClose } = props;
  const { showToast } = useAppData();
  const [members, setMembers] = useState<AccessGroupMember[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [who, setWho] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [adding, setAdding] = useState(false);
  // The next members load ("Show more")
  const [loadingMore, setLoadingMore] = useState(false);
  // The dialog stays mounted from one group to the next (and to no group, when it closes)
  const groupCuid = group?.cuid ?? null;
  const [shownGroupCuid, setShownGroupCuid] = useState(groupCuid);
  if (shownGroupCuid !== groupCuid) {
    // Another group: "Show more" of the group before is not its business
    setShownGroupCuid(groupCuid);
    setLoadingMore(false);
  }
  // The number of times the open group changed: an answer that comes after a change was asked for the group before, and
  // is not for the group that is open now
  const groupChanges = useRef(0);

  useEffect(() => {
    groupChanges.current += 1;
  }, [groupCuid]);

  const load = useCallback(
    async (offset: number) => {
      if (!group) {
        return;
      }
      const asked = groupChanges.current;
      const result = await knowiiApiClient.communities.accessGroups.members.list(
        communityCuid,
        group.cuid,
        COMMUNITY_MEMBERS_PAGE_SIZE,
        offset,
      );
      if (asked !== groupChanges.current) {
        // The group changed before the answer came: these are not the members of the open group
        return;
      }
      if (result.ok) {
        setMembers((current) => (offset === 0 ? result.data.items : [...current, ...result.data.items]));
        setPagination(result.data.pagination);
      } else {
        showToast({ severity: 'error', summary: result.message });
      }
    },
    [communityCuid, group, showToast],
  );

  const showMore = async (offset: number) => {
    const asked = groupChanges.current;
    setLoadingMore(true);
    await load(offset);
    // After a change of group, the busy state is that of the new group: leave it
    if (asked === groupChanges.current) {
      setLoadingMore(false);
    }
  };

  useEffect(() => {
    if (!group) {
      return;
    }
    let active = true;
    void knowiiApiClient.communities.accessGroups.members.list(communityCuid, group.cuid, COMMUNITY_MEMBERS_PAGE_SIZE, 0).then((result) => {
      if (active && result.ok) {
        setMembers(result.data.items);
        setPagination(result.data.pagination);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, group]);

  const add = async () => {
    if (!group) {
      return;
    }
    const value = who.trim();
    if (value === '') {
      setError(byEmail ? 'Enter a username or an email address' : 'Enter a username');
      return;
    }
    // An email has a character before the @ (@ada-lovelace is a username)
    const isEmail = /^[^@\s]+@\S+$/.test(value);
    if (isEmail && !byEmail) {
      setError(EMAIL_SELECTOR_MESSAGE);
      return;
    }
    setAdding(true);
    const result = await knowiiApiClient.communities.accessGroups.members.add(
      communityCuid,
      group.cuid,
      isEmail ? { email: value } : { username: value.replace(/^@/, '') },
    );
    setAdding(false);
    if (!result.ok) {
      setError(Object.values(result.errors)[0]?.[0] ?? result.message);
      return;
    }
    setWho('');
    setError(undefined);
    // By email the server answers without the member, whether a member of the community has the address or not
    showToast(
      result.data
        ? { severity: 'success', summary: `${result.data.name} added to ${group.name}` }
        : {
            severity: 'info',
            summary: 'Address sent',
            detail: `If a member of the community has this address, they are now in ${group.name}.`,
          },
    );
    void load(0);
  };

  const remove = async (member: AccessGroupMember) => {
    if (!group) {
      return;
    }
    const result = await knowiiApiClient.communities.accessGroups.members.remove(communityCuid, group.cuid, member.cuid);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    if (member.sources.includes('by_rule')) {
      // Only the "by hand" source goes: an entitlement rule still gives the group (#1386, ADR 0047)
      setMembers((current) => current.map((item) => (item.cuid === member.cuid ? { ...item, sources: ['by_rule'] } : item)));
      showToast({
        severity: 'info',
        summary: `${member.name} is no longer added by hand. They stay in ${group.name} because of an entitlement rule`,
      });
      return;
    }
    setMembers((current) => current.filter((item) => item.cuid !== member.cuid));
    showToast({ severity: 'success', summary: `${member.name} removed from ${group.name}` });
  };

  return (
    <Dialog
      open={group !== null}
      onClose={onClose}
      size="lg"
      title={`Members of ${group?.name ?? ''}`}
      description="Only members of the community can join a group. They see the spaces that the group opens."
      footer={<Button onClick={onClose}>Close</Button>}
    >
      <form
        noValidate
        aria-label="Add a member to the group"
        className="flex flex-col gap-3 sm:flex-row sm:items-start"
        onSubmit={(event) => {
          event.preventDefault();
          void add();
        }}
      >
        <div className="flex-1">
          <InputLabel htmlFor="access-group-member">{byEmail ? 'Username or email' : 'Username'}</InputLabel>
          <TextInput
            id="access-group-member"
            // The dialog opens to add a member: the field gets the focus
            data-autofocus
            autoComplete="off"
            wrapperClassName="mt-1"
            value={who}
            onChange={(event) => setWho(event.target.value)}
            placeholder={byEmail ? 'ada-lovelace or ada@example.com' : 'ada-lovelace'}
            invalid={!!error}
            aria-describedby={error ? 'access-group-member-error' : undefined}
          />
          <InputError id="access-group-member-error" className="mt-2" message={error} />
        </div>
        <Button type="submit" className="sm:mt-7" loading={adding} leftIcon={<FaUserPlus aria-hidden="true" />}>
          Add
        </Button>
      </form>

      {members.length === 0 ? (
        <p className="mt-4 text-sm text-muted">No members in this group yet.</p>
      ) : (
        <ul className="mt-4 flex flex-col divide-y divide-border" aria-label={`Members of ${group?.name ?? ''}`}>
          {members.map((member) => (
            <li key={member.cuid} className="flex items-center gap-3 py-2">
              <Avatar name={member.name} imageUrl={member.avatar_url} size="sm" />
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate font-bold">{member.name}</span>
                <span className="truncate text-sm text-muted">@{member.username}</span>
                <span className="text-xs text-muted">{membershipSource(member)}</span>
              </div>
              {/* A member that only a rule gives has nothing to remove by hand: the rule or the entitlement has to change */}
              {member.sources.includes('manual') && (
                <Button variant="ghost" size="sm" onClick={() => void remove(member)} aria-label={`Remove ${member.name} from the group`}>
                  <FaUserMinus aria-hidden="true" />
                  Remove
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
      {pagination?.nextOffset != null && (
        <LoadMoreButton size="sm" loading={loadingMore} onClick={() => void showMore(pagination.nextOffset ?? 0)} className="mt-3">
          Show more
        </LoadMoreButton>
      )}
    </Dialog>
  );
}

/**
 * The space groups that one group opens whole (#1422, ADR 0041): every space of them with the "Access groups only"
 * visibility, the spaces added later included. For those who manage spaces.
 */
function GroupSpaceGroupsDialog(props: { communityCuid: string; group: AccessGroup | null; onClose: () => void; onSaved: () => void }) {
  const { communityCuid, group, onClose, onSaved } = props;
  const { showToast } = useAppData();
  const [spaceGroups, setSpaceGroups] = useState<SpaceGroup[] | null>(null);
  // What the manager changed; null: nothing yet, the dialog shows the current space groups of the group
  const [edited, setEdited] = useState<string[] | null>(null);
  const selected = edited ?? (group?.space_groups ?? []).map((spaceGroup) => spaceGroup.cuid);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!group) {
      return;
    }
    let active = true;
    void knowiiApiClient.communities.spaceGroups.list(communityCuid).then((result) => {
      if (active) {
        setSpaceGroups(result.ok ? result.data : []);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, group]);

  const close = () => {
    setEdited(null);
    onClose();
  };

  const save = async () => {
    if (!group) {
      return;
    }
    setSaving(true);
    const result = await knowiiApiClient.communities.accessGroups.spaceGroups.set(communityCuid, group.cuid, selected);
    setSaving(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.errors['space_groups']?.[0] ?? result.message });
      return;
    }
    showToast({ severity: 'success', summary: `Space groups of ${group.name} saved` });
    onSaved();
    close();
  };

  return (
    <Dialog
      open={group !== null}
      onClose={close}
      size="md"
      title={`Space groups of ${group?.name ?? ''}`}
      description="The members of this group read every space of the space groups that you choose, when the space has the visibility Access groups only. A space added to one of them later opens at once."
      footer={
        <>
          <Button variant="secondary" onClick={close}>
            Cancel
          </Button>
          <Button type="submit" form="access-group-space-groups-form" loading={saving} disabled={spaceGroups === null}>
            Save
          </Button>
        </>
      }
    >
      <form
        id="access-group-space-groups-form"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void save();
        }}
      >
        <fieldset aria-busy={spaceGroups === null}>
          <legend className="block text-sm font-bold text-primary">Space groups that this group opens</legend>
          {spaceGroups === null ? (
            <p className="mt-2 text-sm text-muted">Loading the space groups…</p>
          ) : spaceGroups.length === 0 ? (
            <p className="mt-2 text-sm">This community has no space group yet.</p>
          ) : (
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
              {spaceGroups.map((spaceGroup) => (
                <label key={spaceGroup.cuid} className="flex cursor-pointer items-center gap-2 text-sm">
                  <Checkbox
                    name={`space-group-${spaceGroup.cuid}`}
                    checked={selected.includes(spaceGroup.cuid)}
                    onChange={(event) =>
                      setEdited(event.target.checked ? [...selected, spaceGroup.cuid] : selected.filter((cuid) => cuid !== spaceGroup.cuid))
                    }
                  />
                  <span className="font-bold">{spaceGroup.name}</span>
                </label>
              ))}
            </div>
          )}
        </fieldset>
      </form>
    </Dialog>
  );
}

/**
 * Access groups (#1241, ADR 0025): named groups of members that open the spaces with the "Access groups only"
 * visibility. The owner and the admins create, rename and delete groups; moderators with the "manage members" right
 * add and remove their members. The API checks every action again.
 */
export function AccessGroupsManager({ communityCuid, permissions, refreshKey = 0 }: AccessGroupsManagerProps) {
  const { showToast } = useAppData();
  const [groups, setGroups] = useState<AccessGroup[] | null>(null);
  const [draft, setDraft] = useState<AccessGroupForm>({ name: '', description: '' });
  const [draftErrors, setDraftErrors] = useState<FieldErrors>({});
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<AccessGroup | null>(null);
  const [edit, setEdit] = useState<AccessGroupForm>({ name: '', description: '' });
  const [editErrors, setEditErrors] = useState<FieldErrors>({});
  const [deleting, setDeleting] = useState<AccessGroup | null>(null);
  const [membersOf, setMembersOf] = useState<AccessGroup | null>(null);
  const [spaceGroupsOf, setSpaceGroupsOf] = useState<AccessGroup | null>(null);
  const [rulesOf, setRulesOf] = useState<AccessGroup | null>(null);

  const reload = useCallback(async () => {
    const result = await knowiiApiClient.communities.accessGroups.list(communityCuid);
    if (result.ok) {
      setGroups(result.data);
    }
  }, [communityCuid]);

  useEffect(() => {
    let active = true;
    void knowiiApiClient.communities.accessGroups.list(communityCuid).then((result) => {
      if (active) {
        setGroups(result.ok ? result.data : []);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, refreshKey]);

  const create = async () => {
    const errors = validate(draft);
    setDraftErrors(errors);
    if (Object.keys(errors).length > 0) {
      return;
    }
    setCreating(true);
    const result = await knowiiApiClient.communities.accessGroups.create(communityCuid, draft);
    setCreating(false);
    if (!result.ok) {
      setDraftErrors(serverErrors(result.errors));
      if (Object.keys(result.errors).length === 0) {
        showToast({ severity: 'error', summary: result.message });
      }
      return;
    }
    setDraft({ name: '', description: '' });
    showToast({ severity: 'success', summary: `Access group ${result.data.name} created` });
    void reload();
  };

  const save = async () => {
    if (!editing) {
      return;
    }
    const errors = validate(edit);
    setEditErrors(errors);
    if (Object.keys(errors).length > 0) {
      return;
    }
    const result = await knowiiApiClient.communities.accessGroups.update(communityCuid, editing.cuid, edit);
    if (!result.ok) {
      setEditErrors(serverErrors(result.errors));
      return;
    }
    setEditing(null);
    showToast({ severity: 'success', summary: 'Access group saved' });
    void reload();
  };

  const destroy = async () => {
    if (!deleting) {
      return;
    }
    const result = await knowiiApiClient.communities.accessGroups.delete(communityCuid, deleting.cuid);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: `Access group ${deleting.name} deleted` });
    setDeleting(null);
    void reload();
  };

  return (
    <Card aria-labelledby="access-groups-title">
      <CardHeader title={<span id="access-groups-title">Access groups</span>} icon={<FaUserLock aria-hidden="true" />} />
      <CardBody className="flex flex-col gap-6">
        <p className="text-sm text-muted">
          A space with the visibility &quot;Access groups only&quot; opens to the members of its groups, for example paid members. The owner
          and the admins always see it.
        </p>

        {permissions.canManageAccessGroups && (
          <form
            noValidate
            aria-label="Create an access group"
            className="flex flex-col gap-3 sm:flex-row sm:items-start"
            onSubmit={(event) => {
              event.preventDefault();
              void create();
            }}
          >
            <GroupFields idPrefix="new-access-group" value={draft} errors={draftErrors} onChange={setDraft} />
            <Button type="submit" className="sm:mt-7" loading={creating}>
              Create
            </Button>
          </form>
        )}

        {groups !== null && groups.length === 0 ? (
          <EmptyState title="No access groups yet" description="Create a group, add members to it, then give it spaces." />
        ) : (
          <ul className="flex flex-col divide-y divide-border" aria-label="Access groups" aria-busy={groups === null}>
            {(groups ?? []).map((group) => (
              <li key={group.cuid} className="flex flex-wrap items-center gap-3 py-3">
                <div className="flex min-w-0 flex-1 flex-col">
                  <span className="truncate font-bold">{group.name}</span>
                  {group.description && <span className="text-sm text-muted">{group.description}</span>}
                  <span className="text-xs text-muted">
                    {group.members_count ?? 0} {group.members_count === 1 ? 'member' : 'members'} · {group.spaces_count ?? 0}{' '}
                    {group.spaces_count === 1 ? 'space' : 'spaces'}
                  </span>
                  {(group.entitlement_keys ?? []).length > 0 && (
                    <span className="text-xs text-muted">Members also come from: {(group.entitlement_keys ?? []).join(', ')}</span>
                  )}
                  {(group.space_groups ?? []).length > 0 && (
                    <span className="text-xs text-muted">
                      Opens the space {(group.space_groups ?? []).length === 1 ? 'group' : 'groups'}:{' '}
                      {(group.space_groups ?? []).map((spaceGroup) => spaceGroup.name).join(', ')}
                    </span>
                  )}
                </div>
                {permissions.canManageAccessGroupMembers && (
                  <Button variant="ghost" size="sm" onClick={() => setMembersOf(group)} aria-label={`Members of ${group.name}`}>
                    <FaUsers aria-hidden="true" />
                    Members
                  </Button>
                )}
                {permissions.canManageEntitlementRules && (
                  <Button variant="ghost" size="sm" onClick={() => setRulesOf(group)} aria-label={`Entitlement rules of ${group.name}`}>
                    <FaKey aria-hidden="true" />
                    Rules
                  </Button>
                )}
                {permissions.canManageSpaces && (
                  <Button variant="ghost" size="sm" onClick={() => setSpaceGroupsOf(group)} aria-label={`Space groups of ${group.name}`}>
                    <FaLayerGroup aria-hidden="true" />
                    Space groups
                  </Button>
                )}
                {permissions.canManageAccessGroups && (
                  <>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setEdit({ name: group.name, description: group.description });
                        setEditErrors({});
                        setEditing(group);
                      }}
                      aria-label={`Edit ${group.name}`}
                    >
                      <FaPen aria-hidden="true" />
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => setDeleting(group)} aria-label={`Delete ${group.name}`}>
                      <FaTrash aria-hidden="true" />
                      Delete
                    </Button>
                  </>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardBody>

      <Dialog
        open={editing !== null}
        onClose={() => setEditing(null)}
        title={`Edit ${editing?.name ?? ''}`}
        footer={
          <>
            <Button variant="secondary" onClick={() => setEditing(null)}>
              Cancel
            </Button>
            <Button type="submit" form="edit-access-group-form">
              Save
            </Button>
          </>
        }
      >
        <form
          id="edit-access-group-form"
          noValidate
          className="flex flex-col gap-3"
          onSubmit={(event) => {
            event.preventDefault();
            void save();
          }}
        >
          <GroupFields idPrefix="edit-access-group" value={edit} errors={editErrors} onChange={setEdit} />
        </form>
      </Dialog>

      <Dialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        size="sm"
        title={`Delete ${deleting?.name ?? ''}?`}
        description="Its members lose the spaces that only this group opens. A space without any group left stays visible to the owner and the admins only."
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleting(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={() => void destroy()}>
              Delete
            </Button>
          </>
        }
      />

      <GroupRulesDialog communityCuid={communityCuid} group={rulesOf} onClose={() => setRulesOf(null)} onSaved={() => void reload()} />

      <GroupSpaceGroupsDialog
        communityCuid={communityCuid}
        group={spaceGroupsOf}
        onClose={() => setSpaceGroupsOf(null)}
        onSaved={() => void reload()}
      />

      <GroupMembersDialog
        communityCuid={communityCuid}
        group={membersOf}
        // The owner and the admins (who manage the groups) also add by email address
        byEmail={permissions.canManageAccessGroups}
        onClose={() => {
          setMembersOf(null);
          void reload();
        }}
      />
    </Card>
  );
}
