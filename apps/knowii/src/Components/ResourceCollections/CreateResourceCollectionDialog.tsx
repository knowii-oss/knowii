import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { InputText } from 'primereact/inputtext';
import { InputTextarea } from 'primereact/inputtextarea';
import { ProgressSpinner } from 'primereact/progressspinner';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import {
  NewCommunityResourceCollection,
  newCommunityResourceCollectionSchema,
  CommunityResourceCollection,
  knowiiApiClient,
  MIN_ACTION_TIME,
  sleep,
  useAppData,
} from '@knowii/common';
import { useState } from 'react';
import ResourceCollectionIcon from './ResourceCollectionIcon';

interface CreateResourceCollectionDialogSettings {
  visible: boolean;
  communityCuid: string;
}

interface CreateResourceCollectionDialogProps {
  settings: CreateResourceCollectionDialogSettings;
  onHide: () => void;
  onResourceCollectionCreated: (resourceCollection: CommunityResourceCollection) => void;
}

export default function CreateResourceCollectionDialog({
  settings,
  onHide,
  onResourceCollectionCreated,
}: CreateResourceCollectionDialogProps) {
  const [loading, setLoading] = useState(false);
  const appData = useAppData();
  const toast = appData.toast;

  const form = useForm<NewCommunityResourceCollection>({
    resolver: zodResolver(newCommunityResourceCollectionSchema),
    defaultValues: {
      name: '',
      description: '',
    },
    mode: 'onChange',
    reValidateMode: 'onChange',
  });

  const handleSubmit = form.handleSubmit(async (data: NewCommunityResourceCollection) => {
    setLoading(true);

    await sleep(MIN_ACTION_TIME);

    const response = await knowiiApiClient.communities.resourceCollections.create({
      communityCuid: settings.communityCuid,
      ...data,
    });

    if ('success' === response.type && !response.errors) {
      toast?.show({
        severity: 'success',
        summary: 'Resource collection created successfully',
      });

      if (response.data) {
        const createdResourceCollection: CommunityResourceCollection = response.data;
        onResourceCollectionCreated(createdResourceCollection);
      }
      form.reset();
      onHide();
    } else {
      toast?.show({
        severity: 'error',
        summary: 'Failed to create the resource collection',
        detail: response.message,
      });
    }

    setLoading(false);
  });

  return (
    <Dialog
      header={
        <span className="flex items-center gap-2">
          <ResourceCollectionIcon />
          New resource collection
        </span>
      }
      closeOnEscape={true}
      visible={settings.visible}
      className="w-full sm:w-[75vw] md:w-[60vw] lg:w-[40vw] xl:w-[30vw]"
      onHide={onHide}
      footer={
        <>
          <Button severity="secondary" label="Cancel" onClick={onHide} />
          <Button onClick={handleSubmit} label="Create" disabled={!form.formState.isValid || loading} className="ml-2" />
        </>
      }
    >
      {loading ? (
        <div className="flex flex-row justify-center items-center">
          <ProgressSpinner />
        </div>
      ) : (
        <>
          <span>Provide the details of your new resource collection.</span>
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
                      aria-invalid={form.formState.errors.description ? true : false}
                      disabled={loading}
                    />
                  )}
                />
              </div>
              {form.formState.errors.description && <InputError className="mt-2" message={form.formState.errors.description.message} />}
            </div>
          </form>
        </>
      )}
    </Dialog>
  );
}
