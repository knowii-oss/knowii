import React, { FormEventHandler } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import FormSection from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

const CreateTeamForm: React.FC = () => {
  const page: any = usePage();
  const form = useForm({
    name: '',
  });

  const createTeam: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route('teams.store'), {
      errorBag: 'createTeam',
      preserveScroll: true,
    });
  };

  return (
    <FormSection
      onSubmit={createTeam}
      title="Team Details"
      description="Create a new team to collaborate with others on projects."
      actions={
        <PrimaryButton className={form.processing ? 'opacity-25' : ''} disabled={form.processing}>
          Create
        </PrimaryButton>
      }
    >
      <div className="col-span-6">
        <InputLabel value="Team Owner" />
        <div className="flex items-center mt-2">
          <img
            className="object-cover w-12 h-12 rounded-full"
            src={page.props.auth.user.profile_photo_url}
            alt={page.props.auth.user.name}
          />
          <div className="leading-tight ms-4">
            <div className="text-gray-900 dark:text-white">{page.props.auth.user.name}</div>
            <div className="text-sm text-gray-700 dark:text-gray-300">{page.props.auth.user.email}</div>
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
          autoFocus
        />
        <InputError message={form.errors.name} className="mt-2" />
      </div>
    </FormSection>
  );
};

export default CreateTeamForm;
