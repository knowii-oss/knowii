import type { PresenceMember } from '@knowii/common';
import { typingText } from '@knowii/common';

/**
 * Who is typing (#1187). A polite live region: screen readers hear the change without losing their place. It keeps
 * its height, so the field below does not move.
 */
export function TypingIndicator({ typists }: { typists: PresenceMember[] }) {
  const text = typingText(typists.map((member) => member.name));

  return (
    <p aria-live="polite" className="min-h-5 text-xs text-muted italic">
      {text}
    </p>
  );
}
