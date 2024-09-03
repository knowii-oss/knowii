import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import DeleteTeamForm from '@/Pages/Teams/Partials/DeleteTeamForm';
import SectionBorder from '@/Components/SectionBorder';
import TeamMemberManager from '@/Pages/Teams/Partials/TeamMemberManager';
import UpdateTeamNameForm from '@/Pages/Teams/Partials/UpdateTeamNameForm';

interface TeamSettingsProps {
  team: any;
  availableRoles: Array<any>;
  permissions: any;
}

const TeamSettings: React.FC<TeamSettingsProps> = ({ team, availableRoles, permissions }) => {
  return (
    <AppLayout
      title="Team Settings"
      header={<div className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Team Settings</div>}
    >
      <div>
        <div className="py-10 mx-auto max-w-7xl sm:px-6 lg:px-8">
          <UpdateTeamNameForm team={team} permissions={permissions} />

          <div className="mt-10 sm:mt-0">
            <TeamMemberManager team={team} availableRoles={availableRoles} userPermissions={permissions} />
          </div>

          {permissions.canDeleteTeam && !team.personal_team && (
            <>
              <SectionBorder />
              <div className="mt-10 sm:mt-0">
                <DeleteTeamForm team={team} />
              </div>
            </>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default TeamSettings;
