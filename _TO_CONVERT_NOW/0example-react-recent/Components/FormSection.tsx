import React, { PropsWithChildren } from 'react';
import SectionTitle from './SectionTitle';

interface FormSectionProps {
  onSubmit: (e: React.FormEvent) => void;
  title: React.ReactNode;
  description: React.ReactNode;
  actions?: React.ReactNode;
}

const FormSection: React.FC<PropsWithChildren<FormSectionProps>> = ({ onSubmit, title, description, actions, children }) => {
  const hasActions = !!actions;

  return (
    <div className="md:grid md:grid-cols-3 md:gap-6">
      <div className="md:col-span-1">
        <SectionTitle title={title} description={description} />
      </div>
      <div className="mt-5 md:mt-0 md:col-span-2">
        <form onSubmit={onSubmit}>
          <div
            className={`px-4 py-5 bg-white dark:bg-gray-800 sm:p-6 shadow ${
              hasActions ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md'
            }`}
          >
            <div className="grid grid-cols-6 gap-6">{children}</div>
          </div>

          {hasActions && (
            <div className="flex items-center justify-end px-4 py-3 shadow bg-gray-50 dark:bg-gray-800 text-end sm:px-6 sm:rounded-bl-md sm:rounded-br-md">
              {actions}
            </div>
          )}
        </form>
      </div>
    </div>
  );
};

export default FormSection;
