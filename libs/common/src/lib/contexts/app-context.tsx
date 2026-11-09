import type React from 'react';
import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import type { Toast, ToastMessage } from '../types/toast.intf';
import { MAX_VISIBLE_TOASTS } from '../constants';

interface AppData {
  /**
   * Show one or more toast messages
   */
  showToast: (message: ToastMessage | ToastMessage[]) => void;
}

interface ToastsData {
  /**
   * The toasts currently shown, oldest first
   */
  toasts: Toast[];
  dismissToast: (id: string) => void;
}

const AppDataContext = createContext<AppData | undefined>(undefined);
const ToastsContext = createContext<ToastsData | undefined>(undefined);

export const useAppData = (): AppData => {
  const context = useContext(AppDataContext);
  if (context === undefined) {
    throw new Error('useAppData must be used within an AppDataProvider');
  }
  return context;
};

/**
 * Read the current toasts. Only the component that renders them needs this.
 */
export const useToasts = (): ToastsData => {
  const context = useContext(ToastsContext);
  if (context === undefined) {
    throw new Error('useToasts must be used within an AppDataProvider');
  }
  return context;
};

/**
 * Provides application-wide services. Rendered once around the Inertia app, so it stays mounted across page visits
 * (toasts survive Inertia page visits).
 */
export const AppDataProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const nextToastId = useRef(0);

  // Stable value: consumers of showToast never re-render because of it
  const appData = useMemo<AppData>(
    () => ({
      showToast: (message) => {
        const messages = Array.isArray(message) ? message : [message];
        setToasts((current) =>
          [
            ...current,
            ...messages.map((item) => {
              nextToastId.current += 1;
              return { ...item, id: `toast-${nextToastId.current}` };
            }),
          ].slice(-MAX_VISIBLE_TOASTS),
        );
      },
    }),
    [],
  );

  const dismissToast = useCallback((id: string) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const toastsData = useMemo<ToastsData>(() => ({ toasts, dismissToast }), [toasts, dismissToast]);

  return (
    <AppDataContext.Provider value={appData}>
      <ToastsContext.Provider value={toastsData}>{children}</ToastsContext.Provider>
    </AppDataContext.Provider>
  );
};
