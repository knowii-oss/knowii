import { useCallback, useEffect, useRef, useState } from 'react';
import type { CommunityPermissions, InviteLink, InviteLinkStatus, MemberTag } from '@knowii/common';
import { formatEventStart, knowiiApiClient, useAppData } from '@knowii/common';
import { FaCopy, FaLink, FaPen, FaPlus, FaTrash, FaTriangleExclamation, FaUsers } from 'react-icons/fa6';
import type { BadgeVariant } from '@/Components/ui';
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  Dialog,
  EmptyState,
  SaveStatus,
  Switch,
  useAccountTimeZone,
  useInstantSave,
} from '@/Components/ui';
import { MemberTagList } from '@/Components/Members/MemberTagList';
import type { InviteLinkGroupChoice } from '@/Components/InviteLinks/InviteLinkFormDialog';
import { InviteLinkFormDialog } from '@/Components/InviteLinks/InviteLinkFormDialog';
import { InviteLinkUsesDialog } from '@/Components/InviteLinks/InviteLinkUsesDialog';
import { INVITE_LINK_WORDS } from '@/Components/InviteLinks/invite-link-words';
import { useCopyInviteLink } from '@/Components/InviteLinks/use-copy-invite-link';

export interface InviteLinksManagerProps {
  communityCuid: string;
  permissions: Pick<CommunityPermissions, 'canManageAccessGroupMembers' | 'canAssignMemberTags'>;
  /**
   * Changes when the access groups or the tags changed elsewhere on the page: the choices of the form load again
   */
  refreshKey?: number;
}

const STATUS_VARIANTS: Record<InviteLinkStatus, BadgeVariant> = { active: 'success', off: 'neutral', ended: 'warning', full: 'warning' };

/**
 * "3 joined", or "3 of 100 joined" when the link has a maximum
 */
export function joinedText(link: Pick<InviteLink, 'uses_count' | 'max_uses'>): string {
  return link.max_uses === null ? INVITE_LINK_WORDS.joined(link.uses_count) : INVITE_LINK_WORDS.joinedOfMax(link.uses_count, link.max_uses);
}

interface InviteLinkRowProps {
  communityCuid: string;
  link: InviteLink;
  onChanged: (link: InviteLink) => void;
  onEdit: (link: InviteLink) => void;
  onUses: (link: InviteLink) => void;
  onDelete: (link: InviteLink) => void;
}

/**
 * One link of the list: its name, its state in words, its count, its end, what it gives, and its actions. The switch
 * saves at once; on an error it puts the old state back and says why (role="alert").
 */
