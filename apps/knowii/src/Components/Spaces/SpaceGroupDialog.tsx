import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { SpaceGroup, SpaceGroupForm } from '@knowii/common';
import { knowiiApiClient, spaceGroupFormSchema, useAppData } from '@knowii/common';
import { useEffect } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Dialog, TextInput } from '@/Components/ui';

export interface SpaceGroupDialogProps {
  open: boolean;
  communityCuid: string;
  /**
   * The group to rename. Absent: create a new group.
   */
  spaceGroup?: SpaceGroup | undefined;
  onClose: () => void;
  onSaved: (spaceGroup: SpaceGroup) => void;
}

export function SpaceGroupDialog({ open, communityCuid, spaceGroup, onClose, onSaved }: SpaceGroupDialogProps) {
  const { showToast } = useAppData();
  const form = useForm<SpaceGroupForm>({
    resolver: zodResolver(spaceGroupFormSchema),
    defaultValues: { name: spaceGroup?.name ?? '' },
    mode: 'onChange',
  });

  useEffect(() => {
    if (open) {
      form.reset({ name: spaceGroup?.name ?? '' });
    }
  }, [open, spaceGroup, form]);

  const onSubmit = form.handleSubmit(async (data) => {
    const result = spaceGroup
      ? await knowiiApiClient.communities.spaceGroups.update(communityCuid, spaceGroup.cuid, data)
      : await knowiiApiClient.communities.spaceGroups.create(communityCuid, data);

    if (result.ok) {
      showToast({ severity: 'success', summary: spaceGroup ? 'Space group renamed' : 'Space group created' });
      onSaved(result.data);
      onClose();
    } else {
      const nameError = result.errors['name']?.[0];
      if (nameError) {
        form.setError('name', { message: nameError });
      }
      showToast({ severity: 'error', summary: result.message });
    }
  });

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={spaceGroup ? 'Rename the space group' : 'New space group'}
      description="Space groups organize the spaces in the navigation of the community."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="space-group-form" disabled={!form.formState.isValid} loading={form.formState.isSubmitting}>
            {spaceGroup ? 'Save' : 'Create'}
          </Button>
        </>
      }
    >
      <form id="space-group-form" onSubmit={onSubmit} noValidate>
        <InputLabel htmlFor="space-group-name">Name</InputLabel>
        <Controller
          name="name"
          control={form.control}
          render={({ field }) => (
            <TextInput
              id="space-group-name"
              wrapperClassName="mt-1"
              {...field}
              autoFocus
              required
              invalid={!!form.formState.errors.name}
              aria-describedby={form.formState.errors.name ? 'space-group-name-error' : undefined}
            />
          )}
        />
        <InputError id="space-group-name-error" className="mt-2" message={form.formState.errors.name?.message} />
      </form>
    </Dialog>
  );
}
