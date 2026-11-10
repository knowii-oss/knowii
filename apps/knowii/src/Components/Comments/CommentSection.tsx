import { useEffect, useMemo, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import type { Comment, CommentSubject, SpaceContentPayload } from '@knowii/common';
import { knowiiApiClient, useAppData, useSocket } from '@knowii/common';
import { FaChevronDown, FaChevronRight, FaLink, FaPen, FaReply, FaTrash } from 'react-icons/fa6';
import { Button, Dialog, ErrorBoundary, MarkdownContent, RelativeTime } from '@/Components/ui';
import { CommentForm } from './CommentForm';
import { ReactionBar } from '@/Components/Reactions/ReactionBar';

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
}

const countAll = (comments: Comment[]) => comments.reduce((total, comment) => total + 1 + (comment.replies?.length ?? 0), 0);

/**
 * The anchor of a comment in the page (#1205): `#comment-{cuid}` links to it
 */
export const commentAnchor = (cuid: string) => `comment-${cuid}`;

const HIGHLIGHT_MILLISECONDS = 2500;

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
 * delete them (a deleted comment takes its replies with it).
 */
export function CommentSection(props: CommentSectionProps) {
  const { subject, currentUserCuid, canComment, canModerate, onCountChange } = props;
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
    event: ['space.comment.created', 'space.comment.updated', 'space.comment.deleted', 'space.reactions.changed'],
    callback: (event, payload) => {
      const data = payload as SpaceContentPayload;
      const aboutThisSubject = ('postCuid' in subject ? data.post_cuid : data.resource_cuid) === subjectCuid;
      if (!aboutThisSubject || (event === 'space.reactions.changed' && !data.comment_cuid)) {
        return;
      }
      void knowiiApiClient.communities.comments.list(subject).then((result) => {
        if (result.ok) {
          setComments(result.data);
          onCountChange?.(countAll(result.data));
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
    const result = await knowiiApiClient.communities.comments.update(subject, target.cuid, { body });
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
    const result = await knowiiApiClient.communities.comments.delete(subject, pendingDeletion.cuid);
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
      <article
        id={commentAnchor(comment.cuid)}
        // The target of a link to the comment gets the focus (#1205)
        tabIndex={-1}
        aria-label={`Comment by ${authorName}`}
        className="-mx-2 flex scroll-mt-24 flex-col gap-1 rounded-lg px-2 py-1 transition-colors duration-700 outline-none focus-visible:outline-2 focus-visible:outline-primary data-[highlighted=true]:bg-secondary/15"
      >
        <p className="text-xs text-muted">
          <span className="font-bold text-primary">{authorName}</span> · <RelativeTime date={comment.created_at} />
          {comment.edited_at && ' · edited'}
        </p>
        {editing === comment.cuid ? (
          <CommentForm
            communityCuid={subject.communityCuid}
            label="Edit your comment"
            submitLabel="Save"
            initialBody={comment.body}
            autoFocus
            onSubmit={(values) => save(comment, values.body)}
            onCancel={() => setEditing(null)}
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
              <Button size="sm" variant="ghost" aria-label={`Reply to ${authorName}`} onClick={() => setReplyingTo(topLevel.cuid)}>
                <FaReply aria-hidden="true" />
                Reply
              </Button>
            )}
            <Button
              size="sm"
              variant="ghost"
              aria-label={`Copy the link to the comment by ${authorName}`}
              onClick={() => void copyLink(comment)}
            >
              <FaLink aria-hidden="true" />
              Copy link
            </Button>
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
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="comments-title" className="text-lg font-extrabold">
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
          const authorName = comment.author?.name ?? 'a deleted member';
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
                  <span className="font-bold text-primary">{comment.author ? comment.author.name : 'Deleted member'}</span>
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
                      <ol className="flex flex-col gap-3 pl-6" aria-label={`Replies to the comment by ${authorName}`}>
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
                              onCancel={() => setReplyingTo(null)}
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