function InviteLinkRow({ communityCuid, link, onChanged, onEdit, onUses, onDelete }: InviteLinkRowProps) {
  const zone = useAccountTimeZone();
  const copy = useCopyInviteLink();
  const setting = useInstantSave(link.active);

  const toggle = async (value: boolean) => {
    const result = await setting.save(value, () =>
      knowiiApiClient.communities.inviteLinks.update(communityCuid, link.cuid, { active: value }),
    );
    if (result?.ok) {
      onChanged(result.data);
    }
  };

  const state = setting.saveState;
  const ruledGroups = link.access_groups.filter((group) => group.fed_by_rule);
  const givenGroups = link.access_groups.filter((group) => !group.fed_by_rule);

  return (
    <li className="flex flex-col gap-3 py-4 @3xl:flex-row @3xl:items-start @3xl:justify-between">
      <div className="flex min-w-0 flex-1 flex-col gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-bold wrap-anywhere">{link.name}</span>
          <Badge variant={STATUS_VARIANTS[link.status]}>{INVITE_LINK_WORDS.status[link.status]}</Badge>
        </div>
        <p className="flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted">
          <span>{joinedText(link)}</span>
          {link.expires_at !== null && (
            <span>
              {INVITE_LINK_WORDS.ends}{' '}
              <time dateTime={new Date(link.expires_at).toISOString()}>{formatEventStart(link.expires_at, zone)}</time>
            </span>
          )}
        </p>
        <div className="flex flex-wrap items-center gap-2 text-sm">
          {givenGroups.length === 0 && link.member_tags.length === 0 ? (
            <span className="text-muted">{INVITE_LINK_WORDS.givesNothing}</span>
          ) : (
            <>
              <span className="font-bold">{INVITE_LINK_WORDS.gives}</span>
              {givenGroups.length > 0 && <span className="wrap-anywhere">{givenGroups.map((group) => group.name).join(', ')}</span>}
              <MemberTagList tags={link.member_tags} />
            </>
          )}
        </div>
        {ruledGroups.map((group) => (
          <p key={group.cuid} className="flex items-start gap-1.5 text-sm">
            <FaTriangleExclamation aria-hidden="true" className="mt-0.5 shrink-0 text-warning" />
            <span className="wrap-anywhere">{INVITE_LINK_WORDS.fedByRule(group.name)}</span>
          </p>
        ))}
      </div>

      <div className="flex flex-col gap-2 @3xl:items-end">
        <Switch
          checked={setting.value}
          busy={setting.busy}
          onChange={(value) => void toggle(value)}
          label={
            <>
              <span className="sr-only">{INVITE_LINK_WORDS.onHidden(link.name)}</span> {INVITE_LINK_WORDS.on}
            </>
          }
          status={
            state.status === 'error' ? (
              <p role="alert" className="mt-1 text-sm text-danger-text">
                {state.message}
              </p>
            ) : (
              <SaveStatus state={state} savedText={setting.value ? INVITE_LINK_WORDS.savedOn : INVITE_LINK_WORDS.savedOff} />
            )
          }
        />
        <div className="flex flex-wrap gap-1">
          <Button
            variant="secondary"
            size="sm"
            leftIcon={<FaCopy aria-hidden="true" />}
            aria-label={INVITE_LINK_WORDS.copyLinkOf(link.name)}
            onClick={() => void copy(link.url)}
          >
            {INVITE_LINK_WORDS.copyLink}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            leftIcon={<FaPen aria-hidden="true" />}
            aria-label={INVITE_LINK_WORDS.editOf(link.name)}
            onClick={() => onEdit(link)}
          >
            {INVITE_LINK_WORDS.edit}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            leftIcon={<FaUsers aria-hidden="true" />}
            aria-label={INVITE_LINK_WORDS.whoJoinedOf(link.name)}
            aria-haspopup="dialog"
            onClick={() => onUses(link)}
          >
            {INVITE_LINK_WORDS.whoJoined}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            leftIcon={<FaTrash aria-hidden="true" />}
            aria-label={INVITE_LINK_WORDS.deleteOf(link.name)}
            aria-haspopup="dialog"
            onClick={() => onDelete(link)}
          >
            {INVITE_LINK_WORDS.delete}
          </Button>
        </div>
      </div>
    </li>
  );
}

/**
 * The section "Invite links" of the settings (#1391, ADR 0062), for the owner and the admins of a shared community: the
 * links, newest first, each with its state in words, how many people joined through it, its end, what it gives, and its
 * actions (copy, on and off, edit, who joined, delete).
 */
