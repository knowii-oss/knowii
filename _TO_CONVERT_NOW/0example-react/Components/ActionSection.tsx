import React, { ReactNode } from 'react';
import SectionTitle from './SectionTitle';

interface MyComponentProps {
  title: ReactNode;
  description: ReactNode;
  content: ReactNode;
}

const MyComponent: React.FC<MyComponentProps> = ({ title, description, content }) => {
  return (
    <div className="md:grid md:grid-cols-3 md:gap-6">
      <SectionTitle title={title} description={description} />

      <div className="mt-5 md:mt-0 md:col-span-2">
        <div className="px-4 py-5 bg-white shadow sm:p-6 dark:bg-gray-800 sm:rounded-lg">{content}</div>
      </div>
    </div>
  );
};

export default MyComponent;
