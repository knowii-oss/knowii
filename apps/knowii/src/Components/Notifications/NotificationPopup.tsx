import type { FocusEvent, KeyboardEvent } from 'react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import { Link } from '@inertiajs/react';
import { FaXmark } from 'react-icons/fa6';
import { Button } from '@/Components/ui';

// It stays 8 seconds plus 1 second per 20 characters, 20 seconds at most (design 5.3.3)
export const POPUP_BASE_MS = 8000;
export const POPUP_PER_20_CHARACTERS_MS = 1000;
export const POPUP_MAX_MS = 20_000;

export function popupDurationMs(text: string): number {
  return Math.min(POPUP_MAX_MS, POPUP_BASE_MS + Math.ceil(text.length / 20) * POPUP_PER_20_CHARACTERS_MS);
}

const subscribeToVisibility = (onChange: () => void) => {
  document.addEventListener('visibilitychange', onChange);
  return () => document.removeEventListener('visibilitychange', onChange);
};
const isTabVisible = () => document.visibilityState === 'visible';

export interface NotificationPopupProps {
  // The sentence of the line, or "<N> new for you"
  text: string;
  // Where the sentence leads: the item of the line, or the page "Notifications" for several lines
  href: string | null;
  // The link was followed
  onOpen: () => void;
  onClose: () => void;
}

/**
 * The pop-up of a new line that needs the member (#1476, design 5.3 and 9.4): the sentence as one link, and a close button.
 * Nothing else. It never takes the focus. The text is not a live region: the one polite region of the layout says it once.
 *
 * The timer stops while the pointer is on it, while the focus is in it and while the tab is hidden. Escape closes it only
 * when the focus is in it. It sits at the top, so that it covers neither a composer nor the bottom navigation, and it does
 * not slide with `prefers-reduced-motion`.
 */
export function NotificationPopup({ text, href, onOpen, onClose }: NotificationPopupProps) {
  const visible = useSyncExternalStore(subscribeToVisibility, isTabVisible, () => true);
  const [hovered, setHovered] = useState(false);
  const [focused, setFocused] = useState(false);
  const running = visible && !hovered && !focused;

  // The time that is left, and when the timer started
  const remaining = useRef(popupDurationMs(text));
  const closeRef = useRef(onClose);
  useEffect(() => {
    closeRef.current = onClose;
  });

  useEffect(() => {
    if (!running) {
      return;
    }
    const startedAt = Date.now();
    const timer = setTimeout(() => closeRef.current(), remaining.current);
    return () => {
      clearTimeout(timer);
      remaining.current = Math.max(0, remaining.current - (Date.now() - startedAt));
    };
  }, [running]);

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    // The event comes from inside the pop-up: the focus is in it
    if (event.key === 'Escape') {
      event.stopPropagation();
      onClose();
    }
  };
  const onBlur = (event: FocusEvent<HTMLDivElement>) => {
    if (!event.currentTarget.contains(event.relatedTarget)) {
      setFocused(false);
    }
  };

  return (
    <div
      role="group"
      aria-label="New for you"
      data-testid="notification-popup"
      onPointerEnter={() => setHovered(true)}
      onPointerLeave={() => setHovered(false)}
      onFocus={() => setFocused(true)}
      onBlur={onBlur}
      onKeyDown={onKeyDown}
      className="fixed top-20 right-4 left-4 z-50 flex items-start gap-2 rounded-xl border border-border bg-surface-elevated p-3 text-primary shadow-2xl motion-safe:animate-toast-in sm:left-auto sm:w-96"
    >
      <p className="min-w-0 grow text-sm font-bold break-words">
        {href ? (
          <Link
            href={href}
            onClick={onOpen}
            className="rounded underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-primary"
          >
            {text}
          </Link>
        ) : (
          text
        )}
      </p>
      <Button variant="ghost" size="sm" iconOnly aria-label="Close the notification" onClick={onClose} className="shrink-0">
        <FaXmark aria-hidden="true" />
      </Button>
    </div>
  );
}
