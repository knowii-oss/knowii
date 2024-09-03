import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import CreateTeamForm from '@/Pages/Teams/Partials/CreateTeamForm';

const CreateTeam: React.FC = () => {
  return (
    <AppLayout
      title="Create Team"
      header={<div className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Create Team</div>}
    >
      <div>
        <div className="py-10 mx-auto max-w-7xl sm:px-6 lg:px-8">
          <CreateTeamForm />
        </div>
      </div>
    </AppLayout>
  );
};

export default CreateTeam;
