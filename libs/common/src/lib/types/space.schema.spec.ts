import { accessChangeOnMove, openingAccessGroups, spaceGroupHasAccessGroups } from './access-group.schema';
import type { AccessGroup } from './access-group.schema';
import { createSpaceFormWithGroupsSchema, moveItem, spaceFormSchema, spaceFormWithGroupsSchema } from './space.schema';

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
    url: '',
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

  it('accepts a groups space without a group of its own when its space group has one (#1422)', () => {
    const schema = createSpaceFormWithGroupsSchema((cuid) => cuid === 'sg1');
    const groups = { ...valid, visibility: 'groups' };
    expect(schema.safeParse({ ...groups, space_group_cuid: 'sg1' }).success).toBe(true);
    expect(schema.safeParse({ ...groups, space_group_cuid: 'sg2' }).success).toBe(false);
    expect(schema.safeParse({ ...groups, space_group_cuid: 'sg2', access_groups: ['g1'] }).success).toBe(true);
  });

  it('needs an http or https URL for a link space (#1294)', () => {
    const link = { ...valid, type: 'link' };
    expect(spaceFormWithGroupsSchema.safeParse(link).success).toBe(false);
    expect(spaceFormWithGroupsSchema.safeParse({ ...link, url: 'ftp://example.com' }).success).toBe(false);
    expect(spaceFormWithGroupsSchema.safeParse({ ...link, url: 'https://shop.example.com' }).success).toBe(true);
    // The other types ignore the field
    expect(spaceFormWithGroupsSchema.safeParse(valid).success).toBe(true);
  });
});

describe('access groups and space groups (#1422)', () => {
  const group = (cuid: string, name: string, spaceGroups: string[]): AccessGroup => ({
    cuid,
    name,
    description: '',
    created_at: '',
    space_groups: spaceGroups.map((spaceGroup) => ({ cuid: spaceGroup, name: spaceGroup })),
  });
  const groups = [group('g1', 'Alumni', ['sg1']), group('g2', 'Paid members', ['sg1', 'sg2']), group('g3', 'Team', [])];

  it('tells where each opening group comes from', () => {
    expect(openingAccessGroups(groups, ['g2', 'g3'], 'sg1')).toEqual([
      { cuid: 'g1', name: 'Alumni', sources: ['space_group'] },
      { cuid: 'g2', name: 'Paid members', sources: ['space', 'space_group'] },
      { cuid: 'g3', name: 'Team', sources: ['space'] },
    ]);
    expect(openingAccessGroups(groups, [], 'sg3')).toEqual([]);
    expect(spaceGroupHasAccessGroups(groups, 'sg2')).toBe(true);
    expect(spaceGroupHasAccessGroups(groups, 'sg3')).toBe(false);
  });

  it('names the groups that gain and lose a space when it moves', () => {
    const change = accessChangeOnMove(groups, { directCuids: [], spaceGroupCuid: 'sg1' }, { directCuids: [], spaceGroupCuid: 'sg2' });
    expect(change.gain).toEqual([]);
    expect(change.lose.map((item) => item.name)).toEqual(['Alumni']);

    // A group of the space itself stays, whatever the space group
    const kept = accessChangeOnMove(groups, { directCuids: ['g1'], spaceGroupCuid: 'sg1' }, { directCuids: ['g1'], spaceGroupCuid: 'sg3' });
    expect(kept.lose.map((item) => item.name)).toEqual(['Paid members']);
    expect(kept.gain).toEqual([]);

    const gained = accessChangeOnMove(groups, { directCuids: [], spaceGroupCuid: 'sg3' }, { directCuids: [], spaceGroupCuid: 'sg2' });
    expect(gained.gain.map((item) => item.name)).toEqual(['Paid members']);
  });
});
