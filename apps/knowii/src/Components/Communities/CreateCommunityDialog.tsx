import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { InputText } from 'primereact/inputtext';
import { InputTextarea } from 'primereact/inputtextarea';
import { ProgressSpinner } from 'primereact/progressspinner';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { SelectButton } from 'primereact/selectbutton';
import {
  communityVisibilityOptions,
  NewCommunity,
  newCommunitySchema,
  Community,
  knowiiApiClient,
  MIN_ACTION_TIME,
  sleep,
  useAppData,
  DEFAULT_TEXTAREA_ROWS,
} from '@knowii/common';
import { useState } from 'react';
import CommunityIcon from './CommunityIcon';

interface CreateCommunityDialogProps {
  visible: boolean;
  onHide: () => void;
  onCommunityCreated: (community: Community) => void;
}

export default function CreateCommunityDialog({ visible, onHide, onCommunityCreated }: CreateCommunityDialogProps) {
  const [loading, setLoading] = useState(false);
  const appData = useAppData();
  const toast = appData.toast;

  const form = useForm<NewCommunity>({
    resolver: zodResolver(newCommunitySchema),
    defaultValues: {
      name: '',
      description: '',
      visibility: 'public',
    },
    mode: 'onChange',
    reValidateMode: 'onChange',
  });

  const handleSubmit = form.handleSubmit(async (data: NewCommunity) => {
    setLoading(true);

    await sleep(MIN_ACTION_TIME);

    const response = await knowiiApiClient.communities.create(data);

    if ('success' === response.type && !response.errors) {
      toast?.show({
        severity: 'success',
        summary: 'Community created successfully',
      });

      if (response.data) {
        const createdCommunity = response.data;
        onCommunityCreated(createdCommunity);
      }
      form.reset();
      onHide();
    } else {
      toast?.show({
        severity: 'error',
        summary: 'Failed to create the community',
        detail: response.message,
      });
    }

    setLoading(false);
  });

  return (
    <Dialog
      header={
        <span className="flex items-center gap-2">
          <CommunityIcon visibility={null} />
          New community
        </span>
      }
      closeOnEscape={true}
      visible={visible}
      className="w-full sm:w-[75vw] md:w-[60vw] lg:w-[40vw] xl:w-[30vw]"
      onHide={onHide}
      footer={
        <>
          <Button severity="secondary" label="Cancel" onClick={onHide} />
          <Button onClick={handleSubmit} label="Go ahead!" disabled={!form.formState.isValid || loading} className="ml-2" />
        </>
      }
    >
      {loading ? (
        <div className="flex flex-row justify-center items-center">
          <ProgressSpinner />
        </div>
      ) : (
        <>
          <span>Provide the details of your new community.</span>
          <form onSubmit={handleSubmit}>
            {/* Name */}
            <div className="mt-4 col-span-6 sm:col-span-4">
              <InputLabel htmlFor="name">Name</InputLabel>
              <div className="p-inputgroup mt-1">
                <Controller
                  name="name"
                  control={form.control}
                  render={({ field }) => (
                    <InputText
                      id="name"
                      type="text"
                      className="mt-1 block w-full"
                      {...field}
                      aria-invalid={form.formState.errors.name ? true : false}
                      required
                      disabled={form.formState.isSubmitting || loading}
                    />
                  )}
                />
              </div>
              {form.formState.errors.name && <InputError className="mt-2" message={form.formState.errors.name.message} />}
            </div>

            {/* Description */}
            <div className="mt-4 col-span-6 sm:col-span-4">
              <InputLabel htmlFor="description">Description</InputLabel>
              <div className="p-inputgroup mt-1">
                <Controller
                  name="description"
                  control={form.control}
                  render={({ field }) => (
                    <InputTextarea
                      id="description"
                      className="mt-1 block w-full"
                      {...field}
                      rows={DEFAULT_TEXTAREA_ROWS}
                      aria-invalid={form.formState.errors.description ? true : false}
                      disabled={loading}
                    />
                  )}
                />
              </div>
              {form.formState.errors.description && <InputError className="mt-2" message={form.formState.errors.description.message} />}
            </div>

            {/* Visibility */}
            <div className="mt-4">
              <InputLabel htmlFor="visibility">Community visibility</InputLabel>
              <div className="p-inputgroup mt-1">
                <Controller
                  name="visibility"
                  control={form.control}
                  render={({ field }) => (
                    <SelectButton
                      id="visibility"
                      className="mt-1 block"
                      options={communityVisibilityOptions}
                      optionLabel="name"
                      optionValue="visibility"
                      required
                      {...field}
                      aria-invalid={form.formState.errors.visibility ? true : false}
                      disabled={loading}
                    />
                  )}
                />
              </div>
              {form.formState.errors.visibility && <InputError className="mt-2" message={form.formState.errors.visibility.message} />}
            </div>
          </form>
        </>
      )}
    </Dialog>
  );
}
