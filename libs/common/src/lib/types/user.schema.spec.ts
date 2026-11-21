import { describe, expect, it } from 'vitest';
import { SOCIAL_MEDIA_LINK_PROPERTIES } from './social-media-link.schema';
import { currentUserSchema, PROFILE_VISIBILITY_FIELDS, resolveProfileFieldVisibility, userProfileSchema } from './user.schema';

describe('resolveProfileFieldVisibility (#1076)', () => {
  it('shows every field but the phone by default', () => {
    const visibility = resolveProfileFieldVisibility(null);

    expect(Object.keys(visibility)).toHaveLength(PROFILE_VISIBILITY_FIELDS.length);
    expect(visibility.phone).toBe(false);
    expect(visibility.bio).toBe(true);
    expect(visibility.social_link_github).toBe(true);
  });

  it('keeps the stored choices and drops unknown keys', () => {
    const visibility = resolveProfileFieldVisibility({ phone: true, bio: false, is_admin: true });

    expect(visibility.phone).toBe(true);
    expect(visibility.bio).toBe(false);
    expect(visibility).not.toHaveProperty('is_admin');
  });
});

describe('the headline of a profile (#1688)', () => {
  it('is one of the fields with a choice, shown by default', () => {
    expect(PROFILE_VISIBILITY_FIELDS).toContain('headline');
    expect(resolveProfileFieldVisibility(null).headline).toBe(true);
    expect(resolveProfileFieldVisibility(undefined).headline).toBe(true);
    // Choices that were stored before the headline existed
    expect(resolveProfileFieldVisibility({ bio: false, phone: true }).headline).toBe(true);
  });

  it('keeps a stored choice', () => {
    expect(resolveProfileFieldVisibility({ headline: false }).headline).toBe(false);
    expect(resolveProfileFieldVisibility({ headline: true, bio: false })).toMatchObject({ headline: true, bio: false });
  });

  it('is in the profile of the member, null when there is none', () => {
    const links = Object.fromEntries(SOCIAL_MEDIA_LINK_PROPERTIES.map((property) => [property, null]));
    const base = { name: 'Ada Lovelace', username: 'ada', bio: null, location: null, phone: null, ...links };

    expect(userProfileSchema.parse({ ...base, headline: null }).headline).toBeNull();
    expect(userProfileSchema.parse({ ...base, headline: 'Product designer' }).headline).toBe('Product designer');
    expect(userProfileSchema.safeParse(base).success).toBe(false);
  });
});

describe('the own account (#1476)', () => {
  const base = {
    cuid: 'u1',
    name: 'Ada',
    email: 'ada@example.com',
    username: 'ada',
    two_factor_enabled: false,
    two_factor_confirmed_at: null,
    email_verified_at: null,
    created_at: '2026-11-01T10:00:00Z',
    updated_at: '2026-11-01T10:00:00Z',
  };

  it('holds the time zone (null when unknown) and the pop-up switch, and accepts an old session without them', () => {
    expect(currentUserSchema.parse({ ...base, time_zone: 'Europe/Brussels', notification_popups: false })).toMatchObject({
      time_zone: 'Europe/Brussels',
      notification_popups: false,
    });
    expect(currentUserSchema.parse({ ...base, time_zone: null }).time_zone).toBeNull();
    expect(currentUserSchema.parse(base).time_zone).toBeUndefined();
  });

  it('holds the new email address that waits for its confirmation (#1706), null or absent when none', () => {
    const pending = currentUserSchema.parse({
      ...base,
      pending_email: 'ada.new@example.com',
      pending_email_requested_at: '2026-11-21T10:00:00Z',
    });
    expect(pending.pending_email).toBe('ada.new@example.com');
    expect(pending.pending_email_requested_at?.toISOString()).toBe('2026-11-21T10:00:00.000Z');
    expect(currentUserSchema.parse({ ...base, pending_email: null, pending_email_requested_at: null }).pending_email).toBeNull();
    expect(currentUserSchema.parse(base).pending_email).toBeUndefined();
    expect(currentUserSchema.safeParse({ ...base, pending_email: 'not-an-address' }).success).toBe(false);
  });
});
