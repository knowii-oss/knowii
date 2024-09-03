import React, { useState, FormEventHandler } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import ActionSection from '@/Components/ActionSection';
import ConfirmationModal from '@/Components/ConfirmationModal';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';

interface DeleteTeamFormProps {
  team: any;
}

const DeleteTeamForm: React.FC<DeleteTeamFormProps> = ({ team }) => {
  const [confirmingTeamDeletion, setConfirmingTeamDeletion] = useState(false);
  const form = useForm({});

  const confirmTeamDeletion = () => {
    setConfirmingTeamDeletion(true);
  };

  const deleteTeam: FormEventHandler = (e) => {
    e.preventDefault();
    form.delete(route('teams.destroy', team), {
      errorBag: 'deleteTeam',
    });
  };

  return (
    <ActionSection
      title="Delete Team"
      description="Permanently delete this team."
      content={
        <>
          <div className="max-w-xl text-sm text-gray-600 dark:text-gray-400">
            Once a team is deleted, all of its resources and data will be permanently deleted. Before deleting this team, please download
            any data or information regarding this team that you wish to retain.
          </div>

          <div className="mt-5">
            <DangerButton onClick={confirmTeamDeletion}>Delete Team</DangerButton>
          </div>

          <ConfirmationModal
            show={confirmingTeamDeletion}
            onClose={() => setConfirmingTeamDeletion(false)}
            title="Delete Team"
            content="Are you sure you want to delete this team? Once a team is deleted, all of its resources and data will be permanently deleted."
            footer={
              <>
                <SecondaryButton onClick={() => setConfirmingTeamDeletion(false)}>Cancel</SecondaryButton>
                <DangerButton className={`ms-3 ${form.processing ? 'opacity-25' : ''}`} disabled={form.processing} onClick={deleteTeam}>
                  Delete Team
                </DangerButton>
              </>
            }
          />
        </>
      }
    />
  );
};

export default DeleteTeamForm;
