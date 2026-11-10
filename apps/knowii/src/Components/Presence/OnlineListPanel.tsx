import { useState } from 'react';
import { Link } from '@inertiajs/react';
import type { PresenceMember } from '@knowii/common';
import { MEMBER_URL } from '@knowii/common';
import { Avatar, Drawer, TextInput } from '@/Components/ui';

export interface OnlineListPanelProps {
  open: boolean;
  onClose: () => void;
  // "Online now" or "Here now"
  title: string;
  communitySlug: string;
  members: PresenceMember[];
}

// Above this many members, a field filters the list
const SEARCH_FROM = 20;

/**
 * The full list of the members who are online (#1299, follow-up of #1187), in a side panel: avatar, name, username,
 * link to the member page, by name. Escape closes it and the focus goes back to the button that opened it.
 */
export function OnlineListPanel({ open, onClose, title, communitySlug, members }: OnlineListPanelProps) {
  const [query, setQuery] = useState('');
  const needle = query.trim().toLowerCase();
  const sorted = [...members].sort((a, b) => a.name.localeCompare(b.name));
  const shown = needle === '' ? sorted : sorted.filter((member) => `${member.name} ${member.username}`.toLowerCase().includes(needle));

  return (
    <Drawer open={open} onClose={onClose} side="right" closeLabel="Close the list" title={`${title} (${members.length})`}>
      <div className="flex flex-col gap-3">
        {members.length > SEARCH_FROM && (
          <div>
            <label htmlFor="online-list-search" className="sr-only">
              Find a member
            </label>
            <TextInput
              id="online-list-search"
              type="search"
              placeholder="Find a member"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              data-autofocus
            />
          </div>
        )}
        {shown.length === 0 ? (
          <p className="text-sm text-muted">{members.length === 0 ? 'Nobody is online.' : 'No member matches.'}</p>
        ) : (
          <ul aria-label={title} className="flex flex-col gap-1">
            {shown.map((member) => (
              <li key={member.cuid}>
                <Link
                  href={route(MEMBER_URL, { communitySlug, username: member.username })}
                  className="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                  <span className="relative inline-flex">
                    <Avatar name={member.name} imageUrl={member.avatar_url} size="sm" />
                    <span aria-hidden="true" className="absolute right-0 bottom-0 size-2.5 rounded-full bg-success ring-2 ring-surface" />
                  </span>
                  <span className="flex min-w-0 flex-col">
                    <span className="truncate font-bold">{member.name}</span>
                    <span className="truncate text-xs text-muted">@{member.username}</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Drawer>
  );
}
