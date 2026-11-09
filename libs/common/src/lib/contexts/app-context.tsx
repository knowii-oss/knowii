import React, { createContext, useContext, useMemo, useRef } from 'react';
import { Toast } from 'primereact/toast';
import type { ToastMessage } from 'primereact/toast';
import { DEFAULT_TOAST_POSITION } from '../constants';

interface AppData {
  /**
   * Show one or more toast messages
   */
  showToast: (message: ToastMessage | ToastMessage[]) => void;
}

const AppDataContext = createContext<AppData | undefined>(undefined);

export const useAppData = (): AppData => {
  const context = useContext(AppDataContext);
  if (context === undefined) {
    throw new Error('useAppData must be used within an AppDataProvider');
  }
  return context;
};

/**
 * Provides application-wide services. Rendered once around the Inertia app, so it stays mounted across page visits.
 */
export const AppDataProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const toastRef = useRef<Toast | null>(null);

  // Stable value: consumers never re-render because of it
  const appData = useMemo<AppData>(
    () => ({
      showToast: (message) => {
        toastRef.current?.show(message);
      },
    }),
    [],
  );

  return (
    <AppDataContext.Provider value={appData}>
      {/* The single application toast lives here (not in a page layout), so messages survive Inertia page visits */}
      <Toast position={DEFAULT_TOAST_POSITION} ref={toastRef} />
      {children}
    </AppDataContext.Provider>
  );
};
