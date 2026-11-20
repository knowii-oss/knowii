import { describe, expect, it } from 'vitest';
import { API_TOKEN_PERMISSION_LABELS, API_TOKEN_PERMISSIONS } from './jetstream-inertia.intf';

describe('the abilities of API tokens (ADR 0022)', () => {
  it('has a label for every ability, and no label twice', () => {
    const labels = API_TOKEN_PERMISSIONS.map((permission) => API_TOKEN_PERMISSION_LABELS[permission]);

    expect(Object.keys(API_TOKEN_PERMISSION_LABELS).sort()).toEqual([...API_TOKEN_PERMISSIONS].sort());
    expect(labels.every((label) => label.trim().length > 0)).toBe(true);
    expect(new Set(labels).size).toBe(labels.length);
  });

  it('names the ability that reads the answers to events, apart from the abilities of the events (#1515)', () => {
    expect(API_TOKEN_PERMISSIONS).toContain('event-answers:read');
    expect(API_TOKEN_PERMISSION_LABELS['event-answers:read']).toBe('Read the answers to the events that you host or manage');
    // It reads the answers of other members: the label of the events does not promise it
    expect(API_TOKEN_PERMISSION_LABELS['events:read']).not.toMatch(/answer/i);
  });
});
