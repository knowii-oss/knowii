import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import ApiTokenManager from '@/Pages/API/Partials/ApiTokenManager';

interface IndexProps {
  tokens: Array<any>;
  availablePermissions: Array<string>;
  defaultPermissions: Array<string>;
}

const Index: React.FC<IndexProps> = ({ tokens, availablePermissions, defaultPermissions }) => {
  return (
    <AppLayout
      title="API Tokens"
      header={<div className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">API Tokens</div>}
    >
      <div>
        <div className="py-10 mx-auto max-w-7xl sm:px-6 lg:px-8">
          <ApiTokenManager tokens={tokens} availablePermissions={availablePermissions} defaultPermissions={defaultPermissions} />
        </div>
      </div>
    </AppLayout>
  );
};

export default Index;
