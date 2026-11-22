import { useEffect, useState } from 'react';
import type { InviteLink, InviteLinkUse } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { FaUserPlus } from 'react-icons/fa6';
import { Avatar, Dialog, DialogCloseButton, EmptyState, LoadMoreButton, RelativeTime } from '@/Components/ui';
import { INVITE_LINK_WORDS } from './invite-link-words';

// The entries of one page of the list
const PAGE_SIZE = 20;

export interface InviteLinkUsesDialogProps {
  communityCuid: string;
  /**
   * The link of the list. The caller gives a new key for each opening: the list loads when the dialog mounts
   */
  link: InviteLink;
  open: boolean;
  onClose: () => void;
}

/**
 * "Who joined" through an invite link (#1391, ADR 0062): the members, newest first, with their picture, their name and
 * the date of the join, 20 at a time. A member who left is not in the list (and stays in the count of the link).
 */
export function InviteLinkUsesDialog({ communityCuid, link, open, onClose }: InviteLinkUsesDialogProps) {
  const [uses, setUses] = useState<InviteLinkUse[]>([]);
  const [nextOffset, setNextOffset] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [loaded, setLoaded] = useState('');
  const linkCuid = link.cuid;

  useEffect(() => {
    let active = true;
    void knowiiApiClient.communities.inviteLinks.uses(communityCuid, linkCuid, PAGE_SIZE, 0).then((result) => {
      if (!active) {
        return;
      }
      setLoading(false);
      if (!result.ok) {
        setError(result.message);
        return;
      }
      setUses(result.data.items);
      setNextOffset(result.data.pagination.nextOffset ?? null);
    });
    return () => {
      active = false;
    };
  }, [communityCuid, linkCuid]);

  const loadMore = async () => {
    if (nextOffset === null || loading) {
      return;
    }
    setLoading(true);
    const result = await knowiiApiClient.communities.inviteLinks.uses(communityCuid, linkCuid, PAGE_SIZE, nextOffset);
    setLoading(false);
    if (!result.ok) {
      setError(result.message);
      return;
    }
    setUses((current) => [...current, ...result.data.items]);
    setNextOffset(result.data.pagination.nextOffset ?? null);
    setLoaded(INVITE_LINK_WORDS.loaded(result.data.items.length));
  };

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={INVITE_LINK_WORDS.whoJoinedTitle(link.name)}
      footer={<DialogCloseButton variant="primary">{INVITE_LINK_WORDS.close}</DialogCloseButton>}
    >
      <div className="flex flex-col gap-4">
        {error && (
          <p role="alert" className="text-sm text-danger-text">
            {error}
          </p>
        )}
        {!loading && error === null && uses.length === 0 ? (
          <EmptyState icon={<FaUserPlus aria-hidden="true" />} title={INVITE_LINK_WORDS.whoJoinedEmpty} />
        ) : (
          <ul className="flex flex-col divide-y divide-border" aria-label={INVITE_LINK_WORDS.whoJoined} aria-busy={loading}>
            {uses.map((use) => (
              <li key={`${use.member.cuid}-${use.joined_at}`} className="flex items-center gap-3 py-2">
                <Avatar name={use.member.name} imageUrl={use.member.avatar_url ?? null} size="sm" />
                <span className="min-w-0 flex-1 font-bold wrap-anywhere">{use.member.name}</span>
                <RelativeTime date={use.joined_at} className="shrink-0 text-sm text-muted" />
              </li>
            ))}
          </ul>
        )}
        {nextOffset !== null && (
          <LoadMoreButton onClick={() => void loadMore()} loading={loading}>
            {INVITE_LINK_WORDS.loadMore}
          </LoadMoreButton>
        )}
        <p role="status" aria-live="polite" className="sr-only">
          {loaded}
        </p>
      </div>
    </Dialog>
  );
}
