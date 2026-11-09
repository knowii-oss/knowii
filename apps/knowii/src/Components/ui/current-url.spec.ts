import { describe, expect, it } from 'vitest';
import { isCurrentUrl } from './current-url';

describe('isCurrentUrl', () => {
  it('matches the same path, with or without the host, the query or a trailing slash', () => {
    expect(isCurrentUrl('http://localhost:4200/dashboard', '/dashboard')).toBe(true);
    expect(isCurrentUrl('/dashboard', '/dashboard/?tab=1#top')).toBe(true);
  });

  it('does not match another page in exact mode', () => {
    expect(isCurrentUrl('/dashboard', '/dashboard/settings')).toBe(false);
    expect(isCurrentUrl('/user/profile', '/user/api-tokens')).toBe(false);
  });

  it('matches the pages below the link in prefix mode, but not a path that only starts with the same letters', () => {
    expect(isCurrentUrl('/communities/knowii', '/communities/knowii/spaces/general', 'prefix')).toBe(true);
    expect(isCurrentUrl('/communities/knowii', '/communities/knowii-two', 'prefix')).toBe(false);
  });
});
