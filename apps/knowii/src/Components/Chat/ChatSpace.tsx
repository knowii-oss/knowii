import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ChatMessage, Community, Space, SpaceContentPayload } from '@knowii/common';
import { knowiiApiClient, spacePresenceChannel, useAppData, usePresence, useSocket } from '@knowii/common';
import { PresenceAvatars } from '@/Components/Presence/PresenceAvatars';
import { TypingIndicator } from '@/Components/Presence/TypingIndicator';
import { Button, Dialog, EmptyState } from '@/Components/ui';
import { ChatComposer } from './ChatComposer';
import { ChatMessageItem } from './ChatMessageItem';
import { ThreadPanel } from './ThreadPanel';

export interface ChatSpaceProps {
  community: Community;
  space: Space;
  // WARNING: aligned with SpaceController.php (chat spaces)
  initialMessages: ChatMessage[];
  hasMore: boolean;
  // The first message after the last one the member read: the "new messages" line goes before it
  firstUnreadCuid: string | null;
  currentUserCuid: string | null;
  canWrite: boolean;
  canReact: boolean;
  canModerate: boolean;
  /**
   * Presence (#1187): the current user is a member. False for visitors of a public space: no presence for them
   */
  presenceEnabled?: boolean;
  // The current member shows their online status (they join the presence channel)
  showsOnlineStatus?: boolean;
}

const sortByTime = (messages: ChatMessage[]) =>
  [...messages].sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());

/**
 * A chat space (#1185): messages in order, newest at the bottom, older ones on demand, threads in a side panel, live
 * through the space channel (identifiers only, the message loads through the API), and unread markers. Members see who
 * is in the space and who is typing (#1187).
 */
