import { describe, expect, it } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import type { PresenceMember } from '@knowii/common';
import { usePresence } from '@knowii/common';
import { emitPresenceHere, emitWhisper } from '@/test-utils/fake-echo';

const channel = 'community.c1.space.s1.presence';
const member = (cuid: string): PresenceMember => ({ cuid, name: cuid.toUpperCase(), username: cuid, avatar_url: null });

/**
 * The presence of a chat page that shows the messages m1 and m2
 */
function renderPresence() {
  let renders = 0;
  const shown = new Set(['m1', 'm2']);
  const hook = renderHook(() => {
    renders += 1;
    return usePresence({
      channel,
      visible: true,
      fetchMembers: () => Promise.resolve({ ok: true, message: '', data: [] }),
      currentUserCuid: 'me',
      acceptsScope: (scope) => shown.has(scope),
    });
  });
  act(() => emitPresenceHere(channel, [member('me'), member('ada')]));

  return { hook, renders: () => renders };
}

describe('the typing signal of a chat (ADR 0027)', () => {
  it('shows a member who types in the main conversation or in a thread of a message of the page', () => {
    const { hook } = renderPresence();

    act(() => emitWhisper(channel, 'typing', { scope: '', typing: true }, 'ada'));
    act(() => emitWhisper(channel, 'typing', { scope: 'm2', typing: true }, 'ada'));

    expect(hook.result.current.typists('').map((typist) => typist.cuid)).toEqual(['ada']);
    expect(hook.result.current.typists('m2').map((typist) => typist.cuid)).toEqual(['ada']);
  });

  it('ignores a thread that the page does not show: the page does not render again for it', () => {
    const { hook, renders } = renderPresence();
    const before = renders();

    act(() => {
      for (let index = 0; index < 50; index++) {
        emitWhisper(channel, 'typing', { scope: `unknown-${index}`, typing: true }, 'ada');
      }
    });

    expect(renders()).toBe(before);
    expect(hook.result.current.typists('unknown-1')).toEqual([]);
  });

  it('forgets a thread where nobody types any more', () => {
    const { hook, renders } = renderPresence();

    act(() => emitWhisper(channel, 'typing', { scope: 'm1', typing: true }, 'ada'));
    act(() => emitWhisper(channel, 'typing', { scope: 'm1', typing: false }, 'ada'));
    const after = renders();
    // A stop for a thread that holds nobody changes nothing
    act(() => emitWhisper(channel, 'typing', { scope: 'm1', typing: false }, 'ada'));

    expect(hook.result.current.typists('m1')).toEqual([]);
    expect(renders()).toBe(after);
  });
});
