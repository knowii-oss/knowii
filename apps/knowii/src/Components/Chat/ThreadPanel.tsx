import { useEffect, useRef } from 'react';
import type { ChatMessage, PresenceMember } from '@knowii/common';
import { TypingIndicator } from '@/Components/Presence/TypingIndicator';
import { Drawer, Spinner } from '@/Components/ui';
import { ChatComposer } from './ChatComposer';
import type { ChatMessageItemProps } from './ChatMessageItem';
import { ChatMessageItem } from './ChatMessageItem';

export interface ThreadPanelProps {
  open: boolean;
  onClose: () => void;
  communityCuid: string;
  parent: ChatMessage | null;
  replies: ChatMessage[] | null;
  canWrite: boolean;
  onReply: (body: string) => Promise<boolean>;
  /**
   * The shared props of the message items (edit, delete, reactions)
   */
  item: Omit<ChatMessageItemProps, 'message' | 'editing' | 'onOpenThread'>;
  editingCuid: string | null;
  // Typing indicators of the thread (#1187)
  typists?: PresenceMember[];
  onTypingChange?: ((typing: boolean) => void) | undefined;
}

/**
 * The thread of a message in a side panel (#1185): the first message, its replies, and a field to reply. Escape closes
 * it (Drawer). One level: replies have no thread.
 */
export function ThreadPanel({
  open,
  onClose,
  communityCuid,
  parent,
  replies,
  canWrite,
  onReply,
  item,
  editingCuid,
  typists = [],
  onTypingChange,
}: ThreadPanelProps) {
  const repliesRef = useRef<HTMLOListElement>(null);
  // The newest reply at the bottom, in view
  useEffect(() => {
    const list = repliesRef.current;
    if (list) {
      list.scrollTop = list.scrollHeight;
    }
  }, [replies?.length]);

  return (
    <Drawer open={open} onClose={onClose} side="right" title="Thread" closeLabel="Close the thread" className="sm:max-w-md">
      {parent === null ? (
        <Spinner label="Loading the thread" />
      ) : (
        <div className="flex h-full flex-col gap-3">
          <ChatMessageItem {...item} message={parent} editing={editingCuid === parent.cuid} />
          <p className="border-b border-border pb-2 text-xs text-muted">
            {parent.reply_count === 1 ? '1 reply' : `${parent.reply_count} replies`}
          </p>
          <ol ref={repliesRef} role="log" aria-label="Replies" aria-live="polite" className="flex grow flex-col gap-1 overflow-y-auto">
            {replies === null ? (
              <li>
                <Spinner label="Loading the replies" />
              </li>
            ) : (
              replies.map((reply) => (
                <li key={reply.cuid}>
                  <ChatMessageItem {...item} message={reply} editing={editingCuid === reply.cuid} />
                </li>
              ))
            )}
          </ol>
          <TypingIndicator typists={typists} />
          {canWrite && !parent.deleted_at && (
            <ChatComposer
              id="thread-composer"
              communityCuid={communityCuid}
              label="Reply in the thread"
              onSend={onReply}
              autoFocus
              onTypingChange={onTypingChange}
            />
          )}
        </div>
      )}
    </Drawer>
  );
}