export function ChatSpace(props: ChatSpaceProps) {
  const { community, space, currentUserCuid } = props;
  const { showToast } = useAppData();
  const [messages, setMessages] = useState<ChatMessage[]>(props.initialMessages);
  const [hasMore, setHasMore] = useState(props.hasMore);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [editingCuid, setEditingCuid] = useState<string | null>(null);
  const [toDelete, setToDelete] = useState<ChatMessage | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [threadOf, setThreadOf] = useState<ChatMessage | null>(null);
  const [replies, setReplies] = useState<ChatMessage[] | null>(null);
  const logRef = useRef<HTMLOListElement>(null);
  const stickToBottom = useRef(true);

  const fetchMembers = useCallback(
    () => knowiiApiClient.communities.spaces.onlineMembers(community.cuid, space.cuid),
    [community.cuid, space.cuid],
  );
  const presence = usePresence({
    channel: props.presenceEnabled && currentUserCuid ? spacePresenceChannel(community.cuid, space.cuid) : null,
    visible: props.showsOnlineStatus !== false,
    fetchMembers,
    currentUserCuid,
  });
  const { notifyTyping, stopTyping } = presence;
  const onTypingChange = useCallback(
    (scope: string, typing: boolean) => (typing ? notifyTyping(scope) : stopTyping(scope)),
    [notifyTyping, stopTyping],
  );

  // The newest message at the bottom: scroll there on load and when a message arrives, unless the member reads older ones
  useEffect(() => {
    const log = logRef.current;
    if (log && stickToBottom.current) {
      log.scrollTop = log.scrollHeight;
    }
  }, [messages]);

  // Remember what the member read (unread markers)
  const lastCuid = messages.at(-1)?.cuid;
  useEffect(() => {
    if (lastCuid && currentUserCuid) {
      void knowiiApiClient.communities.chat.markAsRead(community.cuid, space.cuid, lastCuid);
    }
  }, [lastCuid, currentUserCuid, community.cuid, space.cuid]);

  const upsert = useCallback((list: ChatMessage[], message: ChatMessage) => {
    const exists = list.some((item) => item.cuid === message.cuid);
    return sortByTime(exists ? list.map((item) => (item.cuid === message.cuid ? message : item)) : [...list, message]);
  }, []);

  const applyMessage = useCallback(
    (message: ChatMessage) => {
      if (message.parent_cuid) {
        setReplies((current) => (current && threadOf?.cuid === message.parent_cuid ? upsert(current, message) : current));
      } else {
        setMessages((current) => upsert(current, message));
        setThreadOf((current) => (current?.cuid === message.cuid ? message : current));
      }
    },
    [threadOf?.cuid, upsert],
  );

  const reload = useCallback(
    async (cuid: string) => {
      const result = await knowiiApiClient.communities.chat.get(community.cuid, space.cuid, cuid);
      if (result.ok) {
        applyMessage(result.data);
      }
    },
    [applyMessage, community.cuid, space.cuid],
  );

  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );
  useSocket({
    channel,
    event: ['space.message.created', 'space.message.updated', 'space.message.deleted', 'space.reactions.changed'],
    callback: (_event, payload) => {
      const data = payload as SpaceContentPayload;
      if (!data.message_cuid) {
        return;
      }
      void reload(data.message_cuid);
      // A reply changes the count of its thread
      if (data.parent_cuid) {
        void reload(data.parent_cuid);
      }
    },
  });

  const loadOlder = async () => {
    const oldest = messages[0];
    if (!oldest) {
      return;
    }
    setLoadingOlder(true);
    stickToBottom.current = false;
    const log = logRef.current;
    const before = log ? log.scrollHeight - log.scrollTop : 0;
    const result = await knowiiApiClient.communities.chat.list(community.cuid, space.cuid, oldest.cuid);
    setLoadingOlder(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setMessages((current) =>
      sortByTime([...result.data.messages.filter((item) => !current.some((shown) => shown.cuid === item.cuid)), ...current]),
    );
    setHasMore(result.data.hasMore);
    // Keep the messages that the member was reading in place
    requestAnimationFrame(() => {
      if (log) {
        log.scrollTop = log.scrollHeight - before;
      }
    });
  };

  const send = async (body: string, parentCuid?: string) => {
    stickToBottom.current = true;
    const result = await knowiiApiClient.communities.chat.create(community.cuid, space.cuid, {
      body,
      ...(parentCuid ? { parent_cuid: parentCuid } : {}),
    });
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return false;
    }
    applyMessage(result.data);
    if (parentCuid) {
      void reload(parentCuid);
    }
    return true;
  };

  const save = async (message: ChatMessage, body: string) => {
    const result = await knowiiApiClient.communities.chat.update(community.cuid, space.cuid, message.cuid, { body });
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return false;
    }
    applyMessage(result.data);
    stopEditing();
    return true;
  };

  // Back to the message field after an edit (keyboard flow of #1185), once the edit field is gone
  const stopEditing = () => setEditingCuid(null);
  const wasEditing = useRef(false);
  useEffect(() => {
    if (editingCuid === null && wasEditing.current) {
      document.getElementById(threadOf ? 'thread-composer' : 'chat-composer')?.focus();
    }
    wasEditing.current = editingCuid !== null;
  }, [editingCuid, threadOf]);

  const confirmDelete = async () => {
    if (!toDelete) {
      return;
    }
    setDeleting(true);
    const result = await knowiiApiClient.communities.chat.delete(community.cuid, space.cuid, toDelete.cuid);
    setDeleting(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    const deleted = toDelete;
    setToDelete(null);
    void reload(deleted.cuid);
    if (deleted.parent_cuid) {
      void reload(deleted.parent_cuid);
    }
  };

  const openThread = async (message: ChatMessage) => {
    setThreadOf(message);
    setReplies(null);
    const result = await knowiiApiClient.communities.chat.thread(community.cuid, space.cuid, message.cuid);
    if (result.ok) {
      setThreadOf(result.data.message);
      setReplies(result.data.replies);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  // Up arrow in the empty field: edit the last own message
  const editLast = () => {
    const last = [...messages].reverse().find((message) => !message.deleted_at && message.author?.cuid === currentUserCuid);
    if (last) {
      setEditingCuid(last.cuid);
    }
  };

  const item = {
    communityCuid: community.cuid,
    spaceCuid: space.cuid,
    currentUserCuid,
    canReact: props.canReact,
    canModerate: props.canModerate,
    onEdit: (message: ChatMessage) => setEditingCuid(message.cuid),
    onCancelEdit: stopEditing,
    onSave: save,
    onDelete: (message: ChatMessage) => setToDelete(message),
  };

  return (
    <section aria-label={`Chat of ${space.name}`} className="flex h-[70vh] min-h-96 flex-col gap-3">
      {props.presenceEnabled && presence.members.length > 0 && (
        <div className="flex flex-wrap items-center gap-2">
          <span id="chat-here-now" className="text-xs font-bold text-muted">
            Here now ({presence.members.length})
          </span>
          <PresenceAvatars communitySlug={community.slug} members={presence.members} max={8} labelledBy="chat-here-now" />
        </div>
      )}
      {hasMore && (
        <div className="flex justify-center">
          <Button variant="secondary" size="sm" onClick={() => void loadOlder()} loading={loadingOlder}>
            Load older messages
          </Button>
        </div>
      )}

      {messages.length === 0 ? (
        <EmptyState
          icon="💬"
          title="No messages yet"
          description={props.canWrite ? 'Say hello to the community.' : 'Messages will show here.'}
        />
      ) : (
        <ol
          ref={logRef}
          role="log"
          aria-label="Messages"
          aria-live="polite"
          tabIndex={0}
          onScroll={(event) => {
            const log = event.currentTarget;
            stickToBottom.current = log.scrollHeight - log.scrollTop - log.clientHeight < 80;
          }}
          className="flex grow flex-col overflow-y-auto rounded-lg border border-border bg-well p-2"
        >
          {messages.map((message) => (
            <li key={message.cuid}>
              {message.cuid === props.firstUnreadCuid && (
                <p
                  role="separator"
                  aria-label="New messages"
                  className="my-2 flex items-center gap-2 text-xs font-bold text-secondary-text"
                >
                  <span aria-hidden="true" className="h-px grow bg-secondary" />
                  New messages
                  <span aria-hidden="true" className="h-px grow bg-secondary" />
                </p>
              )}
              <ChatMessageItem
                {...item}
                message={message}
                editing={editingCuid === message.cuid}
                onOpenThread={(m) => void openThread(m)}
              />
            </li>
          ))}
        </ol>
      )}

      <TypingIndicator typists={presence.typists('')} />
      {props.canWrite && (
        <ChatComposer
          id="chat-composer"
          communityCuid={community.cuid}
          label={`Message ${space.name}`}
          onSend={(body) => send(body)}
          onEditLast={editLast}
          onTypingChange={(typing) => onTypingChange('', typing)}
        />
      )}

      <ThreadPanel
        open={threadOf !== null}
        onClose={() => {
          setThreadOf(null);
          setReplies(null);
        }}
        communityCuid={community.cuid}
        parent={threadOf}
        replies={replies}
        canWrite={props.canWrite}
        onReply={(body) => (threadOf ? send(body, threadOf.cuid) : Promise.resolve(false))}
        item={item}
        editingCuid={editingCuid}
        typists={threadOf ? presence.typists(threadOf.cuid) : []}
        onTypingChange={(typing) => threadOf && onTypingChange(threadOf.cuid, typing)}
      />

      <Dialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        size="sm"
        title="Delete this message?"
        description="The text of the message is deleted. A marker stays in its place. This cannot be undone."
        footer={
          <>
            <Button variant="secondary" onClick={() => setToDelete(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={() => void confirmDelete()} loading={deleting}>
              Delete
            </Button>
          </>
        }
      />
    </section>
  );
}
