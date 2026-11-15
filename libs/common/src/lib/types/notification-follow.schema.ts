import { z } from 'zod';

/**
 * Why a member follows a thread. WARNING: aligned with KnowiiFollowReason.php
 */
export const followReasonSchema = z.enum(['wrote', 'replied', 'manual']);
export type FollowReason = z.infer<typeof followReasonSchema>;

/**
 * What a member chose for a thread. WARNING: aligned with KnowiiFollowState.php
 */
export const followStateSchema = z.enum(['following', 'muted', 'stopped']);
export type FollowState = z.infer<typeof followStateSchema>;

/**
 * The follow state of a thread for the current member: null without a row. WARNING: aligned with ThreadFollowApiController.php
 */
export const threadFollowSchema = z.object({
  state: followStateSchema.nullable(),
  reason: followReasonSchema.nullable(),
});
export type ThreadFollow = z.infer<typeof threadFollowSchema>;

/**
 * The words of the thread state (design 2.2.1, 2.2.2): "Following (you wrote this)", "Following (you replied)", "Following",
 * "Not following" and "Muted"
 */
export function threadFollowLabel(follow: ThreadFollow): string {
  if (follow.state === 'muted') {
    return 'Muted';
  }
  if (follow.state === 'following') {
    if (follow.reason === 'wrote') {
      return 'Following (you wrote this)';
    }
    return follow.reason === 'replied' ? 'Following (you replied)' : 'Following';
  }
  return 'Not following';
}

/**
 * How close a member is to a space. WARNING: aligned with KnowiiSpaceNotificationLevel.php
 */
export const spaceNotificationLevelSchema = z.enum(['all', 'mine', 'none']);
export type SpaceNotificationLevel = z.infer<typeof spaceNotificationLevelSchema>;
// In the order of the screens: the closest first
export const SPACE_NOTIFICATION_LEVELS_ORDER: readonly SpaceNotificationLevel[] = ['all', 'mine', 'none'];

/**
 * The words and the sentence of each level (design 2.2). "Muted" says the same thing at every scope.
 */
export const SPACE_NOTIFICATION_LEVEL_LABELS: Record<SpaceNotificationLevel, { name: string; description: string }> = {
  all: {
    name: 'Everything',
    description: 'You get a line for every new post here. In a chat space the lines are bundled, one per space.',
  },
  mine: {
    name: 'Only what is for me',
    description: 'You are told when someone replies to you, mentions you, or writes in a thread that you follow.',
  },
  none: { name: 'Muted', description: 'You will get nothing from this space, also when someone mentions you.' },
};

/**
 * The levels that a member can choose for a space type. "Everything" exists for discussion spaces and chat spaces only; a
 * link space has no level (WARNING: aligned with ChooseSpaceLevels.php).
 */
export function spaceNotificationLevelsFor(spaceType: string): SpaceNotificationLevel[] {
  if (spaceType === 'link') {
    return [];
  }
  return spaceType === 'discussion' || spaceType === 'chat' ? ['all', 'mine', 'none'] : ['mine', 'none'];
}

/**
 * The level of the current member for a space. WARNING: aligned with ChooseSpaceLevels::present()
 */
export const spaceLevelSchema = z.object({
  space_cuid: z.string(),
  // The level that applies
  level: spaceNotificationLevelSchema,
  // Whether the member chose it
  chosen: z.boolean(),
  // The level that applies without a choice
  default: spaceNotificationLevelSchema,
});
export type SpaceLevel = z.infer<typeof spaceLevelSchema>;

/**
 * One line of the levels of a community. WARNING: aligned with ChooseSpaceLevels::forCommunity()
 */
export const communitySpaceLevelSchema = spaceLevelSchema.extend({
  space_name: z.string(),
  space_slug: z.string(),
  space_type: z.string(),
  space_group_cuid: z.string(),
  space_group_name: z.string(),
});
export type CommunitySpaceLevel = z.infer<typeof communitySpaceLevelSchema>;
