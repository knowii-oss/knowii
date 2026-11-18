import type { Notification } from './notification.schema';
import {
  catchUpLineDetail,
  catchUpLineHeadline,
  catchUpSchema,
  FOLLOWING_KINDS,
  notificationActorNames,
  notificationBadge,
  notificationBellName,
  notificationReasonSentence,
  notificationSchema,
  notificationSentence,
  notificationSentenceHasTitle,
  notificationShowsItem,
  notificationSettingsLinkName,
  tabTitle,
  tabTitleBadge,
} from './notification.schema';
import { SPACE_NOTIFICATION_LEVEL_LABELS, spaceNotificationLevelsFor, threadFollowLabel } from './notification-follow.schema';
import { formatEventStart } from '../utils/time-zone.fn';

const person = (name: string) => ({ cuid: name.toLowerCase(), name, username: name.toLowerCase(), avatar_url: null });

const resource = {
  cuid: 'n1',
  kind: 'post_comment',
  level: 1,
  reason: 'wrote_post',
  community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
  space: { cuid: 's1', slug: 'open', name: 'Open discussion' },
  actor: person('Anna'),
  actors: [person('Anna')],
  others_count: 0,
  items_count: 1,
  title: 'Notes workflow',
  excerpt: 'I use one inbox note per day',
  target: {
    post_slug: 'notes-workflow',
    post_cuid: 'p1',
    comment_cuid: 'k1',
    chat_message_cuid: null,
    thread_cuid: null,
    resource_cuid: null,
    resource_slug: null,
    page_slug: null,
    event_cuid: null,
    event_slug: null,
  },
  event_starts_at: null,
  seen_at: null,
  read_at: null,
  created_at: '2026-11-15T10:00:00.000000Z',
  updated_at: '2026-11-15T10:05:00.000000Z',
};

const notification = (changes: Partial<Notification> = {}): Notification => ({ ...notificationSchema.parse(resource), ...changes });

describe('notificationSchema (#1484)', () => {
  it('parses the resource of the server, with its dates', () => {
    const parsed = notificationSchema.parse(resource);

    expect(parsed.kind).toBe('post_comment');
    expect(parsed.seen_at).toBeNull();
    expect(parsed.created_at).toBeInstanceOf(Date);
    expect(parsed.target.post_slug).toBe('notes-workflow');
  });

  it('accepts a deleted author, a row without a space and a bundle count sent as text', () => {
    const parsed = notificationSchema.parse({
      ...resource,
      actor: null,
      actors: [],
      space: null,
      others_count: '4',
      seen_at: '2026-11-15T10:06:00Z',
    });

    expect(parsed.actor).toBeNull();
    expect(parsed.others_count).toBe(4);
    expect(parsed.seen_at).toBeInstanceOf(Date);
  });

  it('refuses an unknown kind and a missing target', () => {
    expect(notificationSchema.safeParse({ ...resource, kind: 'reaction' }).success).toBe(false);
    expect(notificationSchema.safeParse({ ...resource, target: undefined }).success).toBe(false);
  });
});

describe('the bell (#1484)', () => {
  it('shows 1 to 9, then "9+", and nothing at zero', () => {
    expect(notificationBadge(0)).toBeNull();
    expect(notificationBadge(3)).toBe('3');
    expect(notificationBadge(9)).toBe('9');
    expect(notificationBadge(10)).toBe('9+');
    expect(notificationBadge(120)).toBe('9+');
  });

  it('names the bell with the number', () => {
    expect(notificationBellName(0)).toBe('Notifications');
    expect(notificationBellName(3)).toBe('Notifications, 3 new');
    expect(notificationBellName(12)).toBe('Notifications, 9 or more new');
  });
});

describe('notificationSentence (#1484)', () => {
  it('names 1, 2, 3 and 5 authors', () => {
    expect(notificationSentence(notification())).toBe('Anna replied to your post');
    expect(notificationSentence(notification({ kind: 'comment_reply', actors: [person('Anna'), person('Ben')] }))).toBe(
      'Anna and Ben replied to your comment',
    );
    expect(notificationActorNames(notification({ actors: [person('Anna'), person('Ben'), person('Carl')] }))).toBe('Anna, Ben and Carl');
    expect(notificationSentence(notification({ actors: [person('Anna'), person('Ben'), person('Carl')], others_count: 2 }))).toBe(
      'Anna, Ben, Carl and 2 others replied to your post',
    );
    expect(notificationActorNames(notification({ actors: [person('Anna'), person('Ben'), person('Carl')], others_count: 1 }))).toBe(
      'Anna, Ben, Carl and 1 other',
    );
  });

  it('uses the last author when the bundle has no list, and "Deleted member" without an author', () => {
    expect(notificationActorNames(notification({ actors: [], actor: person('Zoe') }))).toBe('Zoe');
    expect(notificationSentence(notification({ actors: [], actor: null }))).toBe('Deleted member replied to your post');
  });

  it('says where a mention happened', () => {
    expect(notificationSentence(notification({ kind: 'mention', actor: person('Marc'), actors: [person('Marc')] }))).toBe(
      'Marc mentioned you in Open discussion',
    );
    expect(notificationSentence(notification({ kind: 'mention', space: null, actors: [person('Marc')] }))).toBe(
      'Marc mentioned you in Knowii',
    );
  });
});

