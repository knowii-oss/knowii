import { useEffect, useRef } from 'react';
import { cn } from './cn';
import { useTheme } from './theme';

/**
 * Cloudflare Turnstile: bot check for public forms. https://developers.cloudflare.com/turnstile/
 * The server MUST verify the token (siteverify): the widget alone protects nothing.
 */

const TURNSTILE_SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

interface TurnstileRenderOptions {
  sitekey: string;
  theme: 'light' | 'dark' | 'auto';
  callback: (token: string) => void;
  'expired-callback': () => void;
  'error-callback': () => void;
}

export interface TurnstileApi {
  render: (container: HTMLElement, options: TurnstileRenderOptions) => string | undefined;
  remove: (widgetId: string) => void;
}

declare global {
  interface Window {
    turnstile?: TurnstileApi;
  }
}

let turnstileLoader: Promise<TurnstileApi> | null = null;

/**
 * Load the Turnstile script once. It is added by our own (trusted) script, so the CSP allows it through 'strict-dynamic'.
 */
export function loadTurnstile(): Promise<TurnstileApi> {
  if (window.turnstile) {
    return Promise.resolve(window.turnstile);
  }
  if (!turnstileLoader) {
    turnstileLoader = new Promise<TurnstileApi>((resolve, reject) => {
      const script = document.createElement('script');
      script.src = TURNSTILE_SCRIPT_URL;
      script.async = true;
      script.onload = () => (window.turnstile ? resolve(window.turnstile) : reject(new Error('Turnstile did not load')));
      script.onerror = () => {
        turnstileLoader = null;
        reject(new Error('Turnstile did not load'));
      };
      document.head.appendChild(script);
    });
  }
  return turnstileLoader;
}

interface TurnstileProps {
  siteKey: string;
  /**
   * Called with a new token, or with null when the token expires or the check fails
   */
  onTokenChange: (token: string | null) => void;
  className?: string;
}

/**
 * Turnstile widget. Mount it again (change its key) to get a new token: a token can be used only once.
 */
export function Turnstile({ siteKey, onTokenChange, className }: TurnstileProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const onTokenChangeRef = useRef(onTokenChange);
  const { theme } = useTheme();

  useEffect(() => {
    onTokenChangeRef.current = onTokenChange;
  }, [onTokenChange]);

  useEffect(() => {
    let widgetId: string | undefined;
    let cancelled = false;

    loadTurnstile()
      .then((turnstile) => {
        if (cancelled || !containerRef.current) {
          return;
        }
        widgetId = turnstile.render(containerRef.current, {
          sitekey: siteKey,
          theme,
          callback: (token) => onTokenChangeRef.current(token),
          'expired-callback': () => onTokenChangeRef.current(null),
          'error-callback': () => onTokenChangeRef.current(null),
        });
      })
      .catch(() => onTokenChangeRef.current(null));

    return () => {
      cancelled = true;
      if (widgetId) {
        window.turnstile?.remove(widgetId);
      }
    };
  }, [siteKey, theme]);

  return <div ref={containerRef} className={cn('min-h-[65px]', className)} />;
}
