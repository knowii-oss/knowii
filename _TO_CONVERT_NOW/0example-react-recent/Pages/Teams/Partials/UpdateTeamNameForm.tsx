import React, { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import ActionMessage from '@/Components/ActionMessage';
import FormSection from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

interface UpdateTeamNameFormProps {
  team: {
    id: number;
    name: string;
    owner: {
      name: string;
      email: string;
      profile_photo_url: string;
    };
  };
  permissions: {
    canUpdateTeam: boolean;
  };
}

const UpdateTeamNameForm: React.FC<UpdateTeamNameFormProps> = ({ team, permissions }) => {
  const form = useForm({
    name: team.name,
  });

  const updateTeamName: FormEventHandler = (e) => {
    e.preventDefault();
    form.put(route('teams.update', team), {
      errorBag: 'updateTeamName',
      preserveScroll: true,
    });
  };

  return (
    <FormSection
      onSubmit={updateTeamName}
      title="Team Name"
      description="The team's name and owner information."
      actions={
        permissions.canUpdateTeam && (
          <>
            <ActionMessage on={form.recentlySuccessful} className="me-3">
              Saved.
            </ActionMessage>
            <PrimaryButton className={form.processing ? 'opacity-25' : ''} disabled={form.processing}>
              Save
            </PrimaryButton>
          </>
        )
      }
    >
      <div className="col-span-6">
        <InputLabel value="Team Owner" />
        <div className="flex items-center mt-2">
          <img className="object-cover w-12 h-12 rounded-full" src={team.owner.profile_photo_url} alt={team.owner.name} />
          <div className="leading-tight ms-4">
            <div className="text-gray-900 dark:text-white">{team.owner.name}</div>
            <div className="text-sm text-gray-700 dark:text-gray-300">{team.owner.email}</div>
          </div>
        </div>
      </div>

      <div className="col-span-6 sm:col-span-4">
        <InputLabel htmlFor="name" value="Team Name" />
        <TextInput
          id="name"
          value={form.data.name}
          onChange={(e) => form.setData('name', e.target.value)}
          type="text"
          className="block w-full mt-1"
          disabled={!permissions.canUpdateTeam}
        />
        <InputError message={form.errors.name} className="mt-2" />
      </div>
    </FormSection>
  );
};

export default UpdateTeamNameForm;
