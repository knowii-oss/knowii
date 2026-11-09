import { moveItem, spaceFormSchema, spaceFormWithGroupsSchema } from './space.schema';

describe('moveItem', () => {
  it('moves an item one step up or down', () => {
    expect(moveItem(['a', 'b', 'c'], 0, 1)).toEqual(['b', 'a', 'c']);
    expect(moveItem(['a', 'b', 'c'], 2, -1)).toEqual(['a', 'c', 'b']);
  });

  it('keeps the list when the move goes out of bounds', () => {
    const items = ['a', 'b'];
    expect(moveItem(items, 0, -1)).toEqual(['a', 'b']);
    expect(moveItem(items, 1, 1)).toEqual(['a', 'b']);
    expect(moveItem(items, 1, 1)).not.toBe(items);
  });
});

describe('spaceFormSchema', () => {
  const valid = {
    name: 'Announcements',
    description: '',
    emoji: '📣',
    type: 'discussion',
    visibility: 'public',
    posting_policy: 'moderators',
    space_group_cuid: 'abc',
    access_groups: [] as string[],
  };

  it('accepts a valid space', () => {
    expect(spaceFormSchema.safeParse(valid).success).toBe(true);
    expect(spaceFormSchema.safeParse({ ...valid, emoji: '' }).success).toBe(true);
  });

  it('rejects text as an emoji, an unknown type and a missing group', () => {
    expect(spaceFormSchema.safeParse({ ...valid, emoji: 'hi' }).success).toBe(false);
    expect(spaceFormSchema.safeParse({ ...valid, type: 'forum' }).success).toBe(false);
    expect(spaceFormSchema.safeParse({ ...valid, space_group_cuid: '' }).success).toBe(false);
  });

  it('needs an access group for a groups space (#1241)', () => {
    expect(spaceFormWithGroupsSchema.safeParse({ ...valid, visibility: 'groups' }).success).toBe(false);
    expect(spaceFormWithGroupsSchema.safeParse({ ...valid, visibility: 'groups', access_groups: ['g1'] }).success).toBe(true);
    expect(spaceFormWithGroupsSchema.safeParse({ ...valid, visibility: 'members' }).success).toBe(true);
  });
});
