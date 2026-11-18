export type ToastSeverity = 'success' | 'info' | 'warn' | 'error';

export interface ToastMessage {
  severity: ToastSeverity;
  /**
   * Short message, always shown
   */
  summary: string;
  /**
   * Optional additional information
   */
  detail?: string;
  /**
   * Time in milliseconds before the toast is dismissed automatically. Defaults to DEFAULT_TOAST_LIFE.
   */
  life?: number;
  /**
   * When true, the toast stays until the user dismisses it
   */
  sticky?: boolean;
  /**
   * A link to the next step, for example "Open the space" after a create (#1576). A toast with an action stays until the
   * user follows the link or dismisses the toast (WCAG 2.2.1): it never goes away by itself.
   */
  action?: ToastAction;
}

export interface ToastAction {
  /**
   * The text of the link
   */
  label: string;
  /**
   * The page that the link opens (an Inertia visit)
   */
  href: string;
}

export interface Toast extends ToastMessage {
  id: string;
}
