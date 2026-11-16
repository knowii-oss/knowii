import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from 'react';
import { notificationBadge, notificationBellName, useTypedPage } from '@knowii/common';
import { FaBell } from 'react-icons/fa';
import { Button, Drawer } from '@/Components/ui';
import { NotificationsPanel } from './NotificationsPanel';
import { useNotifications } from './NotificationsProvider';

const PANEL_ID = 'notifications-panel';
const HEADING_ID = 'notifications-heading';
// The phone layout of the app starts below the `md` breakpoint (960 px, styles.css): there the panel is a drawer
const NARROW_QUERY = '(max-width: 959px)';

const subscribeToNarrow = (onChange: () => void) => {
  const media = window.matchMedia?.(NARROW_QUERY);
  media?.addEventListener('change', onChange);
  return () => media?.removeEventListener('change', onChange);
};
const isNarrow = () => window.matchMedia?.(NARROW_QUERY).matches ?? false;

/**
 * The bell of the header and its Notifications panel (#1484, design 5.1, 5.2, 9.1, 9.2). For a signed-in member only
 * (the layout renders it inside the NotificationsProvider).
 *
 * The number is the value of the server. The button has `aria-expanded` and `aria-controls`, and its name holds the
 * number, so the badge is hidden from assistive technology. On a desktop the panel is under the bell and is not modal (Tab
 * leaves it, Escape and a click outside close it). On a phone it is the Drawer component. On opening, the focus goes to
 * the heading "Notifications"; on closing, it goes back to the bell.
 */
export function NotificationBell() {
  const { count, registerPanel, setPanelOpen } = useNotifications();
  const page = useTypedPage();
  // The "Catch up" card of the panel is the one of the community of the page (design 5.5), when the page has one
  const community = (page.props as { community?: { cuid?: string; slug?: string; name?: string } }).community;
  const catchUpCommunities =
    community?.cuid && community.slug && community.name ? [{ cuid: community.cuid, slug: community.slug, name: community.name }] : [];
  const narrow = useSyncExternalStore(subscribeToNarrow, isNarrow, () => false);
  const [open, setOpen] = useState(false);
  const button = useRef<HTMLButtonElement>(null);
  const heading = useRef<HTMLSpanElement>(null);
  const container = useRef<HTMLDivElement>(null);

  // "Show all" of the card of the home opens the panel, and the provider knows when no pop-up may show
  useEffect(() => {
    registerPanel(() => setOpen(true));
    return () => registerPanel(null);
  }, [registerPanel]);
  useEffect(() => {
    setPanelOpen(open);
  }, [open, setPanelOpen]);

  const focusHeading = useCallback(() => heading.current?.focus(), []);
  const close = useCallback(() => setOpen(false), []);

  // On a desktop: Escape closes and gives the focus back to the bell, a click outside closes
  useEffect(() => {
    if (!open || narrow) {
      return;
    }
    // The menu of a line opens outside the panel (a portal): its Escape closes the menu only, a click in it keeps the panel
    const inMenu = (target: EventTarget | null) => target instanceof Element && target.closest('[role="menu"]') !== null;
    const onKeyDown = (event: KeyboardEvent) => {
      // The listener runs before the menu handles its own Escape (capture phase): an open menu closes alone
      if (
        event.key === 'Escape' &&
        !event.defaultPrevented &&
        !inMenu(document.activeElement) &&
        document.querySelector('[role="menu"]') === null
      ) {
        setOpen(false);
        button.current?.focus();
      }
    };
    const onPointerDown = (event: PointerEvent) => {
      if (event.target instanceof Node && !container.current?.contains(event.target) && !inMenu(event.target)) {
        setOpen(false);
      }
    };
    document.addEventListener('keydown', onKeyDown, true);
    document.addEventListener('pointerdown', onPointerDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown, true);
      document.removeEventListener('pointerdown', onPointerDown);
    };
  }, [open, narrow]);

  // On opening, the focus goes to the heading (the drawer moves the focus itself a moment later)
  useEffect(() => {
    if (!open) {
      return;
    }
    focusHeading();
    // A slow machine may run this after the member started to use the panel: then the focus stays where they put it
    let used = false;
    const markUsed = () => {
      used = true;
    };
    document.addEventListener('pointerdown', markUsed, true);
    document.addEventListener('keydown', markUsed, true);
    const timer = setTimeout(() => {
      if (!used) {
        focusHeading();
      }
    }, 50);
    return () => {
      clearTimeout(timer);
      document.removeEventListener('pointerdown', markUsed, true);
      document.removeEventListener('keydown', markUsed, true);
    };
  }, [open, narrow, focusHeading]);

  const badge = notificationBadge(count);
  const headingText = (
    <span
      ref={heading}
      id={HEADING_ID}
      tabIndex={-1}
      data-autofocus
      className="rounded outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
      Notifications
    </span>
  );

  return (
    <div ref={container} className="relative">
      <Button
        ref={button}
        variant="secondary"
        iconOnly
        aria-label={notificationBellName(count)}
        aria-expanded={open}
        aria-controls={PANEL_ID}
        onClick={() => setOpen((current) => !current)}
        className="relative"
      >
        <FaBell aria-hidden="true" />
        {badge && (
          <span
            aria-hidden="true"
            className="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-well bg-secondary px-1 text-xs leading-none font-bold text-white"
          >
            {badge}
          </span>
        )}
      </Button>

      {narrow ? (
        <Drawer open={open} onClose={close} side="right" title={headingText} closeLabel="Close notifications">
          <div id={PANEL_ID}>
            <NotificationsPanel onClose={close} focusHeading={focusHeading} catchUpCommunities={catchUpCommunities} />
          </div>
        </Drawer>
      ) : (
        open && (
          <div
            id={PANEL_ID}
            role="region"
            aria-labelledby={HEADING_ID}
            className="absolute top-full right-0 z-40 mt-2 max-h-[75vh] w-[28rem] max-w-[calc(100vw-2rem)] overflow-y-auto rounded-xl border border-border bg-surface-elevated p-4 text-primary shadow-2xl"
          >
            <h2 className="mb-3 text-lg font-extrabold">{headingText}</h2>
            <NotificationsPanel onClose={close} focusHeading={focusHeading} catchUpCommunities={catchUpCommunities} />
          </div>
        )
      )}
    </div>
  );
}