describe('the "Following" kinds (#1497)', () => {
  const level2 = (changes: Partial<Notification>) =>
    notification({ level: 2, reason: 'follows_thread', actors: [person('Anna')], actor: person('Anna'), ...changes });

  it('parses the five level 2 kinds, the reason, the follow reason and the new target fields', () => {
    const parsed = notificationSchema.parse({
      ...resource,
      kind: 'lesson_new',
      level: 2,
      reason: 'started_course',
      follow_reason: null,
      items_count: '3',
      target: { ...resource.target, page_slug: 'lesson-2' },
    });

    expect(parsed.kind).toBe('lesson_new');
    expect(parsed.level).toBe(2);
    expect(parsed.reason).toBe('started_course');
    expect(parsed.items_count).toBe(3);
    expect(parsed.target.page_slug).toBe('lesson-2');
    for (const kind of ['thread_comment', 'thread_reply', 'space_post', 'space_chat']) {
      expect(notificationSchema.safeParse({ ...resource, kind, level: 2 }).success).toBe(true);
    }
    expect(notificationSchema.safeParse({ ...resource, reason: 'because' }).success).toBe(false);
  });

  it('builds one sentence per kind from the live counts', () => {
    expect(notificationSentence(level2({ kind: 'thread_comment', items_count: 3 }))).toBe('3 new replies in Notes workflow');
    expect(notificationSentence(level2({ kind: 'thread_comment', items_count: 1 }))).toBe('Anna replied in Notes workflow');
    expect(notificationSentence(level2({ kind: 'thread_reply', items_count: 2, title: null }))).toBe(
      '2 new replies in a thread of Open discussion',
    );
    expect(notificationSentence(level2({ kind: 'space_post', items_count: 1 }))).toBe('Anna posted in Open discussion: Notes workflow');
    expect(notificationSentence(level2({ kind: 'space_post', items_count: 4 }))).toBe('4 new posts in Open discussion');
    expect(notificationSentence(level2({ kind: 'space_chat', items_count: 9, title: null }))).toBe('New messages in Open discussion');
    expect(
      notificationSentence(
        level2({ kind: 'lesson_new', items_count: 1, title: 'Lesson one', space: { cuid: 's2', slug: 'c', name: 'Course' } }),
      ),
    ).toBe('Lesson Lesson one of Course is out');
    expect(notificationSentence(level2({ kind: 'lesson_new', items_count: 2, space: { cuid: 's2', slug: 'c', name: 'Course' } }))).toBe(
      '2 new lessons in Course',
    );
  });

  it('writes the line of a new event with its start in the zone of the reader, and the title (#1513)', () => {
    const event = level2({
      kind: 'event_new',
      reason: 'space_everything',
      title: 'Weekly review office hour',
      event_starts_at: new Date('2026-11-18T16:00:00Z'),
      space: { cuid: 's3', slug: 'office-hours', name: 'Office hours' },
    });

    expect(notificationSentence(event, 'Europe/Brussels')).toBe(
      `${formatEventStart(new Date('2026-11-18T16:00:00Z'), 'Europe/Brussels')}: Weekly review office hour`,
    );
    expect(notificationSentence(event, 'Europe/Brussels')).toContain('18 Nov, 17:00');
    expect(notificationSentence(event, 'Asia/Singapore')).toContain('19 Nov, 00:00');
    expect(notificationSentenceHasTitle(event)).toBe(true);
    expect(notificationShowsItem(event)).toBe(true);
    expect(FOLLOWING_KINDS).toContain('event_new');
    expect(notificationSentence({ ...event, event_starts_at: null })).toBe('New event in Office hours');
    expect(
      notificationSchema
        .parse({
          ...resource,
          kind: 'event_new',
          level: 2,
          reason: 'space_everything',
          event_starts_at: '2026-11-18T16:00:00.000000Z',
          target: { ...resource.target, comment_cuid: null, event_cuid: 'e1', event_slug: 'weekly-review' },
        })
        .event_starts_at?.toISOString(),
    ).toBe('2026-11-18T16:00:00.000Z');
  });

  it('shows the title once: in the sentence, or under it', () => {
    expect(notificationSentenceHasTitle(level2({ kind: 'thread_comment' }))).toBe(true);
    expect(notificationSentenceHasTitle(level2({ kind: 'space_post', items_count: 1 }))).toBe(true);
    expect(notificationSentenceHasTitle(level2({ kind: 'space_post', items_count: 3 }))).toBe(false);
    expect(notificationSentenceHasTitle(notification())).toBe(false);
  });

  it('shows no title and no excerpt for a line of several new posts or lessons (#1509)', () => {
    expect(notificationShowsItem(level2({ kind: 'space_post', items_count: 3 }))).toBe(false);
    expect(notificationShowsItem(level2({ kind: 'lesson_new', items_count: 2 }))).toBe(false);
    expect(notificationShowsItem(level2({ kind: 'space_post', items_count: 1 }))).toBe(true);
    expect(notificationShowsItem(level2({ kind: 'lesson_new', items_count: 1 }))).toBe(true);
    expect(notificationShowsItem(level2({ kind: 'thread_comment', items_count: 3 }))).toBe(true);
    expect(notificationShowsItem(notification())).toBe(true);
  });

  it('says why in a sentence for each reason, and names the link after its setting', () => {
    const space = { cuid: 's1', slug: 'open', name: 'Open discussion' };
    expect(notificationReasonSentence({ reason: 'wrote_post', follow_reason: null, space })).toBe(
      'You wrote this post, and someone replied to it.',
    );
    expect(notificationReasonSentence({ reason: 'mentioned', follow_reason: null, space })).toBe('Someone mentioned you.');
    expect(notificationReasonSentence({ reason: 'follows_thread', follow_reason: 'wrote', space })).toBe(
      'You follow this thread because you wrote it.',
    );
    expect(notificationReasonSentence({ reason: 'follows_thread', follow_reason: 'replied', space })).toBe(
      'You follow this thread because you replied in it.',
    );
    expect(notificationReasonSentence({ reason: 'follows_thread', follow_reason: 'manual', space })).toBe(
      'You follow this thread because you chose to follow it.',
    );
    expect(notificationReasonSentence({ reason: 'space_everything', follow_reason: null, space })).toContain('Open discussion');
    expect(notificationSettingsLinkName({ space, community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' } })).toBe(
      'Notification settings for Open discussion',
    );
  });
});

describe('the words of a thread and of a space level (#1497)', () => {
  it('names the five states of a thread', () => {
    expect(threadFollowLabel({ state: 'following', reason: 'wrote' })).toBe('Following (you wrote this)');
    expect(threadFollowLabel({ state: 'following', reason: 'replied' })).toBe('Following (you replied)');
    expect(threadFollowLabel({ state: 'following', reason: 'manual' })).toBe('Following');
    expect(threadFollowLabel({ state: 'stopped', reason: 'replied' })).toBe('Not following');
    expect(threadFollowLabel({ state: null, reason: null })).toBe('Not following');
    expect(threadFollowLabel({ state: 'muted', reason: null })).toBe('Muted');
  });

  it('offers Everything to a discussion space and a chat space only, and nothing to a link space', () => {
    expect(spaceNotificationLevelsFor('discussion')).toEqual(['all', 'mine', 'none']);
    expect(spaceNotificationLevelsFor('chat')).toEqual(['all', 'mine', 'none']);
    // An events space has Everything (#1513)
    expect(spaceNotificationLevelsFor('events')).toEqual(['all', 'mine', 'none']);
    expect(spaceNotificationLevelsFor('course')).toEqual(['mine', 'none']);
    expect(spaceNotificationLevelsFor('link')).toEqual([]);
    expect(SPACE_NOTIFICATION_LEVEL_LABELS.none.description).toBe('You will get nothing from this space, also when someone mentions you.');
    expect(SPACE_NOTIFICATION_LEVEL_LABELS.all.name).toBe('Everything');
    expect(SPACE_NOTIFICATION_LEVEL_LABELS.mine.name).toBe('Only what is for me');
  });
});

describe('the catch-up and the tab title (#1476)', () => {
  const line = { title: 'A title', excerpt: 'An excerpt' };

  it('shows the title first and the excerpt under it, else the excerpt first', () => {
    expect(catchUpLineHeadline(line)).toBe('A title');
    expect(catchUpLineDetail(line)).toBe('An excerpt');
    expect(catchUpLineHeadline({ title: null, excerpt: 'Only text' })).toBe('Only text');
    expect(catchUpLineDetail({ title: null, excerpt: 'Only text' })).toBeNull();
    expect(catchUpLineDetail({ title: 'A title', excerpt: '' })).toBeNull();
  });

  it('adds the bell number and the messages in the tab title, with "9+" above 9 and nothing at zero', () => {
    expect(tabTitle('Home', 0, 0)).toBe('Home');
    expect(tabTitle('Home', 2, 1)).toBe('(3) Home');
    expect(tabTitle('Home', 0, 4)).toBe('(4) Home');
    expect(tabTitle('Home', 9, 0)).toBe('(9) Home');
    expect(tabTitle('Home', 6, 4)).toBe('(9+) Home');
    expect(tabTitleBadge(-1, 0)).toBeNull();
  });

  it('knows the three kinds of lines and no count', () => {
    const parsed = catchUpSchema.safeParse({
      lines: [{ ...resource, author: null, kind: 'needs_you', date: '2026-11-15T10:00:00Z' }],
      has_more: false,
    });
    expect(parsed.success).toBe(true);
    expect(
      catchUpSchema.safeParse({ lines: [{ ...resource, author: null, kind: 'other', date: '2026-11-15T10:00:00Z' }], has_more: false })
        .success,
    ).toBe(false);
  });
});
