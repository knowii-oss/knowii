import type { ChatMessage, ReactionTarget } from '@knowii/common';
import { FaComments, FaPen, FaTrash } from 'react-icons/fa6';
import { ReactionBar } from '@/Components/Reactions/ReactionBar';
import { Avatar, Button, MarkdownContent, RelativeTime } from '@/Components/ui';
import { ChatComposer } from './ChatComposer';

export interface ChatMessageItemProps {
  message: ChatMessage;
  communityCuid: string;
  // The reaction endpoint of a message: in a chat space or in a conversation (#1186)
  reactionTargetFor: (messageCuid: string) => ReactionTarget;
  currentUserCuid: string | null;
  canReact: boolean;
  canModerate: boolean;
  editing: boolean;
  onEdit: (message: ChatMessage) => void;
  onCancelEdit: () => void;
  onSave: (message: ChatMessage, body: string) => Promise<boolean>;
  onDelete: (message: ChatMessage) => void;
  /**
   * Open the thread of the message. Absent for the replies (one level).
   */
  onOpenThread?: ((message: ChatMessage) => void) | undefined;
}

/**
 * A message of a chat (#1185): author, time, text, reactions, and the actions of the member (reply in thread, edit,
 * delete). A deleted message is a marker.
 */
export function ChatMessageItem(props: ChatMessageItemProps) {
  const { message, currentUserCuid } = props;
  const own = message.author !== null && message.author.cuid === currentUserCuid;

  if (message.deleted_at) {
    return (
      <article aria-label="Deleted message" className="flex flex-col gap-1 px-2 py-2 text-sm text-muted italic">
        This message was deleted.
        {props.onOpenThread && message.reply_count > 0 && <ThreadSummary {...props} />}
      </article>
    );
  }

  const authorName = message.author?.name ?? 'Deleted member';

  return (
    <article
      aria-label={`Message by ${authorName}`}
      className="group flex gap-3 rounded-lg px-2 py-2 focus-within:bg-overlay/5 hover:bg-overlay/5"
    >
      <Avatar name={authorName} imageUrl={message.author?.avatar_url ?? null} size="sm" />
      <div className="flex min-w-0 grow flex-col gap-1">
        <header className="flex flex-wrap items-baseline gap-x-2 text-sm">
          <span className="font-bold">{authorName}</span>
          <span className="text-xs text-muted">
            <RelativeTime date={message.created_at} />
            {message.edited_at && ' · edited'}
          </span>
        </header>

        {props.editing ? (
          <ChatComposer
            id={`edit-${message.cuid}`}
            communityCuid={props.communityCuid}
            label="Edit the message"
            initialValue={message.body ?? ''}
            submitLabel="Save"
            autoFocus
            onSend={(body) => props.onSave(message, body)}
            onCancel={props.onCancelEdit}
          />
        ) : (
          <MarkdownContent html={message.body_html ?? ''} className="break-words prose-p:my-1" />
        )}

        <div className="flex flex-wrap items-center gap-2">
          <ReactionBar
            target={props.reactionTargetFor(message.cuid)}
            initialReactions={message.reactions}
            canReact={props.canReact}
            itemLabel={`the message by ${authorName}`}
          />
          {props.onOpenThread && (
            <Button variant="ghost" size="sm" onClick={() => props.onOpenThread?.(message)}>
              <FaComments aria-hidden="true" />
              Reply in thread
            </Button>
          )}
          {own && !props.editing && (
            <Button variant="ghost" size="sm" onClick={() => props.onEdit(message)} aria-label={`Edit the message by ${authorName}`}>
              <FaPen aria-hidden="true" />
              Edit
            </Button>
          )}
          {(own || props.canModerate) && !props.editing && (
            <Button variant="ghost" size="sm" onClick={() => props.onDelete(message)} aria-label={`Delete the message by ${authorName}`}>
              <FaTrash aria-hidden="true" />
              Delete
            </Button>
          )}
        </div>

        {props.onOpenThread && message.reply_count > 0 && <ThreadSummary {...props} />}
      </div>
    </article>
  );
}

function ThreadSummary({ message, onOpenThread }: ChatMessageItemProps) {
  return (
    <button
      type="button"
      onClick={() => onOpenThread?.(message)}
      className="touch-target w-fit cursor-pointer gap-1 rounded text-sm font-bold text-secondary-text hover:underline"
    >
      {message.reply_count === 1 ? '1 reply' : `${message.reply_count} replies`}
      {message.last_reply_at && (
        <span className="font-normal text-muted">
          {' '}
          · last <RelativeTime date={message.last_reply_at} />
        </span>
      )}
    </button>
  );
}
