import type { Notification } from './notification.schema';
import {
  notificationActorNames,
  notificationBadge,
  notificationBellName,
  notificationSchema,
  notificationSentence,
} from './notification.schema';

const person = (name: string) => ({ cuid: name.toLowerCase(), name, username: name.toLowerCase(), avatar_url: null });

const resource = {
  cuid: 'n1',
  kind: 'post_comment',
  level: 1,
  community: { cuid: 'c1', slug: 'knowii', name: 'Knowii' },
  space: { cuid: 's1', slug: 'open', name: 'Open discussion' },
  actor: person('Anna'),
  actors: [person('Anna')],
  others_count: 0,
  title: 'Notes workflow',
  excerpt: 'I use one inbox note per day',
  target: {
    post_slug: 'notes-workflow',
    comment_cuid: 'k1',
    chat_message_cuid: null,
    thread_cuid: null,
    resource_cuid: null,
    resource_slug: null,
  },
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
