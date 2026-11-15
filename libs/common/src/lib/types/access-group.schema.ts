import { z } from 'zod';
import { MAX_LENGTH_ACCESS_GROUP_DESCRIPTION, MAX_LENGTH_ACCESS_GROUP_NAME, MIN_LENGTH_ACCESS_GROUP_NAME } from '../constants';

/**
 * An access group (#1241, ADR 0025): a named group of members that opens the spaces with the "groups" visibility.
 * WARNING: aligned with AccessGroupResource.php
 */
export const accessGroupSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  description: z.string(),
  members_count: z.number().int().optional(),
  spaces_count: z.number().int().optional(),
  // The space groups that the group opens whole (#1422, ADR 0041)
  space_groups_count: z.number().int().optional(),
  space_groups: z.array(z.object({ cuid: z.string(), name: z.string() })).optional(),
  created_at: z.string(),
});
export type AccessGroup = z.infer<typeof accessGroupSchema>;

/**
 * A space group that an access group opens whole (#1422). WARNING: aligned with SpaceGroupResource.php (the fields
 * that this screen reads)
 */
export const accessGroupSpaceGroupSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  position: z.number().int(),
});
export type AccessGroupSpaceGroup = z.infer<typeof accessGroupSpaceGroupSchema>;

// WARNING: aligned with AccessGroupInputRules.php
export const accessGroupFormSchema = z.object({
  name: z
    .string()
    .trim()
    .min(MIN_LENGTH_ACCESS_GROUP_NAME, {
      error: `Use between ${MIN_LENGTH_ACCESS_GROUP_NAME} and ${MAX_LENGTH_ACCESS_GROUP_NAME} characters`,
    })
    .max(MAX_LENGTH_ACCESS_GROUP_NAME, { error: `The name is too long (maximum ${MAX_LENGTH_ACCESS_GROUP_NAME} characters)` }),
  description: z.string().max(MAX_LENGTH_ACCESS_GROUP_DESCRIPTION, {
    error: `The description is too long (maximum ${MAX_LENGTH_ACCESS_GROUP_DESCRIPTION} characters)`,
  }),
});
export type AccessGroupForm = z.infer<typeof accessGroupFormSchema>;

/**
 * Who to add to a group: exactly one of the user cuid, the username or the email address
 */
export type AccessGroupMemberInput = { member: string } | { username: string } | { email: string };

/**
 * Where the opening of a space comes from (#1422, ADR 0041): a group given to the space itself, or a group given to
 * its space group.
 */
export type AccessSource = 'space' | 'space_group';

export const ACCESS_SOURCE_LABELS: Record<AccessSource, string> = {
  space: 'this space',
  space_group: 'its space group',
};

export interface OpeningAccessGroup {
  cuid: string;
  name: string;
  sources: AccessSource[];
}

/**
 * The access groups that open a space, with where each comes from. `directCuids` are the groups given to the space,
 * `spaceGroupCuid` is its space group. WARNING: aligned with Space::openingAccessGroupIds() (the server decides)
 */
export function openingAccessGroups(groups: AccessGroup[], directCuids: string[], spaceGroupCuid: string): OpeningAccessGroup[] {
  const opening: OpeningAccessGroup[] = [];
  for (const group of groups) {
    const sources: AccessSource[] = [];
    if (directCuids.includes(group.cuid)) {
      sources.push('space');
    }
    if ((group.space_groups ?? []).some((spaceGroup) => spaceGroup.cuid === spaceGroupCuid)) {
      sources.push('space_group');
    }
    if (sources.length > 0) {
      opening.push({ cuid: group.cuid, name: group.name, sources });
    }
  }
  return opening;
}

/**
 * Whether the access groups of a space group open its spaces (the rule "a groups space needs a group", #1422)
 */
export function spaceGroupHasAccessGroups(groups: AccessGroup[], spaceGroupCuid: string): boolean {
  return groups.some((group) => (group.space_groups ?? []).some((spaceGroup) => spaceGroup.cuid === spaceGroupCuid));
}

/**
 * Who gains and who loses a "groups" space when it is saved with another space group (and maybe other groups of its
 * own): the groups that open it after and not before gain it, the groups that opened it before and not after lose it.
 */
export function accessChangeOnMove(
  groups: AccessGroup[],
  before: { directCuids: string[]; spaceGroupCuid: string },
  after: { directCuids: string[]; spaceGroupCuid: string },
): { gain: OpeningAccessGroup[]; lose: OpeningAccessGroup[] } {
  const opened = openingAccessGroups(groups, before.directCuids, before.spaceGroupCuid);
  const opens = openingAccessGroups(groups, after.directCuids, after.spaceGroupCuid);
  return {
    gain: opens.filter((group) => !opened.some((other) => other.cuid === group.cuid)),
    lose: opened.filter((group) => !opens.some((other) => other.cuid === group.cuid)),
  };
}
