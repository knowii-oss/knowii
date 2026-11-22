import { useCallback, useEffect, useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Link } from '@inertiajs/react';
import type {
  AssignableCommunityRole,
  CommunityInvitation,
  InvitationAccessGroup,
  CommunityInvitationForm,
  CommunityJoinBlock,
  CommunityMember,
  CommunityPermissions,
  CommunityRole,
  MemberTag,
  Pagination,
} from '@knowii/common';
import {
  canActOnRole,
  COMMUNITY_MEMBERS_PAGE_SIZE,
  COMMUNITY_ROLE_LABELS,
  communityInvitationFormSchema,
  grantableRoles,
  INVITATIONS_PAGE_SIZE,
  knowiiApiClient,
  MEMBER_URL,
  useAppData,
} from '@knowii/common';
import { FaMagnifyingGlass, FaTags, FaUserLock, FaUserMinus, FaUserPlus, FaUsers, FaXmark } from 'react-icons/fa6';
import {
  Avatar,
  Button,
  Card,
  CardBody,
  CardHeader,
  Dialog,
  EmptyState,
  InputError,
  InputLabel,
  LoadMoreButton,
  RelativeTime,
  Select,
  TextInput,
  useFocusAfterRender,
} from '@/Components/ui';
import { MemberTagList } from '@/Components/Members/MemberTagList';
import { MemberTagsDialog } from './MemberTagsDialog';
import type { InvitationGrants } from './InvitationGrantsPicker';
import { InvitationGrantsPicker, NO_INVITATION_GRANTS } from './InvitationGrantsPicker';
import { INVITATION_WORDS } from './invitation-words';

