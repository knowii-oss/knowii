import React, { ReactNode } from 'react';

interface MyComponentProps {
  logo: ReactNode;
  children: ReactNode;
}

const MyComponent: React.FC<MyComponentProps> = ({ logo, children }) => {
  return (
    <div className="flex flex-col items-center min-h-screen pt-6 bg-gray-100 sm:justify-center sm:pt-0 dark:bg-gray-900">
      <div>{logo}</div>

      <div className="w-full px-6 py-4 mt-6 overflow-hidden bg-white shadow-md sm:max-w-md dark:bg-gray-800 sm:rounded-lg">{children}</div>
    </div>
  );
};

export default MyComponent;
