import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { cn, isCurrentUrl } from '@/Components/ui';

export interface MainMenuEntry {
  label: string;
  href: string;
  icon: ReactNode;
  /**
   * exact (default): only this page is active. prefix: the pages below it are active too.
   */
  match?: 'exact' | 'prefix';
}

interface Props {
  entries: readonly MainMenuEntry[];
  /**
   * The URL of the open page (the Inertia page URL)
   */
  currentUrl: string;
  onNavigate: () => void;
}

/**
 * The entries of the main menu (#686). The entry of the open page has aria-current="page", a bar on its start side and
 * the accent color: the bar marks it without color too.
 */
export default function MainMenu({ entries, currentUrl, onNavigate }: Props) {
  return (
    <ul className="flex w-full flex-col gap-2">
      {entries.map((entry) => {
        const active = isCurrentUrl(entry.href, currentUrl, entry.match);
        return (
          <li key={entry.href}>
            <Link
              href={entry.href}
              preserveState={true}
              onClick={onNavigate}
              aria-current={active ? 'page' : undefined}
              className={cn(
                'flex items-center gap-3 rounded-lg border-l-4 px-4 py-3 text-lg font-bold transition-colors hover:bg-overlay/10',
                active ? 'border-secondary bg-overlay/10 text-primary' : 'border-transparent text-muted hover:text-primary',
              )}
            >
              <span aria-hidden="true" className="text-secondary-text">
                {entry.icon}
              </span>
              {entry.label}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
