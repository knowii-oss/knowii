import { describe, expect, it } from 'vitest';
import { PUBLIC_CHAT_SPACE_FORM_NOTE, PUBLIC_SPACE_FORM_WARNING } from './PublicSpaceNotice';

describe('the texts of the space form for a public space (#1387)', () => {
  it('has the texts of the space form', () => {
    expect(PUBLIC_SPACE_FORM_WARNING).toBe(
      'Anyone on the internet can read this space, without an account, with the names and pictures of the authors.',
    );
    expect(PUBLIC_CHAT_SPACE_FORM_NOTE).toBe('A chat space stays for signed-in people.');
  });
});
