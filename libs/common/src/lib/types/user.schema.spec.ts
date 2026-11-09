import { describe, expect, it } from 'vitest';
import { PROFILE_VISIBILITY_FIELDS, resolveProfileFieldVisibility } from './user.schema';

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
