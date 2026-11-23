import { describe, expect, it } from 'vitest';
import { emailStatusSchema } from './email-status.schema';

describe('emailStatusSchema (#1478)', () => {
  it('reads the email status of the API', () => {
    const parsed = emailStatusSchema.safeParse({
      suppressed: true,
      reason: 'complaint',
      since: '2026-11-23T10:00:00.000000Z',
      address_hint: 'ada@example.com',
      email_choices: [{ group: 'events', state: 'off' }],
    });

    expect(parsed.success).toBe(true);
  });

  it('refuses a reason that the server does not send', () => {
    const parsed = emailStatusSchema.safeParse({
      suppressed: true,
      reason: 'other',
      since: null,
      address_hint: 'a@example.com',
      email_choices: [],
    });

    expect(parsed.success).toBe(false);
  });
});
