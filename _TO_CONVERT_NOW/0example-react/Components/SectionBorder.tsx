import React from 'react';

const SectionBorder: React.FC = () => {
  return (
    <div className="hidden sm:block">
      <div className="py-8">
        <div className="border-t border-gray-200 dark:border-gray-700" />
      </div>
    </div>
  );
};

export default SectionBorder;
