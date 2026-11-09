import { useCallback, useEffect, useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Link } from '@inertiajs/react';
import type {
  AssignableCommunityRole,
  CommunityInvitation,
  CommunityInvitationForm,
  CommunityMember,
  CommunityPermissions,
  CommunityRole,
  Pagination,
} from '@knowii/common';
import {
  canActOnRole,
  COMMUNITY_MEMBERS_PAGE_SIZE,
  COMMUNITY_ROLE_LABELS,
  communityInvitationFormSchema,
  grantableRoles,
  knowiiApiClient,
  MEMBER_URL,
  useAppData,
} from '@knowii/common';
import { FaMagnifyingGlass, FaUserMinus, FaUserPlus, FaUsers, FaXmark } from 'react-icons/fa6';
import { Avatar, Button, Card, CardBody, CardHeader, Dialog, EmptyState, RelativeTime, Select, TextInput } from '@/Components/ui';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

export interface CommunityMembersManagerProps {
  communityCuid: string;
  communitySlug: string;
  /**
   * The role of the current user: the screen offers only the roles below it (#1242)
   */
  currentRole: CommunityRole;
  permissions: Pick<CommunityPermissions, 'canAddCommunityMembers' | 'canUpdateCommunityMembers' | 'canRemoveCommunityMembers'>;
  /**
   * Changes when the members or the invitations changed elsewhere (real-time event): the lists load again
   */
  refreshKey?: number;
}

const SEARCH_DELAY_MS = 250;

/**
 * Member management (#1242): invite by email, change roles and remove members, within the role hierarchy of ADR 0017.
 * The screen hides what the current user may not do; the API checks every action again.
 */
