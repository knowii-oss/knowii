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
}

export interface Toast extends ToastMessage {
  id: string;
}
