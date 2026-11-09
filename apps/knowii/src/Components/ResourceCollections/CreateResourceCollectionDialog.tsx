import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import type { NewCommunityResourceCollection, CommunityResourceCollection } from '@knowii/common';
import {
  newCommunityResourceCollectionSchema,
  knowiiApiClient,
  MIN_ACTION_TIME,
  sleep,
  useAppData,
  DEFAULT_TEXTAREA_ROWS,
} from '@knowii/common';
import { useState } from 'react';
import ResourceCollectionIcon from './ResourceCollectionIcon';
import { Button, Dialog, Spinner, Textarea, TextInput } from '@/Components/ui';

export interface CreateResourceCollectionDialogSettings {
  visible: boolean;
  communityCuid: string;
}

interface CreateResourceCollectionDialogProps {
  settings: CreateResourceCollectionDialogSettings;
  onHide: () => void;
  onResourceCollectionCreated: (resourceCollection: CommunityResourceCollection) => void;
}

export default function CreateResourceCollectionDialog(props: CreateResourceCollectionDialogProps) {
  const { showToast } = useAppData();

  const [loading, setLoading] = useState(false);

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
      communityCuid: props.settings.communityCuid,
      ...data,
    });

    if ('success' === response.type && !response.errors) {
      showToast({
        severity: 'success',
        summary: 'Resource collection created successfully',
      });

      if (response.data) {
        const createdResourceCollection = response.data;
        props.onResourceCollectionCreated(createdResourceCollection);
      }
      form.reset();
      props.onHide();
    } else if ('businessIssue' === response.type && response.message) {
      // The name is already used in this community (#925): show it on the field, where the member fixes it
      form.setError('name', { type: 'server', message: response.message }, { shouldFocus: true });
    } else {
      showToast({
        severity: 'error',
        summary: response.message ? response.message : 'Failed to create the resource collection',
        detail: response.message ? response.message : 'Please try again later.',
      });
    }

    setLoading(false);
  });

  return (
    <Dialog
      open={props.settings.visible}
      onClose={props.onHide}
      title={
        <>
          <ResourceCollectionIcon />
          New resource collection
        </>
      }
      description="Provide the details of your new resource collection."
      footer={
        <>
          <Button variant="secondary" onClick={props.onHide}>
            Cancel
          </Button>
          <Button type="submit" form="create-resource-collection-form" disabled={!form.formState.isValid} loading={loading}>
            Create
          </Button>
        </>
      }
    >
      {loading ? (
        <div className="flex flex-row items-center justify-center py-8">
          <Spinner size="lg" label="Creating the resource collection" />
        </div>
      ) : (
        <form id="create-resource-collection-form" onSubmit={handleSubmit} noValidate>
          {/* Name */}
          <div>
            <InputLabel htmlFor="name">Name</InputLabel>
            <Controller
              name="name"
              control={form.control}
              render={({ field }) => (
                <TextInput
                  id="name"
                  type="text"
                  wrapperClassName="mt-1"
                  {...field}
                  autoFocus
                  invalid={!!form.formState.errors.name}
                  aria-describedby={form.formState.errors.name ? 'name-error' : undefined}
                  required
                  disabled={form.formState.isSubmitting || loading}
                />
              )}
            />
            <InputError id="name-error" className="mt-2" message={form.formState.errors.name?.message} />
          </div>

          {/* Description */}
          <div className="mt-4">
            <InputLabel htmlFor="description">Description</InputLabel>
            <Controller
              name="description"
              control={form.control}
              render={({ field }) => (
                <Textarea
                  id="description"
                  wrapperClassName="mt-1"
                  {...field}
                  rows={DEFAULT_TEXTAREA_ROWS}
                  invalid={!!form.formState.errors.description}
                  aria-describedby={form.formState.errors.description ? 'description-error' : undefined}
                  disabled={loading}
                />
              )}
            />
            <InputError id="description-error" className="mt-2" message={form.formState.errors.description?.message} />
          </div>
        </form>
      )}
    </Dialog>
  );
}
