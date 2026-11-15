import type { ReactNode } from 'react';
import { createContext, useContext } from 'react';
import { Link } from '@inertiajs/react';
import { LOGIN_URL } from '@knowii/common';
import { cn } from '@/Components/ui';

/**
 * Whether the reader is a visitor: nobody is signed in (#1387, ADR 0044). The layout provides it. A visitor reads the
 * public spaces: no composer, no live update (no WebSocket), reactions as counts, no link to a member.
 */
const VisitorContext = createContext(false);

export const VisitorProvider = VisitorContext.Provider;

export function useIsVisitor(): boolean {
  return useContext(VisitorContext);
}

interface SignInPromptProps {
  /**
   * What signing in allows: "Sign in to reply", "Sign in to post", "Sign in to download"
   */
  children: ReactNode;
  className?: string;
}

/**
 * Takes the place of a composer or of an action that needs an account. It is a link to the sign-in page: the server
 * keeps the address of the page that the visitor reads, and the sign-in comes back to it.
 */
export function SignInPrompt({ children, className }: SignInPromptProps) {
  return (
    <p className={cn('text-sm', className)}>
      <Link
        href={route(LOGIN_URL)}
        className="font-bold underline hover:no-underline focus-visible:outline-2 focus-visible:outline-primary"
      >
        {children}
      </Link>
    </p>
  );
}
