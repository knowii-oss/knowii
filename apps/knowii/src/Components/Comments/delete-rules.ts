import type { Comment, Post } from '@knowii/common';

/**
 * What a delete does (#1565, ADR 0050), the same rules as DeleteComment.php and DeletePost.php: the dialogs say it
 * before, and the page shows the result without a reload.
 */

/**
 * A comment of "other people": not a marker, and not written by the person who deletes (a deleted account counts)
 */
export function isOfOtherPeople(comment: Comment, currentUserCuid: string | null): boolean {
  return comment.deleted_at === null && (comment.author === null || comment.author.cuid !== currentUserCuid);
}

const isOwn = (item: { author: { cuid: string } | null }, currentUserCuid: string | null) =>
  currentUserCuid !== null && item.author?.cuid === currentUserCuid;

/**
 * Every comment of a list, the replies included
 */
export const allComments = (comments: Comment[]): Comment[] => comments.flatMap((comment) => [comment, ...(comment.replies ?? [])]);

/**
 * The comment stays as a marker for the replies of other people: the delete is not the one of a moderator with the
 * replies, and the comment is not a marker yet
 */
export function commentBecomesMarker(target: Comment, currentUserCuid: string | null, withReplies: boolean): boolean {
  return !withReplies && target.deleted_at === null && (target.replies ?? []).some((reply) => isOfOtherPeople(reply, currentUserCuid));
}

/**
 * The post stays as a marker for the comments of other people. While some comments are not loaded (`complete` false,
 * ADR 0073), it may: the page asks the server for the post after the delete
 */
export function postBecomesMarker(
  post: Post,
  comments: Comment[],
  currentUserCuid: string | null,
  withComments: boolean,
  complete = true,
): boolean {
  return (
    !withComments &&
    post.deleted_at === null &&
    (!complete || allComments(comments).some((comment) => isOfOtherPeople(comment, currentUserCuid)))
  );
}

/**
 * The line of the delete dialog of a comment that says what happens to the replies, or null when it has none
 */
export function commentDeletionLine(target: Comment, currentUserCuid: string | null, withReplies: boolean): string | null {
  const replies = target.replies ?? [];
  if (replies.length === 0) {
    return null;
  }
  // A marker goes only with its replies (a moderator)
  if (withReplies || target.deleted_at !== null) {
    return 'Its replies are deleted too.';
  }
  if (commentBecomesMarker(target, currentUserCuid, false)) {
    return isOwn(target, currentUserCuid)
      ? 'The replies of other people stay. Your comment is replaced by "This comment was deleted."'
      : 'The replies of other people stay. The comment is replaced by "This comment was deleted."';
  }
  return 'Your replies under it are deleted too.';
}

/**
 * The line of the delete dialog of a post that says what happens to the discussion, or null when it has none
 */
export function postDeletionLine(
  post: Post,
  comments: Comment[],
  currentUserCuid: string | null,
  withComments: boolean,
  complete = true,
): string | null {
  const all = allComments(comments);
  if (all.length === 0) {
    return null;
  }
  if (withComments || post.deleted_at !== null) {
    return 'Its comments are deleted too.';
  }
  // Some comments are not loaded (ADR 0073): the page does not know whether they are of other people
  if (!complete && !postBecomesMarker(post, comments, currentUserCuid, false)) {
    return 'Your comments under it are deleted too. If it has comments of other people, they stay and the post is replaced by a marker.';
  }
  if (postBecomesMarker(post, comments, currentUserCuid, false, complete)) {
    return isOwn(post, currentUserCuid)
      ? 'The comments of other people stay. Your post is replaced by a marker.'
      : 'The comments of other people stay. The post is replaced by a marker.';
  }
  // Only the person's own comments, and markers that hold nothing
  return all.some((comment) => comment.deleted_at === null) ? 'Your comments under it are deleted too.' : null;
}

/**
 * The comments after a delete: the comment becomes a marker with its replies, or goes with them
 */
export function commentsAfterDeletion(
  comments: Comment[],
  target: Comment,
  currentUserCuid: string | null,
  withReplies: boolean,
): Comment[] {
  if (commentBecomesMarker(target, currentUserCuid, withReplies)) {
    return comments.map((comment) =>
      comment.cuid === target.cuid
        ? { ...comment, body: '', body_html: '', author: null, edited_at: null, reactions: [], deleted_at: new Date() }
        : comment,
    );
  }
  return comments
    .filter((comment) => comment.cuid !== target.cuid)
    .map((comment) => ({ ...comment, replies: comment.replies?.filter((reply) => reply.cuid !== target.cuid) }));
}

/**
 * The number of comments, the replies included (the title of the comments counts the same way)
 */
export const countAllComments = (comments: Comment[]): number => allComments(comments).length;
