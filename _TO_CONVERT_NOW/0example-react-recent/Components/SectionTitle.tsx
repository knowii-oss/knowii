import React, { ReactNode } from 'react';

interface MyComponentProps {
  title: ReactNode;
  description: ReactNode;
  aside?: ReactNode;
}

const MyComponent: React.FC<MyComponentProps> = ({ title, description, aside }) => {
  return (
    <div className="flex justify-between md:col-span-1">
      <div className="px-4 sm:px-0">
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">{title}</h3>

        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{description}</p>
      </div>

      <div className="px-4 sm:px-0">{aside}</div>
    </div>
  );
};

export default MyComponent;
