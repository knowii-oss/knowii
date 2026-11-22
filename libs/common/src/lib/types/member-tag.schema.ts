import { z } from 'zod';
import { MAX_LENGTH_MEMBER_TAG_NAME, MIN_LENGTH_MEMBER_TAG_NAME } from '../constants';

/**
 * Who sees a member tag (#1389, ADR 0060): the members of the community, or the managers only (the default). Never
 * "public": a tag never shows to a visitor. WARNING: aligned with KnowiiMemberTagVisibility.php
 */
export const MEMBER_TAG_VISIBILITIES = ['members', 'managers'] as const;
export const memberTagVisibilitySchema = z.enum(MEMBER_TAG_VISIBILITIES);
export type MemberTagVisibility = z.infer<typeof memberTagVisibilitySchema>;
export const DEFAULT_MEMBER_TAG_VISIBILITY: MemberTagVisibility = 'managers';

export const MEMBER_TAG_VISIBILITY_LABELS: Record<MemberTagVisibility, string> = {
  managers: 'Managers only',
  members: 'Members',
};

/**
 * The palette of the member tags: one key of eight, each a token `--color-tag-<key>` of each theme (styles.css).
 * WARNING: aligned with KnowiiMemberTagColor.php
 */
export const MEMBER_TAG_COLORS = ['gray', 'red', 'orange', 'yellow', 'green', 'teal', 'blue', 'purple'] as const;
export const memberTagColorSchema = z.enum(MEMBER_TAG_COLORS);
export type MemberTagColor = z.infer<typeof memberTagColorSchema>;
export const DEFAULT_MEMBER_TAG_COLOR: MemberTagColor = 'gray';

export const MEMBER_TAG_COLOR_LABELS: Record<MemberTagColor, string> = {
  gray: 'Gray',
  red: 'Red',
  orange: 'Orange',
  yellow: 'Yellow',
  green: 'Green',
  teal: 'Teal',
  blue: 'Blue',
  purple: 'Purple',
};

/**
 * A member tag. `members_count` is a number for the people who see every tag, in the list of the tags, and null
 * elsewhere. WARNING: aligned with MemberTagResource.php
 */
export const memberTagSchema = z.object({
  cuid: z.string(),
  name: z.string(),
  color: memberTagColorSchema,
  visibility: memberTagVisibilitySchema,
  members_count: z.number().int().nullable(),
  created_at: z.string(),
});
export type MemberTag = z.infer<typeof memberTagSchema>;

/**
 * The name as the server stores it: trimmed, each run of spaces made one. WARNING: aligned with MemberTag::cleanName()
 */
export function cleanMemberTagName(name: string): string {
  return name.replace(/\p{Zs}+/gu, ' ').trim();
}

// WARNING: aligned with MemberTagInputRules.php
export const memberTagFormSchema = z.object({
  name: z
    .string()
    .transform(cleanMemberTagName)
    .pipe(
      z
        .string()
        .min(MIN_LENGTH_MEMBER_TAG_NAME, {
          error: `Use between ${MIN_LENGTH_MEMBER_TAG_NAME} and ${MAX_LENGTH_MEMBER_TAG_NAME} characters`,
        })
        // The server counts characters, not UTF-16 units
        .refine((name) => [...name].length <= MAX_LENGTH_MEMBER_TAG_NAME, {
          error: `The name is too long (maximum ${MAX_LENGTH_MEMBER_TAG_NAME} characters)`,
        })
        .refine((name) => !/[\p{Cc}\p{Zl}\p{Zp}]/u.test(name), { error: 'Write the name on one line' })
        // No format character (an invisible space, a direction mark): two tags never look the same
        .refine((name) => !/\p{Cf}/u.test(name), { error: 'Remove the invisible characters from the name' }),
    ),
  color: memberTagColorSchema,
  visibility: memberTagVisibilitySchema,
});
export type MemberTagFormInput = z.input<typeof memberTagFormSchema>;
export type MemberTagForm = z.output<typeof memberTagFormSchema>;

/**
 * Who gets a tag: exactly one of the user cuid or the username (never an email address)
 */
export type MemberTagMemberInput = { member: string } | { username: string };

/**
 * The text of the form of a tag (#1389, decision 35 of ADR 0060). Approved word for word by the owner: change no word.
 */
export const MEMBER_TAG_PERSONAL_DATA_NOTICE =
  'A tag is personal data about each member who has it. Do not record health, religion, political opinions or other sensitive facts in a tag. Each member can read all their tags in the export of their data.';

/**
 * The tags of a member, by name without the case, as the server sorts them
 */
export function sortMemberTags(tags: MemberTag[]): MemberTag[] {
  return [...tags].sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()) || a.cuid.localeCompare(b.cuid));
}
