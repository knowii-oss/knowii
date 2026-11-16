import { describe, expect, it } from 'vitest';
import { currentUserSchema, PROFILE_VISIBILITY_FIELDS, resolveProfileFieldVisibility } from './user.schema';

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
});
