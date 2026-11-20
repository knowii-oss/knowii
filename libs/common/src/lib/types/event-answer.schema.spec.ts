import { describe, expect, it } from 'vitest';
import {
  EVENT_ANSWERS,
  eventAnswerCountsSchema,
  eventAnswerMemberSchema,
  eventAnswerSchema,
  eventAnswerValueSchema,
} from './event-answer.schema';

describe('eventAnswerSchema (#1515)', () => {
  it('knows the three answers, and no "maybe"', () => {
    expect(EVENT_ANSWERS).toEqual(['yes', 'replay', 'no']);
    expect(eventAnswerValueSchema.options).toEqual(['yes', 'replay', 'no']);
    expect(eventAnswerValueSchema.safeParse('maybe').success).toBe(false);
  });

  it('reads the answer of the member as EventAnswerResource.php sends it, with the time of the last change', () => {
    const answer = eventAnswerSchema.parse({ answer: 'replay', updated_at: '2026-11-10T09:05:00.000000Z' });

    expect(answer.answer).toBe('replay');
    expect(answer.updated_at?.toISOString()).toBe('2026-11-10T09:05:00.000Z');
  });

  it('reads a member who did not answer: null, never an invented answer', () => {
    const none = eventAnswerSchema.parse({ answer: null, updated_at: null });

    expect(none.answer).toBeNull();
    expect(none.updated_at).toBeNull();
    expect(eventAnswerSchema.safeParse({ answer: 'maybe', updated_at: null }).success).toBe(false);
    expect(eventAnswerSchema.safeParse({ updated_at: null }).success).toBe(false);
  });

  it('carries no member and no event: the keys are the answer and its date', () => {
    const parsed = eventAnswerSchema.parse({
      answer: 'yes',
      updated_at: '2026-11-10T09:05:00.000000Z',
      user: { name: 'Ada' },
      event_cuid: 'e1',
    });

    expect(Object.keys(parsed)).toEqual(['answer', 'updated_at']);
  });

  it('reads the counts of the list of the host, and a member of that list', () => {
    expect(eventAnswerCountsSchema.parse({ yes: 12, replay: 3, no: 0 })).toEqual({ yes: 12, replay: 3, no: 0 });
    expect(eventAnswerCountsSchema.safeParse({ yes: 12, replay: 3 }).success).toBe(false);
    expect(eventAnswerCountsSchema.safeParse({ yes: -1, replay: 0, no: 0 }).success).toBe(false);

    const member = eventAnswerMemberSchema.parse({ cuid: 'u1', name: 'Ada Lovelace', username: 'ada', avatar_url: null });
    expect(member.username).toBe('ada');
  });
});