export function CommunityMembersManager({
  communityCuid,
  communitySlug,
  currentRole,
  permissions,
  refreshKey = 0,
}: CommunityMembersManagerProps) {
  const { showToast } = useAppData();
  const roles = grantableRoles(currentRole);
  const canInvite = permissions.canAddCommunityMembers && roles.length > 0;

  const [members, setMembers] = useState<CommunityMember[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState('');
  const [invitations, setInvitations] = useState<CommunityInvitation[]>([]);
  const [pendingRemoval, setPendingRemoval] = useState<CommunityMember | null>(null);
  const [busy, setBusy] = useState(false);
  const requestId = useRef(0);

  const loadMembers = useCallback(
    async (offset: number, search: string) => {
      const id = ++requestId.current;
      setLoading(true);
      const result = await knowiiApiClient.communities.members.list(communityCuid, COMMUNITY_MEMBERS_PAGE_SIZE, offset, search);
      // A newer search started meanwhile: drop this answer
      if (id !== requestId.current) {
        return;
      }
      setLoading(false);
      if (!result.ok) {
        showToast({ severity: 'error', summary: result.message });
        return;
      }
      setMembers((current) => (offset === 0 ? result.data.items : [...current, ...result.data.items]));
      setPagination(result.data.pagination);
    },
    [communityCuid, showToast],
  );

  const loadInvitations = useCallback(async () => {
    if (!permissions.canAddCommunityMembers) {
      return;
    }
    const result = await knowiiApiClient.communities.invitations.list(communityCuid);
    if (result.ok) {
      setInvitations(result.data);
    }
  }, [communityCuid, permissions.canAddCommunityMembers]);

  useEffect(() => {
    const timer = setTimeout(() => void loadMembers(0, query.trim()), query === '' ? 0 : SEARCH_DELAY_MS);
    return () => clearTimeout(timer);
  }, [loadMembers, query, refreshKey]);

  useEffect(() => {
    if (!permissions.canAddCommunityMembers) {
      return;
    }
    let active = true;
    void knowiiApiClient.communities.invitations.list(communityCuid).then((result) => {
      if (active && result.ok) {
        setInvitations(result.data);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, permissions.canAddCommunityMembers, refreshKey]);

  const form = useForm<CommunityInvitationForm>({
    resolver: zodResolver(communityInvitationFormSchema),
    defaultValues: { email: '', role: 'member' },
  });
  const { errors } = form.formState;

  const onInvite = form.handleSubmit(async (data) => {
    const result = await knowiiApiClient.communities.invitations.create(communityCuid, data);
    if (!result.ok) {
      for (const [field, messages] of Object.entries(result.errors)) {
        if ((field === 'email' || field === 'role') && messages[0]) {
          form.setError(field, { message: messages[0] });
        }
      }
      if (Object.keys(result.errors).length === 0) {
        showToast({ severity: 'error', summary: result.message });
      }
      return;
    }
    form.reset({ email: '', role: data.role });
    showToast({ severity: 'success', summary: `Invitation sent to ${result.data.email}` });
    void loadInvitations();
  });

  const changeRole = async (member: CommunityMember, role: AssignableCommunityRole) => {
    if (role === member.role) {
      return;
    }
    const result = await knowiiApiClient.communities.members.updateRole(communityCuid, member.cuid, role);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setMembers((current) => current.map((item) => (item.cuid === member.cuid ? result.data : item)));
    showToast({ severity: 'success', summary: `${member.name} is now ${COMMUNITY_ROLE_LABELS[role].toLowerCase()}` });
  };

  const confirmRemoval = async () => {
    if (!pendingRemoval) {
      return;
    }
    setBusy(true);
    const result = await knowiiApiClient.communities.members.remove(communityCuid, pendingRemoval.cuid);
    setBusy(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: `${pendingRemoval.name} was removed` });
    setMembers((current) => current.filter((item) => item.cuid !== pendingRemoval.cuid));
    setPendingRemoval(null);
  };

  const cancelInvitation = async (invitation: CommunityInvitation) => {
    const result = await knowiiApiClient.communities.invitations.cancel(communityCuid, invitation.cuid);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setInvitations((current) => current.filter((item) => item.cuid !== invitation.cuid));
    showToast({ severity: 'success', summary: `Invitation to ${invitation.email} cancelled` });
  };

  const roleOptions = roles.map((role) => ({ value: role, label: COMMUNITY_ROLE_LABELS[role] }));

  return (
    <div className="flex flex-col gap-6">
      <Card aria-labelledby="members-title">
        <CardHeader title={<span id="members-title">Members</span>} icon={<FaUsers aria-hidden="true" />} />
        <CardBody className="flex flex-col gap-6">
          {canInvite && (
            <form onSubmit={onInvite} noValidate className="flex flex-col gap-3 sm:flex-row sm:items-start" aria-label="Invite a member">
              <div className="flex-1">
                <InputLabel htmlFor="invitation-email">Invite by email</InputLabel>
                <Controller
                  name="email"
                  control={form.control}
                  render={({ field }) => (
                    <TextInput
                      id="invitation-email"
                      type="email"
                      autoComplete="off"
                      wrapperClassName="mt-1"
                      placeholder="name@example.com"
                      {...field}
                      invalid={!!errors.email}
                      aria-describedby={errors.email ? 'invitation-email-error' : undefined}
                    />
                  )}
                />
                <InputError id="invitation-email-error" className="mt-2" message={errors.email?.message} />
              </div>
              <div className="sm:w-44">
                <InputLabel htmlFor="invitation-role">Role</InputLabel>
                <Controller
                  name="role"
                  control={form.control}
                  render={({ field }) => (
                    <Select<AssignableCommunityRole>
                      id="invitation-role"
                      className="mt-1"
                      options={roleOptions}
                      value={field.value}
                      onChange={field.onChange}
                    />
                  )}
                />
                <InputError className="mt-2" message={errors.role?.message} />
              </div>
              <Button type="submit" className="sm:mt-7" loading={form.formState.isSubmitting} leftIcon={<FaUserPlus aria-hidden="true" />}>
                Invite
              </Button>
            </form>
          )}

          <div>
            <TextInput
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search by name or username"
              aria-label="Search the members"
              icon={<FaMagnifyingGlass aria-hidden="true" />}
            />
          </div>

          {!loading && members.length === 0 ? (
            <EmptyState title={query === '' ? 'No members yet' : 'No member matches this search'} />
          ) : (
            <ul className="flex flex-col divide-y divide-border" aria-label="Members" aria-busy={loading}>
              {members.map((member) => {
                const manageable = canActOnRole(currentRole, member.role);
                const canChangeRole = permissions.canUpdateCommunityMembers && manageable;
                const canRemove = permissions.canRemoveCommunityMembers && manageable;
                return (
                  <li key={member.cuid} className="flex flex-wrap items-center gap-3 py-3">
                    <Avatar name={member.name} size="sm" />
                    <div className="flex min-w-0 flex-1 flex-col">
                      <Link
                        href={route(MEMBER_URL, { communitySlug, username: member.username })}
                        className="truncate font-bold text-primary hover:underline"
                      >
                        {member.name}
                      </Link>
                      <span className="truncate text-sm text-muted">@{member.username}</span>
                    </div>
                    {canChangeRole ? (
                      <Select<AssignableCommunityRole>
                        className="w-40"
                        size="sm"
                        aria-label={`Role of ${member.name}`}
                        options={roleOptions}
                        value={member.role === 'owner' ? null : member.role}
                        onChange={(role) => void changeRole(member, role)}
                      />
                    ) : (
                      <span className="rounded-full bg-overlay/10 px-3 py-1 text-sm">{COMMUNITY_ROLE_LABELS[member.role]}</span>
                    )}
                    {canRemove && (
                      <Button
                        variant="ghost"
                        size="sm"
                        iconOnly
                        aria-label={`Remove ${member.name}`}
                        title={`Remove ${member.name}`}
                        onClick={() => setPendingRemoval(member)}
                      >
                        <FaUserMinus aria-hidden="true" />
                      </Button>
                    )}
                  </li>
                );
              })}
            </ul>
          )}

          {pagination?.nextOffset != null && (
            <div className="flex justify-center">
              <Button variant="secondary" loading={loading} onClick={() => void loadMembers(pagination.nextOffset ?? 0, query.trim())}>
                Load more
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      {permissions.canAddCommunityMembers && (
        <Card aria-labelledby="invitations-title">
          <CardHeader title={<span id="invitations-title">Pending invitations</span>} icon={<FaUserPlus aria-hidden="true" />} />
          <CardBody>
            {invitations.length === 0 ? (
              <p className="text-sm text-muted">No pending invitations.</p>
            ) : (
              <ul className="flex flex-col divide-y divide-border" aria-label="Pending invitations">
                {invitations.map((invitation) => (
                  <li key={invitation.cuid} className="flex flex-wrap items-center gap-3 py-3">
                    <div className="flex min-w-0 flex-1 flex-col">
                      <span className="truncate font-bold">{invitation.email}</span>
                      <span className="text-sm text-muted">
                        {COMMUNITY_ROLE_LABELS[invitation.role]}, sent <RelativeTime date={invitation.created_at} />
                        {invitation.is_expired && <span className="ml-2 font-bold text-danger-text">Expired</span>}
                      </span>
                    </div>
                    {roles.includes(invitation.role) && (
                      <Button
                        variant="ghost"
                        size="sm"
                        aria-label={`Cancel the invitation to ${invitation.email}`}
                        onClick={() => void cancelInvitation(invitation)}
                        leftIcon={<FaXmark aria-hidden="true" />}
                      >
                        Cancel
                      </Button>
                    )}
                  </li>
                ))}
              </ul>
            )}
            <p className="mt-3 text-xs text-muted">
              Invitation links work for 7 days, only for an account with the invited email address. Invite again to send a new link.
            </p>
          </CardBody>
        </Card>
      )}

      <Dialog
        open={pendingRemoval !== null}
        onClose={() => setPendingRemoval(null)}
        size="sm"
        title={`Remove ${pendingRemoval?.name ?? ''}?`}
        description="They lose access to the community. What they wrote stays. You can invite them again later."
        footer={
          <>
            <Button variant="secondary" onClick={() => setPendingRemoval(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={() => void confirmRemoval()} loading={busy}>
              Remove
            </Button>
          </>
        }
      />
    </div>
  );
}
