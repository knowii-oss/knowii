import React, { createContext, useContext } from 'react';
import { Toast } from 'primereact/toast';

interface AppData {
  toast: Toast | null;
}

const AppDataContext = createContext<AppData | undefined>(undefined);

export const useAppData = () => {
  const context = useContext(AppDataContext);
  if (context === undefined) {
    throw new Error('useAppData must be used within an AppDataProvider');
  }
  return context;
};

export const AppDataProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  // Initialize your app data here
  const appData: AppData = {
    toast: null,
  };

  return <AppDataContext.Provider value={appData}>{children}</AppDataContext.Provider>;
};
