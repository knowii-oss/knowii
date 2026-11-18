import { useEffect, useMemo, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import type { Comment, CommentSubject, SpaceContentPayload } from '@knowii/common';
import { knowiiApiClient, useAppData, useSocket } from '@knowii/common';
import { FaChevronDown, FaChevronRight, FaLink, FaPen, FaReply, FaTrash } from 'react-icons/fa6';
import { Button, Checkbox, Dialog, ErrorBoundary, MarkdownContent, RelativeTime, useFocusAfterRender } from '@/Components/ui';
import { CommentForm } from './CommentForm';
import { commentBecomesMarker, commentDeletionLine, commentsAfterDeletion } from './delete-rules';
import { ReactionBar } from '@/Components/Reactions/ReactionBar';
import { SignInPrompt, useIsVisitor } from '@/Components/Reader/Reader';

export interface CommentSectionProps {
  // The post or the resource that the comments are about (#1034)
  subject: CommentSubject;
  initialComments: Comment[];
  currentUserCuid: string | null;
  canComment: boolean;
  canModerate: boolean;
  /**
   * Called when the number of comments (top-level and replies) changes
   */
  onCountChange?: (count: number) => void;
  /**
   * Called when the comments change: the delete dialog of the post says what happens to them (#1565)
   */
  onCommentsChange?: (comments: Comment[]) => void;
}

const countAll = (comments: Comment[]) => comments.reduce((total, comment) => total + 1 + (comment.replies?.length ?? 0), 0);

/**
 * The anchor of a comment in the page (#1205): `#comment-{cuid}` links to it
 */
export const commentAnchor = (cuid: string) => `comment-${cuid}`;

const HIGHLIGHT_MILLISECONDS = 2500;

// The buttons that open a form, found again after the form closes (#1624)
type FormOpener = 'reply' | 'edit';

/**
 * The comment element (author, time and text): the target of the focus after a save (#1624)
 */
const commentElement = (cuid: string) => document.getElementById(commentAnchor(cuid));

const openerButton = (cuid: string, opener: FormOpener) =>
  commentElement(cuid)?.querySelector<HTMLButtonElement>(`[data-opens="${opener}"]`);

const COMMENTS_TITLE_ID = 'comments-title';

/**
 * A comment, or the button of its thread when the thread is collapsed
 */
const commentOrThread = (cuid: string) => commentElement(cuid) ?? document.querySelector<HTMLElement>(`[aria-controls="thread-${cuid}"]`);

/**
 * Where the focus goes after a delete (#1565): the marker that stays, else the next comment of the same list, the one
 * before it, the comment that the reply answered, or the title of the comments. Found after the render.
 */
function focusAfterDeletion(before: Comment[], target: Comment, marker: boolean): () => HTMLElement | null {
  if (marker) {
    return () => commentElement(target.cuid);
  }
  const parent = target.parent_cuid === null ? null : before.find((comment) => comment.cuid === target.parent_cuid);
  const siblings = parent ? (parent.replies ?? []) : before;
  const index = siblings.findIndex((comment) => comment.cuid === target.cuid);
  const neighbour = siblings[index + 1] ?? siblings[index - 1] ?? parent ?? null;
  return () => (neighbour ? commentOrThread(neighbour.cuid) : null) ?? document.getElementById(COMMENTS_TITLE_ID);
}

/**
 * A saved comment in the list, once: the live update may have brought it before the response of the API (#1624)
 */
function withCreated(comments: Comment[], created: Comment): Comment[] {
  if (created.parent_cuid === null) {
    return comments.some((comment) => comment.cuid === created.cuid) ? comments : [...comments, { ...created, replies: [] }];
  }
  return comments.map((comment) =>
    comment.cuid === created.parent_cuid && !comment.replies?.some((reply) => reply.cuid === created.cuid)
      ? { ...comment, replies: [...(comment.replies ?? []), created] }
      : comment,
  );
}

/**
 * The comment that the URL points to (`#comment-{cuid}`), if any
 */
function commentInHash(): string | null {
  const hash = window.location.hash;
  const prefix = `#${commentAnchor('')}`;
  return hash.startsWith(prefix) ? decodeURIComponent(hash.slice(prefix.length)) : null;
}

/**
 * The top-level comment of the thread that holds a comment (the comment itself, or the parent of a reply)
 */
function threadOf(comments: Comment[], cuid: string): string | null {
  return comments.find((comment) => comment.cuid === cuid || comment.replies?.some((reply) => reply.cuid === cuid))?.cuid ?? null;
}

const collapsedStorageKey = (subjectCuid: string) => `knowii:collapsed-comments:${subjectCuid}`;

function readCollapsed(subjectCuid: string): string[] {
  try {
    const value: unknown = JSON.parse(window.localStorage.getItem(collapsedStorageKey(subjectCuid)) ?? '[]');
    return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : [];
  } catch {
    return [];
  }
}

/**
 * Scrolls to a comment, moves the focus to it and highlights it for a moment (#1205)
 */
function showComment(cuid: string) {
  const element = document.getElementById(commentAnchor(cuid));
  if (!element) {
    return;
  }
  element.scrollIntoView({ block: 'center' });
  element.focus({ preventScroll: true });
  element.dataset['highlighted'] = 'true';
  window.setTimeout(() => {
    delete element.dataset['highlighted'];
  }, HIGHLIGHT_MILLISECONDS);
}

/**
 * Comments of a post or a resource, oldest first, with one level of replies. Authors edit their comments; authors and moderators
 * delete them. A comment with replies of other people stays as a marker; a moderator can delete the replies too (#1565).
 */
export function CommentSection(props: CommentSectionProps) {
  const { subject, currentUserCuid, canComment, canModerate, onCountChange, onCommentsChange } = props;
  const { showToast } = useAppData();
  const isVisitor = useIsVisitor();
  const [comments, setComments] = useState<Comment[]>(props.initialComments);
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  // The comment whose "Reply" button opened the reply form: a reply to a reply opens the form of its thread (#1624)
  const replyOpener = useRef<string | null>(null);
  const [editing, setEditing] = useState<string | null>(null);
  const [pendingDeletion, setPendingDeletion] = useState<Comment | null>(null);
  // The comment of the delete dialog, kept while the dialog fades out
  const [deletionTarget, setDeletionTarget] = useState<Comment | null>(null);
  // "Also delete the replies (N)": moderators only (#1565)
  const [deleteReplies, setDeleteReplies] = useState(false);
  const [deleting, setDeleting] = useState(false);
  // The element that gets the focus once the delete dialog is closed (#1565)
  const focusAfterClose = useRef<(() => HTMLElement | null) | null>(null);

  const focusAfterRender = useFocusAfterRender();

  const update = (next: Comment[]) => {
    setComments(next);
    onCountChange?.(countAll(next));
    onCommentsChange?.(next);
  };

  // Live updates (#1180): a comment of this subject changed, or the reactions of one: load the comments again. The
  // forms keep what the member is typing (they are keyed by comment).
  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: subject.communityCuid, spaceCuid: subject.spaceCuid }),
    [subject.communityCuid, subject.spaceCuid],
  );
  const subjectCuid = 'postCuid' in subject ? subject.postCuid : subject.resourceCuid;

  // Collapsed threads (#1205), kept per post or resource in the browser. A link to a comment opens its thread.
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(() => {
    const kept = new Set(readCollapsed(subjectCuid));
    const target = commentInHash();
    const thread = target ? threadOf(props.initialComments, target) : null;
    if (thread) {
      kept.delete(thread);
    }
    return kept;
  });
  const changeCollapsed = (next: ReadonlySet<string>) => {
    setCollapsed(next);
    try {
      window.localStorage.setItem(collapsedStorageKey(subjectCuid), JSON.stringify([...next]));
    } catch {
      // Storage full or blocked: the threads stay collapsed until the page reloads
    }
  };
  const toggleThread = (cuid: string) => {
    const next = new Set(collapsed);
    if (!next.delete(cuid)) {
      next.add(cuid);
    }
    changeCollapsed(next);
  };

  // The latest values for the hashchange listener, which is registered once
  const latest = useRef({ comments, collapsed, changeCollapsed });
  useEffect(() => {
    latest.current = { comments, collapsed, changeCollapsed };
  });

  // Open the comment of the URL (#1205): on the first render, and when the hash changes (a link on the same page)
  useEffect(() => {
    const initial = commentInHash();
    if (initial) {
      showComment(initial);
    }
    const onHashChange = () => {
      const target = commentInHash();
      if (!target) {
        return;
      }
      const { comments: current, collapsed: closed, changeCollapsed: change } = latest.current;
      const thread = threadOf(current, target);
      if (thread && closed.has(thread)) {
        const next = new Set(closed);
        next.delete(thread);
        // The comment must be in the page before it gets the focus
        flushSync(() => change(next));
      }
      showComment(target);
    };
    window.addEventListener('hashchange', onHashChange);
    return () => window.removeEventListener('hashchange', onHashChange);
  }, []);

  const copyLink = async (comment: Comment) => {
    const { origin, pathname, search } = window.location;
    const url = `${origin}${pathname}${search}#${commentAnchor(comment.cuid)}`;
    try {
      await navigator.clipboard.writeText(url);
      showToast({ severity: 'success', summary: 'Link copied' });
    } catch {
      showToast({ severity: 'error', summary: 'Could not copy the link', detail: url });
    }
  };

  useSocket({
    channel,
    // A visitor gets a static page: no connection (#1387)
    enabled: !isVisitor,
    event: ['space.comment.created', 'space.comment.updated', 'space.comment.deleted', 'space.reactions.changed'],
    callback: (event, payload) => {
      const data = payload as SpaceContentPayload;
      const aboutThisSubject = ('postCuid' in subject ? data.post_cuid : data.resource_cuid) === subjectCuid;
      if (!aboutThisSubject || (event === 'space.reactions.changed' && !data.comment_cuid)) {
        return;
      }
      void knowiiApiClient.communities.comments.list(subject).then((result) => {
        if (result.ok) {
          update(result.data);
        }
      });
    },
  });

  const create = async (body: string, parentCuid: string | null): Promise<string | null> => {
    const result = await knowiiApiClient.communities.comments.create(subject, { body, parent_cuid: parentCuid });
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return result.errors['body']?.[0] ?? result.message;
    }
    const created = result.data;
    // The comments of now: a live update may have changed them during the request
    update(withCreated(latest.current.comments, created));
    setReplyingTo(null);
    replyOpener.current = null;
    if (created.parent_cuid !== null) {
      // The reply form goes away: the focus goes to the new reply (#1624). The top-level form stays and keeps it (#1618).
      focusAfterRender(() => commentElement(created.cuid));
    }
    return null;
  };

  const openReply = (comment: Comment, topLevel: Comment) => {
    replyOpener.current = comment.cuid;
    setReplyingTo(topLevel.cuid);
  };

  const cancelReply = () => {
    const opener = replyOpener.current;
    replyOpener.current = null;
    setReplyingTo(null);
    if (opener) {
      focusAfterRender(() => openerButton(opener, 'reply'));
    }
  };

  const save = async (target: Comment, body: string): Promise<string | null> => {
    const result = await knowiiApiClient.communities.comments.update(subject, target.cuid, { body });
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return result.errors['body']?.[0] ?? result.message;
    }
    const replace = (comment: Comment): Comment =>
      comment.cuid === target.cuid
        ? { ...comment, body: result.data.body, body_html: result.data.body_html, edited_at: result.data.edited_at }
        : { ...comment, replies: comment.replies?.map(replace) };
    update(latest.current.comments.map(replace));
    setEditing(null);
    // The edit form goes away: the focus goes to the edited comment (#1624)
    focusAfterRender(() => commentElement(target.cuid));
    return null;
  };

  const cancelEdit = (target: Comment) => {
    setEditing(null);
    focusAfterRender(() => openerButton(target.cuid, 'edit'));
  };

  const askDeletion = (comment: Comment) => {
    setDeleteReplies(false);
    setDeletionTarget(comment);
    setPendingDeletion(comment);
  };

  // A marker goes only with its replies: only a moderator sees its "Delete" (#1565)
  const deletesReplies = (target: Comment) => target.deleted_at !== null || (canModerate && deleteReplies);

  const confirmDeletion = async () => {
    const target = pendingDeletion;
    if (!target || deleting) {
      return;
    }
    const withReplies = deletesReplies(target);
    setDeleting(true);
    const result = await knowiiApiClient.communities.comments.delete(subject, target.cuid, withReplies ? { delete_replies: true } : {});
    setDeleting(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    // The comments of now: a live update may have changed them during the request
    const before = latest.current.comments;
    const marker = commentBecomesMarker(target, currentUserCuid, withReplies);
    update(commentsAfterDeletion(before, target, currentUserCuid, withReplies));
    // The dialog gives the focus back once it is closed: the "Delete" button is gone, the marker or a neighbour gets it
    focusAfterClose.current = focusAfterDeletion(before, target, marker);
    setPendingDeletion(null);
    showToast({ severity: 'success', summary: 'Comment deleted' });
  };

  const afterDeletionDialog = () => {
    const find = focusAfterClose.current;
    focusAfterClose.current = null;
    setDeletionTarget(null);
    setDeleteReplies(false);
    if (find) {
      // After the unmount of the dialog: Headless UI then gives the focus to the last element it knew, in a microtask
      window.setTimeout(() => focusAfterRender(find), 0);
    }
  };

  const renderComment = (comment: Comment, topLevel: Comment) => {
    const isAuthor = currentUserCuid !== null && comment.author?.cuid === currentUserCuid;
    const authorName = comment.author ? comment.author.name : 'Deleted member';
    // The marker of a deleted comment that still has replies of other people (#1344, #1565)
    const isMarker = comment.deleted_at !== null;
    return (
      <article
        id={commentAnchor(comment.cuid)}
        // The target of a link to the comment gets the focus (#1205), and its outline stays until the focus leaves (#1484)
        tabIndex={-1}
        aria-label={isMarker ? 'Deleted comment' : `Comment by ${authorName}`}
        className="-mx-2 flex scroll-mt-24 flex-col gap-1 rounded-lg px-2 py-1 transition-colors duration-700 outline-none focus:outline-2 focus:outline-solid focus:outline-primary motion-reduce:transition-none data-[highlighted=true]:bg-secondary/15"
      >
        {isMarker ? (
          <p className="text-sm text-muted italic">This comment was deleted.</p>
        ) : (
          <p className="text-xs text-muted">
            <span className="font-bold text-primary">{authorName}</span> · <RelativeTime date={comment.created_at} />
            {comment.edited_at && ' · edited'}
          </p>
        )}
        {isMarker ? null : editing === comment.cuid ? (
          <CommentForm
            communityCuid={subject.communityCuid}
            label="Edit your comment"
            submitLabel="Save"
            initialBody={comment.body}
            autoFocus
            onSubmit={(values) => save(comment, values.body)}
            onCancel={() => cancelEdit(comment)}
          />
        ) : (
          <ErrorBoundary variant="block" label={`the comment by ${authorName}`}>
            <MarkdownContent html={comment.body_html} className="text-sm" />
            <ReactionBar
              target={{ ...subject, commentCuid: comment.cuid }}
              initialReactions={comment.reactions}
              canReact={canComment}
              itemLabel={`the comment by ${authorName}`}
            />
          </ErrorBoundary>
        )}
        {editing !== comment.cuid && (
          <div className="flex flex-wrap gap-1">
            {canComment && (
              <Button
                size="sm"
                variant="ghost"
                aria-label={isMarker ? 'Reply in the thread of the deleted comment' : `Reply to ${authorName}`}
                data-opens="reply"
                onClick={() => openReply(comment, topLevel)}
              >
                <FaReply aria-hidden="true" />
                Reply
              </Button>
            )}
            <Button
              size="sm"
              variant="ghost"
              aria-label={isMarker ? 'Copy the link to the deleted comment' : `Copy the link to the comment by ${authorName}`}
              onClick={() => void copyLink(comment)}
            >
              <FaLink aria-hidden="true" />
              Copy link
            </Button>
            {isAuthor && (
              <Button size="sm" variant="ghost" aria-label="Edit your comment" data-opens="edit" onClick={() => setEditing(comment.cuid)}>
                <FaPen aria-hidden="true" />
                Edit
              </Button>
            )}
            {(isAuthor || canModerate) && (
              <Button
                size="sm"
                variant="ghost"
                aria-label={isMarker ? 'Delete the deleted comment' : `Delete the comment by ${authorName}`}
                onClick={() => askDeletion(comment)}
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
    <section aria-labelledby={COMMENTS_TITLE_ID} className="flex flex-col gap-4 border-t border-border pt-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        {/* The focus goes here after the delete of the last comment (#1565) */}
        <h2
          id={COMMENTS_TITLE_ID}
          tabIndex={-1}
          className="rounded-sm text-lg font-extrabold outline-none focus:outline-2 focus:outline-offset-2 focus:outline-solid focus:outline-primary"
        >
          {total === 0 ? 'Comments' : total === 1 ? '1 comment' : `${total} comments`}
        </h2>
        {comments.length > 0 && (
          <div className="flex gap-1">
            <Button size="sm" variant="ghost" disabled={collapsed.size === 0} onClick={() => changeCollapsed(new Set())}>
              Expand all
            </Button>
            <Button
              size="sm"
              variant="ghost"
              disabled={comments.every((comment) => collapsed.has(comment.cuid))}
              onClick={() => changeCollapsed(new Set(comments.map((comment) => comment.cuid)))}
            >
              Collapse all
            </Button>
          </div>
        )}
      </div>

      {comments.length === 0 && <p className="text-sm text-muted">No comments yet.</p>}

      <ol className="flex flex-col gap-5">
        {comments.map((comment) => {
          const authorName = comment.deleted_at !== null ? 'a deleted comment' : (comment.author?.name ?? 'a deleted member');
          const replyCount = comment.replies?.length ?? 0;
          const isCollapsed = collapsed.has(comment.cuid);
          const threadId = `thread-${comment.cuid}`;
          return (
            <li key={comment.cuid} className="flex gap-1">
              <Button
                size="sm"
                variant="ghost"
                iconOnly
                aria-expanded={!isCollapsed}
                aria-controls={threadId}
                aria-label={`${isCollapsed ? 'Expand' : 'Collapse'} the thread of ${authorName}`}
                onClick={() => toggleThread(comment.cuid)}
              >
                {isCollapsed ? <FaChevronRight aria-hidden="true" /> : <FaChevronDown aria-hidden="true" />}
              </Button>
              {isCollapsed ? (
                <p id={threadId} className="flex min-h-8 items-center text-xs text-muted">
                  <span className="font-bold text-primary">
                    {comment.deleted_at !== null ? 'Deleted comment' : comment.author ? comment.author.name : 'Deleted member'}
                  </span>
                  &nbsp;· {replyCount === 0 ? 'no replies' : replyCount === 1 ? '1 reply' : `${replyCount} replies`}
                </p>
              ) : (
                <div id={threadId} className="flex min-w-0 flex-1 flex-col gap-3">
                  {renderComment(comment, comment)}
                  {(replyCount > 0 || replyingTo === comment.cuid) && (
                    <div className="relative">
                      {/* The thread line collapses the thread on click, like Reddit. The button above does it for the keyboard. */}
                      <button
                        type="button"
                        tabIndex={-1}
                        aria-hidden="true"
                        className="group absolute top-0 bottom-0 left-0 w-4 cursor-pointer"
                        onClick={() => toggleThread(comment.cuid)}
                      >
                        <span className="absolute top-0 bottom-0 left-0 w-0.5 bg-border group-hover:bg-secondary" />
                      </button>
                      <ol
                        className="flex flex-col gap-3 pl-6"
                        aria-label={
                          comment.deleted_at !== null ? 'Replies to the deleted comment' : `Replies to the comment by ${authorName}`
                        }
                      >
                        {comment.replies?.map((reply) => (
                          <li key={reply.cuid}>{renderComment(reply, comment)}</li>
                        ))}
                        {replyingTo === comment.cuid && (
                          <li>
                            <CommentForm
                              communityCuid={subject.communityCuid}
                              label={`Reply to ${comment.author?.name ?? 'the comment'}`}
                              submitLabel="Reply"
                              autoFocus
                              onSubmit={(values) => create(values.body, comment.cuid)}
                              onCancel={cancelReply}
                            />
                          </li>
                        )}
                      </ol>
                    </div>
                  )}
                </div>
              )}
            </li>
          );
        })}
      </ol>

      {canComment && (
        <CommentForm
          communityCuid={subject.communityCuid}
          label="Write a comment"
          submitLabel="Comment"
          onSubmit={(values) => create(values.body, null)}
        />
      )}
      {isVisitor && <SignInPrompt>Sign in to reply</SignInPrompt>}

      <Dialog
        open={pendingDeletion !== null}
        onClose={() => setPendingDeletion(null)}
        onAfterClose={afterDeletionDialog}
        size="sm"
        title="Delete this comment?"
        description={[
          deletionTarget ? commentDeletionLine(deletionTarget, currentUserCuid, deletesReplies(deletionTarget)) : null,
          'This cannot be undone.',
        ]
          .filter((line) => line !== null)
          .join(' ')}
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
      >
        {/* A moderator chooses to delete the replies of other people too (#1565). A marker always goes with them. */}
        {canModerate && deletionTarget && deletionTarget.deleted_at === null && (deletionTarget.replies?.length ?? 0) > 0 ? (
          <label className="flex cursor-pointer items-start gap-3 text-sm">
            <Checkbox
              checked={deleteReplies}
              onChange={(event) => {
                // Busy: the choice stays as it was sent
                if (!deleting) {
                  setDeleteReplies(event.target.checked);
                }
              }}
            />
            <span className="pt-0.5">Also delete the replies ({deletionTarget.replies?.length ?? 0})</span>
          </label>
        ) : null}
      </Dialog>
    </section>
  );
}
