import { useState } from 'react';
import type { Comment } from '@knowii/common';
import { knowiiApiClient, useAppData } from '@knowii/common';
import { FaPen, FaReply, FaTrash } from 'react-icons/fa6';
import { Button, Dialog, MarkdownContent, RelativeTime } from '@/Components/ui';
import { CommentForm } from './CommentForm';
import { ReactionBar } from '@/Components/Reactions/ReactionBar';

export interface CommentSectionProps {
  communityCuid: string;
  spaceCuid: string;
  postCuid: string;
  initialComments: Comment[];
  currentUserCuid: string | null;
  canComment: boolean;
  canModerate: boolean;
  /**
   * Called when the number of comments (top-level and replies) changes
   */
  onCountChange?: (count: number) => void;
}

const countAll = (comments: Comment[]) => comments.reduce((total, comment) => total + 1 + (comment.replies?.length ?? 0), 0);

/**
 * Comments of a post, oldest first, with one level of replies. Authors edit their comments; authors and moderators
 * delete them (a deleted comment takes its replies with it).
 */
export function CommentSection(props: CommentSectionProps) {
  const { communityCuid, spaceCuid, postCuid, currentUserCuid, canComment, canModerate, onCountChange } = props;
  const { showToast } = useAppData();
  const [comments, setComments] = useState<Comment[]>(props.initialComments);
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [editing, setEditing] = useState<string | null>(null);
  const [pendingDeletion, setPendingDeletion] = useState<Comment | null>(null);
  const [deleting, setDeleting] = useState(false);

  const update = (next: Comment[]) => {
    setComments(next);
    onCountChange?.(countAll(next));
  };

  const create = async (body: string, parentCuid: string | null): Promise<string | null> => {
    const result = await knowiiApiClient.communities.comments.create(communityCuid, spaceCuid, postCuid, { body, parent_cuid: parentCuid });
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return result.errors['body']?.[0] ?? result.message;
    }
    const created = result.data;
    update(
      created.parent_cuid === null
        ? [...comments, { ...created, replies: [] }]
        : comments.map((comment) =>
            comment.cuid === created.parent_cuid ? { ...comment, replies: [...(comment.replies ?? []), created] } : comment,
          ),
    );
    setReplyingTo(null);
    return null;
  };

  const save = async (target: Comment, body: string): Promise<string | null> => {
    const result = await knowiiApiClient.communities.comments.update(communityCuid, spaceCuid, postCuid, target.cuid, { body });
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return result.errors['body']?.[0] ?? result.message;
    }
    const replace = (comment: Comment): Comment =>
      comment.cuid === target.cuid
        ? { ...comment, body: result.data.body, body_html: result.data.body_html, edited_at: result.data.edited_at }
        : { ...comment, replies: comment.replies?.map(replace) };
    update(comments.map(replace));
    setEditing(null);
    return null;
  };

  const confirmDeletion = async () => {
    if (!pendingDeletion) {
      return;
    }
    setDeleting(true);
    const result = await knowiiApiClient.communities.comments.delete(communityCuid, spaceCuid, postCuid, pendingDeletion.cuid);
    setDeleting(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    update(
      comments
        .filter((comment) => comment.cuid !== pendingDeletion.cuid)
        .map((comment) => ({ ...comment, replies: comment.replies?.filter((reply) => reply.cuid !== pendingDeletion.cuid) })),
    );
    setPendingDeletion(null);
    showToast({ severity: 'success', summary: 'Comment deleted' });
  };

  const renderComment = (comment: Comment, topLevel: Comment) => {
    const isAuthor = currentUserCuid !== null && comment.author?.cuid === currentUserCuid;
    const authorName = comment.author ? comment.author.name : 'Deleted member';
    return (
      <article aria-label={`Comment by ${authorName}`} className="flex flex-col gap-1">
        <p className="text-xs text-muted">
          <span className="font-bold text-primary">{authorName}</span> · <RelativeTime date={comment.created_at} />
          {comment.edited_at && ' · edited'}
        </p>
        {editing === comment.cuid ? (
          <CommentForm
            communityCuid={communityCuid}
            label="Edit your comment"
            submitLabel="Save"
            initialBody={comment.body}
            autoFocus
            onSubmit={(values) => save(comment, values.body)}
            onCancel={() => setEditing(null)}
          />
        ) : (
          <>
            <MarkdownContent html={comment.body_html} className="text-sm" />
            <ReactionBar
              target={{ communityCuid, spaceCuid, postCuid, commentCuid: comment.cuid }}
              initialReactions={comment.reactions}
              canReact={canComment}
              itemLabel={`the comment by ${authorName}`}
            />
          </>
        )}
        {editing !== comment.cuid && (
          <div className="flex flex-wrap gap-1">
            {canComment && (
              <Button size="sm" variant="ghost" aria-label={`Reply to ${authorName}`} onClick={() => setReplyingTo(topLevel.cuid)}>
                <FaReply aria-hidden="true" />
                Reply
              </Button>
            )}
            {isAuthor && (
              <Button size="sm" variant="ghost" aria-label="Edit your comment" onClick={() => setEditing(comment.cuid)}>
                <FaPen aria-hidden="true" />
                Edit
              </Button>
            )}
            {(isAuthor || canModerate) && (
              <Button
                size="sm"
                variant="ghost"
                aria-label={`Delete the comment by ${authorName}`}
                onClick={() => setPendingDeletion(comment)}
              >
                <FaTrash aria-hidden="true" />
                Delete
              </Button>
            )}
          </div>
        )}
      </article>
    );
  };

  const total = countAll(comments);

  return (
    <section aria-labelledby="comments-title" className="flex flex-col gap-4 border-t border-border pt-6">
      <h2 id="comments-title" className="text-lg font-extrabold">
        {total === 0 ? 'Comments' : total === 1 ? '1 comment' : `${total} comments`}
      </h2>

      {comments.length === 0 && <p className="text-sm text-muted">No comments yet.</p>}

      <ol className="flex flex-col gap-5">
        {comments.map((comment) => (
          <li key={comment.cuid} className="flex flex-col gap-3">
            {renderComment(comment, comment)}
            {((comment.replies?.length ?? 0) > 0 || replyingTo === comment.cuid) && (
              <ol
                className="ml-4 flex flex-col gap-3 border-l-2 border-border pl-4"
                aria-label={`Replies to the comment by ${comment.author?.name ?? 'a deleted member'}`}
              >
                {comment.replies?.map((reply) => (
                  <li key={reply.cuid}>{renderComment(reply, comment)}</li>
                ))}
                {replyingTo === comment.cuid && (
                  <li>
                    <CommentForm
                      communityCuid={communityCuid}
                      label={`Reply to ${comment.author?.name ?? 'the comment'}`}
                      submitLabel="Reply"
                      autoFocus
                      onSubmit={(values) => create(values.body, comment.cuid)}
                      onCancel={() => setReplyingTo(null)}
                    />
                  </li>
                )}
              </ol>
            )}
          </li>
        ))}
      </ol>

      {canComment && (
        <CommentForm
          communityCuid={communityCuid}
          label="Write a comment"
          submitLabel="Comment"
          onSubmit={(values) => create(values.body, null)}
        />
      )}

      <Dialog
        open={pendingDeletion !== null}
        onClose={() => setPendingDeletion(null)}
        size="sm"
        title="Delete this comment?"
        description={
          pendingDeletion?.parent_cuid === null && (pendingDeletion.replies?.length ?? 0) > 0
            ? 'Its replies are deleted too. This cannot be undone.'
            : 'This cannot be undone.'
        }
        footer={
          <>
            <Button variant="secondary" onClick={() => setPendingDeletion(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={confirmDeletion} loading={deleting}>
              Delete
            </Button>
          </>
        }
      />
    </section>
  );
}