export interface CommunityMembersManagerProps {
  communityCuid: string;
  communitySlug: string;
  /**
   * The role of the current user: the screen offers only the roles below it (#1242)
   */
  currentRole: CommunityRole;
  permissions: Pick<CommunityPermissions, 'canAddCommunityMembers' | 'canUpdateCommunityMembers' | 'canRemoveCommunityMembers'> &
    Partial<Pick<CommunityPermissions, 'canAssignMemberTags' | 'canManageAccessGroupMembers'>>;
  /**
   * Changes when the members or the invitations changed elsewhere (real-time event): the lists load again
   */
  refreshKey?: number;
  /**
   * Changes when the access groups or the member tags of the community changed: the choices of the invitation form
   * load again (#1390)
   */
  grantsRefreshKey?: number;
  /**
   * After a tag was put on a member or taken off (#1389): the section "Member tags" counts again
   */
  onMemberTagsChanged?: () => void;
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
  grantsRefreshKey = 0,
  onMemberTagsChanged,
}: CommunityMembersManagerProps) {
  const { showToast } = useAppData();
  const roles = grantableRoles(currentRole);
  const canInvite = permissions.canAddCommunityMembers && roles.length > 0;

  const [members, setMembers] = useState<CommunityMember[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState('');
  const [invitations, setInvitations] = useState<CommunityInvitation[]>([]);
  // The pending invitations load one page at a time (#1390): a file adds up to 1000 of them
  const [invitationsNext, setInvitationsNext] = useState<number | null>(null);
  const [loadingInvitations, setLoadingInvitations] = useState(false);
  const [invitationsStatus, setInvitationsStatus] = useState('');
  const invitationsRef = useRef<HTMLUListElement>(null);
  const focusAfterRender = useFocusAfterRender();
  // What the invitation gives at the join (#1390): the choices of the community, and the choice of the form
  const canGiveAccessGroups = permissions.canManageAccessGroupMembers === true;
  const canGiveMemberTags = permissions.canAssignMemberTags === true;
  const [grantChoices, setGrantChoices] = useState<{ accessGroups: InvitationAccessGroup[]; memberTags: MemberTag[] }>({
    accessGroups: [],
    memberTags: [],
  });
  const [grants, setGrants] = useState<InvitationGrants>(NO_INVITATION_GRANTS);
  const [grantsError, setGrantsError] = useState('');
  const [joinBlocks, setJoinBlocks] = useState<CommunityJoinBlock[]>([]);
  const [pendingRemoval, setPendingRemoval] = useState<CommunityMember | null>(null);
  const [busy, setBusy] = useState(false);
  // The member whose tags the dialog shows (#1389)
  const [taggedMember, setTaggedMember] = useState<CommunityMember | null>(null);
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
    const result = await knowiiApiClient.communities.invitations.list(communityCuid, INVITATIONS_PAGE_SIZE, 0);
    if (result.ok) {
      setInvitations(result.data.items);
      setInvitationsNext(result.data.pagination.nextOffset ?? null);
    }
  }, [communityCuid, permissions.canAddCommunityMembers]);

  const loadMoreInvitations = async () => {
    if (invitationsNext === null || loadingInvitations) {
      return;
    }
    setLoadingInvitations(true);
    const result = await knowiiApiClient.communities.invitations.list(communityCuid, INVITATIONS_PAGE_SIZE, invitationsNext);
    setLoadingInvitations(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    // An invitation can move between two loads when the list changes on the server: never two rows for one invitation
    const added = result.data.items.filter((item) => !invitations.some((shown) => shown.cuid === item.cuid));
    setInvitations((current) => [...current, ...added.filter((item) => !current.some((shown) => shown.cuid === item.cuid))]);
    setInvitationsNext(result.data.pagination.nextOffset ?? null);
    setInvitationsStatus(INVITATION_WORDS.loaded(added.length));
    const first = added[0];
    if (first) {
      // The button may go away with the last page: the reader continues on the first new row
      focusAfterRender(() =>
        Array.from(invitationsRef.current?.querySelectorAll<HTMLElement>('[data-invitation]') ?? []).find(
          (row) => row.dataset['invitation'] === first.cuid,
        ),
      );
    }
  };

  useEffect(() => {
    const timer = setTimeout(() => void loadMembers(0, query.trim()), query === '' ? 0 : SEARCH_DELAY_MS);
    return () => clearTimeout(timer);
  }, [loadMembers, query, refreshKey]);

  useEffect(() => {
    if (!permissions.canAddCommunityMembers) {
      return;
    }
    let active = true;
    void knowiiApiClient.communities.invitations.list(communityCuid, INVITATIONS_PAGE_SIZE, 0).then((result) => {
      if (active && result.ok) {
        setInvitations(result.data.items);
        setInvitationsNext(result.data.pagination.nextOffset ?? null);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, permissions.canAddCommunityMembers, refreshKey]);

  // The groups and the tags that an invitation can give (#1390): only for the people who may give them
  useEffect(() => {
    if (!canInvite || (!canGiveAccessGroups && !canGiveMemberTags)) {
      return;
    }
    let active = true;
    void Promise.all([
      canGiveAccessGroups ? knowiiApiClient.communities.accessGroups.list(communityCuid) : null,
      canGiveMemberTags ? knowiiApiClient.communities.memberTags.list(communityCuid) : null,
    ]).then(([groups, tags]) => {
      if (!active) {
        return;
      }
      setGrantChoices({
        accessGroups: groups?.ok ? groups.data.map((group) => ({ cuid: group.cuid, name: group.name })) : [],
        memberTags: tags?.ok ? tags.data : [],
      });
    });
    return () => {
      active = false;
    };
  }, [communityCuid, canInvite, canGiveAccessGroups, canGiveMemberTags, grantsRefreshKey]);

  // The people whom a manager removed cannot join again by themselves (#1486): the managers of the members lift it
  const loadJoinBlocks = useCallback(async () => {
    if (!permissions.canRemoveCommunityMembers) {
      return;
    }
    const result = await knowiiApiClient.communities.members.joinBlocks(communityCuid);
    if (result.ok) {
      setJoinBlocks(result.data);
    }
  }, [communityCuid, permissions.canRemoveCommunityMembers]);

  useEffect(() => {
    if (!permissions.canRemoveCommunityMembers) {
      return;
    }
    let active = true;
    void knowiiApiClient.communities.members.joinBlocks(communityCuid).then((result) => {
      if (active && result.ok) {
        setJoinBlocks(result.data);
      }
    });
    return () => {
      active = false;
    };
  }, [communityCuid, permissions.canRemoveCommunityMembers, refreshKey]);

  const form = useForm<CommunityInvitationForm>({
    resolver: zodResolver(communityInvitationFormSchema),
    defaultValues: { email: '', role: 'member' },
  });
  const { errors } = form.formState;

  const onInvite = form.handleSubmit(async (data) => {
    setGrantsError('');
    const result = await knowiiApiClient.communities.invitations.create(communityCuid, {
      ...data,
      access_groups: grants.accessGroups,
      member_tags: grants.memberTags,
    });
    if (!result.ok) {
      for (const [field, messages] of Object.entries(result.errors)) {
        if ((field === 'email' || field === 'role') && messages[0]) {
          form.setError(field, { message: messages[0] });
        }
        if ((field === 'access_groups' || field === 'member_tags') && messages[0]) {
          setGrantsError(messages[0]);
        }
      }
      if (Object.keys(result.errors).length === 0) {
        showToast({ severity: 'error', summary: result.message });
      }
      return;
    }
    // The form stays on the page: the focus goes back in the email field, for the next invitation (#1618). Before the
    // reset: the reset removes the fields until the next render, and setFocus finds no field then.
    form.setFocus('email');
    form.reset({ email: '', role: data.role });
    setGrants(NO_INVITATION_GRANTS);
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
    void loadJoinBlocks();
  };

  const liftJoinBlock = async (block: CommunityJoinBlock) => {
    const result = await knowiiApiClient.communities.members.liftJoinBlock(communityCuid, block.cuid);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setJoinBlocks((current) => current.filter((item) => item.cuid !== block.cuid));
    showToast({ severity: 'success', summary: `${block.name} may join again` });
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

  const changeTags = (memberCuid: string, tags: MemberTag[]) => {
    setMembers((current) => current.map((item) => (item.cuid === memberCuid ? { ...item, tags } : item)));
    setTaggedMember((current) => (current?.cuid === memberCuid ? { ...current, tags } : current));
    onMemberTagsChanged?.();
  };

  const roleOptions = roles.map((role) => ({ value: role, label: COMMUNITY_ROLE_LABELS[role] }));

  return (
    <div className="flex flex-col gap-6">
      <Card aria-labelledby="members-title">
        <CardHeader title={<span id="members-title">Members</span>} icon={<FaUsers aria-hidden="true" />} />
        <CardBody className="flex flex-col gap-6">
          {canInvite && (
            <form
              onSubmit={onInvite}
              noValidate
              className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start"
              aria-label="Invite a member"
            >
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
              <InvitationGrantsPicker
                className="sm:mt-7"
                accessGroups={grantChoices.accessGroups}
                memberTags={grantChoices.memberTags}
                canGiveAccessGroups={canGiveAccessGroups}
                canGiveMemberTags={canGiveMemberTags}
                value={grants}
                onChange={(value) => {
                  setGrants(value);
                  setGrantsError('');
                }}
              />
              <Button type="submit" className="sm:mt-7" loading={form.formState.isSubmitting} leftIcon={<FaUserPlus aria-hidden="true" />}>
                Invite
              </Button>
              {grantsError !== '' && <InputError className="basis-full" message={grantsError} />}
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
                    <Avatar name={member.name} imageUrl={member.avatar_url} size="sm" />
                    <div className="flex min-w-0 flex-1 basis-48 flex-col">
                      <Link
                        href={route(MEMBER_URL, { communitySlug, username: member.username })}
                        className="truncate font-bold text-primary hover:underline"
                      >
                        {member.name}
                      </Link>
                      <span className="truncate text-sm text-muted">@{member.username}</span>
                      <MemberTagList tags={member.tags} className="mt-1" />
                    </div>
                    {permissions.canAssignMemberTags === true && (
                      <Button
                        variant="ghost"
                        size="sm"
                        iconOnly
                        aria-label={`Tags of ${member.name}`}
                        title={`Tags of ${member.name}`}
                        onClick={() => setTaggedMember(member)}
                      >
                        <FaTags aria-hidden="true" />
                      </Button>
                    )}
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
            <LoadMoreButton loading={loading} onClick={() => void loadMembers(pagination.nextOffset ?? 0, query.trim())}>
              Load more
            </LoadMoreButton>
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
              <ul ref={invitationsRef} className="flex flex-col divide-y divide-border" aria-label="Pending invitations">
                {invitations.map((invitation) => (
                  <li
                    key={invitation.cuid}
                    data-invitation={invitation.cuid}
                    tabIndex={-1}
                    className="flex flex-wrap items-center gap-3 py-3 focus-visible:outline-2 focus-visible:outline-primary"
                  >
                    <div className="flex min-w-0 flex-1 basis-48 flex-col">
                      <span className="truncate font-bold">{invitation.email}</span>
                      <span className="text-sm text-muted">
                        {COMMUNITY_ROLE_LABELS[invitation.role]}, sent <RelativeTime date={invitation.created_at} />
                        {invitation.is_expired && <span className="ml-2 font-bold text-danger-text">Expired</span>}
                      </span>
                      {invitation.access_groups.length > 0 && (
                        <span className="text-sm break-words text-muted">
                          {INVITATION_WORDS.pendingAccessGroups(invitation.access_groups.map((group) => group.name).join(', '))}
                        </span>
                      )}
                      <MemberTagList tags={invitation.member_tags} className="mt-1" />
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
            {invitationsNext !== null && (
              <LoadMoreButton className="mt-3" loading={loadingInvitations} onClick={() => void loadMoreInvitations()}>
                {INVITATION_WORDS.loadMore}
              </LoadMoreButton>
            )}
            <p role="status" aria-live="polite" className="sr-only">
              {invitationsStatus}
            </p>
            <p className="mt-3 text-xs text-muted">
              Invitation links work for 7 days, only for an account with the invited email address. Invite again to send a new link.
            </p>
          </CardBody>
        </Card>
      )}

      {permissions.canRemoveCommunityMembers && joinBlocks.length > 0 && (
        <Card aria-labelledby="join-blocks-title">
          <CardHeader title={<span id="join-blocks-title">Removed members</span>} icon={<FaUserLock aria-hidden="true" />} />
          <CardBody>
            <ul className="flex flex-col divide-y divide-border" aria-label="Removed members">
              {joinBlocks.map((block) => (
                <li key={block.cuid} className="flex flex-wrap items-center gap-3 py-3">
                  <Avatar name={block.name} imageUrl={block.avatar_url} size="sm" />
                  <div className="flex min-w-0 flex-1 flex-col">
                    <span className="truncate font-bold">{block.name}</span>
                    <span className="text-sm text-muted">
                      @{block.username}, removed <RelativeTime date={block.removed_at} />
                    </span>
                  </div>
                  <Button
                    variant="secondary"
                    size="sm"
                    aria-label={`May join again: ${block.name}`}
                    onClick={() => void liftJoinBlock(block)}
                  >
                    May join again
                  </Button>
                </li>
              ))}
            </ul>
            <p className="mt-3 text-xs text-muted">
              These people cannot join the community by themselves, even when it is open. An invitation that they accept lets them in too.
            </p>
          </CardBody>
        </Card>
      )}

      {permissions.canAssignMemberTags === true && (
        <MemberTagsDialog communityCuid={communityCuid} member={taggedMember} onClose={() => setTaggedMember(null)} onChange={changeTags} />
      )}

      <Dialog
        open={pendingRemoval !== null}
        onClose={() => setPendingRemoval(null)}
        size="sm"
        title={`Remove ${pendingRemoval?.name ?? ''}?`}
        description="They lose access to the community. What they wrote stays. They cannot join again by themselves, even when the community is open: you can invite them again, or let them join again."
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
