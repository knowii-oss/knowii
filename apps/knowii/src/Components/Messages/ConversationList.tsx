import { Link } from '@inertiajs/react';
import type { Conversation } from '@knowii/common';
import { CONVERSATION_URL, conversationTitle } from '@knowii/common';
import { Avatar, cn, RelativeTime } from '@/Components/ui';

export interface ConversationListProps {
  communitySlug: string;
  conversations: Conversation[];
  activeCuid: string | null;
  currentUserCuid: string;
}

/**
 * The conversations of the member, newest first (#1186): who, the newest message, when, and what they did not read.
 * A list of links: Tab moves between them.
 */
export function ConversationList({ communitySlug, conversations, activeCuid, currentUserCuid }: ConversationListProps) {
  if (conversations.length === 0) {
    return <p className="px-2 text-sm text-muted">No conversations yet.</p>;
  }

  return (
    <ul className="flex flex-col gap-1">
      {conversations.map((conversation) => {
        const active = conversation.cuid === activeCuid;
        const title = conversationTitle(conversation, currentUserCuid);
        const others = (conversation.participants ?? []).filter((participant) => participant.cuid !== currentUserCuid);
        const first = others[0];
        // The open conversation is being read: no badge on it
        const unread = active ? 0 : (conversation.unread_count ?? 0);
        const last = conversation.last_message;

        return (
          <li key={conversation.cuid}>
            <Link
              href={route(CONVERSATION_URL, { communitySlug, conversationCuid: conversation.cuid })}
              preserveScroll
              {...(active ? { 'aria-current': 'page' as const } : {})}
              className={cn(
                'flex items-start gap-3 rounded-lg px-2 py-2 transition-colors',
                'hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                active && 'bg-overlay/15',
              )}
            >
              <Avatar name={first?.name ?? title} imageUrl={first?.avatar_url ?? null} size="sm" />
              <span className="flex min-w-0 grow flex-col">
                <span className="flex items-baseline gap-2">
                  <span className={cn('min-w-0 grow truncate text-sm', unread > 0 ? 'font-extrabold' : 'font-bold')}>{title}</span>
                  {conversation.last_message_at && (
                    <span className="shrink-0 text-xs text-muted">
                      <RelativeTime date={conversation.last_message_at} />
                    </span>
                  )}
                </span>
                <span className="flex items-center gap-2">
                  <span className="min-w-0 grow truncate text-xs text-muted">
                    {!last ? '' : last.deleted ? 'This message was deleted.' : `${last.author_name ?? 'Deleted member'}: ${last.text}`}
                  </span>
                  {unread > 0 && (
                    <span className="shrink-0 rounded-full bg-secondary px-1.5 text-xs font-bold text-white">
                      {unread}
                      <span className="sr-only"> unread</span>
                    </span>
                  )}
                </span>
              </span>
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