export function InviteLinksManager({ communityCuid, permissions, refreshKey = 0 }: InviteLinksManagerProps) {
  const { showToast } = useAppData();
  const [links, setLinks] = useState<InviteLink[]>([]);
  const [loading, setLoading] = useState(true);
  const [choices, setChoices] = useState<{ accessGroups: InviteLinkGroupChoice[]; memberTags: MemberTag[] }>({
    accessGroups: [],
    memberTags: [],
  });
  // The form of a new link (link null) or of a change, and "Who joined": a new key at each opening, and the dialog stays
  // mounted while it closes
  const [form, setForm] = useState<{ link: InviteLink | null; key: number; open: boolean } | null>(null);
  const [uses, setUses] = useState<{ link: InviteLink; key: number; open: boolean } | null>(null);
  const openForm = (link: InviteLink | null) => setForm((current) => ({ link, key: (current?.key ?? 0) + 1, open: true }));
  const openUses = (link: InviteLink) => setUses((current) => ({ link, key: (current?.key ?? 0) + 1, open: true }));
  // The question of a delete keeps its link while it closes, so that its title keeps the name
  const [deleting, setDeleting] = useState<{ link: InviteLink; open: boolean } | null>(null);
  const closeDelete = () => setDeleting((current) => (current ? { ...current, open: false } : null));
  // After a delete the row and its button go: the focus goes to "New invite link" once the question closed
  const deleted = useRef(false);
  const newLinkButton = useRef<HTMLButtonElement>(null);
  const [busy, setBusy] = useState(false);
  const canGiveAccessGroups = permissions.canManageAccessGroupMembers;
  const canGiveMemberTags = permissions.canAssignMemberTags;

  const showError = useCallback((message: string) => showToast({ severity: 'error', summary: message }), [showToast]);

  useEffect(() => {
    let active = true;
    void knowiiApiClient.communities.inviteLinks.list(communityCuid).then((result) => {
      if (!active) {
        return;
      }
      setLoading(false);
      if (!result.ok) {
        showError(result.message);
        return;
      }
      setLinks(result.data);
    });
    return () => {
      active = false;
    };
  }, [communityCuid, showError]);

  useEffect(() => {
    let active = true;
    void Promise.all([
      canGiveAccessGroups ? knowiiApiClient.communities.accessGroups.list(communityCuid) : null,
      canGiveMemberTags ? knowiiApiClient.communities.memberTags.list(communityCuid) : null,
    ]).then(([groups, tags]) => {
      if (!active) {
        return;
      }
      setChoices({
        // A group with entitlement keys is fed by a rule: a link cannot give it (the server checks it too)
        accessGroups: groups?.ok
          ? groups.data.map((group) => ({ cuid: group.cuid, name: group.name, fedByRule: (group.entitlement_keys ?? []).length > 0 }))
          : [],
        memberTags: tags?.ok ? tags.data : [],
      });
    });
    return () => {
      active = false;
    };
  }, [communityCuid, canGiveAccessGroups, canGiveMemberTags, refreshKey]);

  const replace = (link: InviteLink) => {
    setLinks((current) =>
      current.some((item) => item.cuid === link.cuid) ? current.map((item) => (item.cuid === link.cuid ? link : item)) : [link, ...current],
    );
  };

  const confirmDelete = async () => {
    const target = deleting?.link;
    if (!target) {
      return;
    }
    setBusy(true);
    const result = await knowiiApiClient.communities.inviteLinks.remove(communityCuid, target.cuid);
    setBusy(false);
    if (!result.ok) {
      showError(result.message);
      return;
    }
    showToast({ severity: 'success', summary: INVITE_LINK_WORDS.deleted(target.name) });
    setLinks((current) => current.filter((link) => link.cuid !== target.cuid));
    deleted.current = true;
    closeDelete();
  };

  return (
    <Card as="section" aria-labelledby="invite-links-title">
      <CardHeader
        title={<span id="invite-links-title">{INVITE_LINK_WORDS.sectionTitle}</span>}
        icon={<FaLink aria-hidden="true" />}
        actions={
          <Button ref={newLinkButton} leftIcon={<FaPlus aria-hidden="true" />} aria-haspopup="dialog" onClick={() => openForm(null)}>
            {INVITE_LINK_WORDS.newLink}
          </Button>
        }
      />
      <CardBody className="flex flex-col gap-4 @container">
        <p className="text-sm text-muted">{INVITE_LINK_WORDS.sectionDescription}</p>

        {!loading && links.length === 0 ? (
          <EmptyState
            icon={<FaLink aria-hidden="true" />}
            title={INVITE_LINK_WORDS.emptyTitle}
            description={INVITE_LINK_WORDS.emptyDescription}
          />
        ) : (
          <ul className="flex flex-col divide-y divide-border" aria-label={INVITE_LINK_WORDS.listLabel} aria-busy={loading}>
            {links.map((link) => (
              <InviteLinkRow
                key={link.cuid}
                communityCuid={communityCuid}
                link={link}
                onChanged={replace}
                onEdit={openForm}
                onUses={openUses}
                onDelete={(item) => setDeleting({ link: item, open: true })}
              />
            ))}
          </ul>
        )}
      </CardBody>

      {form && (
        <InviteLinkFormDialog
          key={form.key}
          communityCuid={communityCuid}
          open={form.open}
          link={form.link}
          accessGroups={choices.accessGroups}
          memberTags={choices.memberTags}
          canGiveAccessGroups={canGiveAccessGroups}
          canGiveMemberTags={canGiveMemberTags}
          onClose={() => setForm((current) => (current ? { ...current, open: false } : null))}
          onSaved={replace}
        />
      )}

      {uses && (
        <InviteLinkUsesDialog
          key={uses.key}
          communityCuid={communityCuid}
          link={uses.link}
          open={uses.open}
          onClose={() => setUses((current) => (current ? { ...current, open: false } : null))}
        />
      )}

      <Dialog
        open={deleting?.open ?? false}
        onClose={closeDelete}
        onAfterClose={() => {
          setDeleting(null);
          if (deleted.current) {
            deleted.current = false;
            newLinkButton.current?.focus();
          }
        }}
        size="sm"
        title={INVITE_LINK_WORDS.deleteTitle(deleting?.link.name ?? '')}
        description={INVITE_LINK_WORDS.deleteDetails(deleting?.link.uses_count ?? 0)}
        footer={
          <>
            <Button variant="secondary" onClick={closeDelete} autoFocus>
              {INVITE_LINK_WORDS.cancel}
            </Button>
            <Button variant="danger" onClick={() => void confirmDelete()} loading={busy}>
              {INVITE_LINK_WORDS.delete}
            </Button>
          </>
        }
      />
    </Card>
  );
}
