import { useCallback, useEffect, useState } from 'react';
import type { AccessGroup, AccessGroupForm, Author, CommunityPermissions, Pagination } from '@knowii/common';
import { accessGroupFormSchema, COMMUNITY_MEMBERS_PAGE_SIZE, knowiiApiClient, useAppData } from '@knowii/common';
import { FaPen, FaTrash, FaUserLock, FaUserMinus, FaUserPlus, FaUsers } from 'react-icons/fa6';
import { Avatar, Button, Card, CardBody, CardHeader, Dialog, EmptyState, TextInput } from '@/Components/ui';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

export interface AccessGroupsManagerProps {
  communityCuid: string;
  permissions: Pick<CommunityPermissions, 'canManageAccessGroups' | 'canManageAccessGroupMembers'>;
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
 * The members of one group (#1241): add a member of the community by username or email, remove members.
 */
function GroupMembersDialog(props: { communityCuid: string; group: AccessGroup | null; onClose: () => void }) {
  const { communityCuid, group, onClose } = props;
  const { showToast } = useAppData();
  const [members, setMembers] = useState<Author[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [who, setWho] = useState('');
  const [error, setError] = useState<string | undefined>(undefined);
  const [adding, setAdding] = useState(false);

  const load = useCallback(
    async (offset: number) => {
      if (!group) {
        return;
      }
      const result = await knowiiApiClient.communities.accessGroups.members.list(
        communityCuid,
        group.cuid,
        COMMUNITY_MEMBERS_PAGE_SIZE,
        offset,
      );
      if (result.ok) {
        setMembers((current) => (offset === 0 ? result.data.items : [...current, ...result.data.items]));
        setPagination(result.data.pagination);
      } else {
        showToast({ severity: 'error', summary: result.message });
      }
    },
    [communityCuid, group, showToast],
  );

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
      setError('Enter a username or an email address');
      return;
    }
    setAdding(true);
    const result = await knowiiApiClient.communities.accessGroups.members.add(
      communityCuid,
      group.cuid,
      // An email has a character before the @ (@ada-lovelace is a username)
      /^[^@\s]+@\S+$/.test(value) ? { email: value } : { username: value.replace(/^@/, '') },
    );
    setAdding(false);
    if (!result.ok) {
      setError(Object.values(result.errors)[0]?.[0] ?? result.message);
      return;
    }
    setWho('');
    setError(undefined);
    showToast({ severity: 'success', summary: `${result.data.name} added to ${group.name}` });
    void load(0);
  };

  const remove = async (member: Author) => {
    if (!group) {
      return;
    }
    const result = await knowiiApiClient.communities.accessGroups.members.remove(communityCuid, group.cuid, member.cuid);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
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
          <InputLabel htmlFor="access-group-member">Username or email</InputLabel>
          <TextInput
            id="access-group-member"
            // The dialog opens to add a member: the field gets the focus
            data-autofocus
            autoComplete="off"
            wrapperClassName="mt-1"
            value={who}
            onChange={(event) => setWho(event.target.value)}
            placeholder="ada-lovelace or ada@example.com"
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
              </div>
              <Button variant="ghost" size="sm" onClick={() => void remove(member)} aria-label={`Remove ${member.name} from the group`}>
                <FaUserMinus aria-hidden="true" />
                Remove
              </Button>
            </li>
          ))}
        </ul>
      )}
      {pagination?.nextOffset != null && (
        <div className="mt-3 flex justify-center">
          <Button variant="secondary" size="sm" onClick={() => void load(pagination.nextOffset ?? 0)}>
            Show more
          </Button>
        </div>
      )}
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
                </div>
                {permissions.canManageAccessGroupMembers && (
                  <Button variant="ghost" size="sm" onClick={() => setMembersOf(group)} aria-label={`Members of ${group.name}`}>
                    <FaUsers aria-hidden="true" />
                    Members
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

      <GroupMembersDialog
        communityCuid={communityCuid}
        group={membersOf}
        onClose={() => {
          setMembersOf(null);
          void reload();
        }}
      />
    </Card>
  );
}
