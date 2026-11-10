import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import type { NewCommunity, Community, CommunityVisibility } from '@knowii/common';
import {
  communityVisibilityOptions,
  newCommunitySchema,
  knowiiApiClient,
  MIN_ACTION_TIME,
  sleep,
  useAppData,
  DEFAULT_TEXTAREA_ROWS,
} from '@knowii/common';
import { useState } from 'react';
import CommunityIcon from './CommunityIcon';
import { CommunityNameStatus } from './CommunityNameStatus';
import { useCommunityNameAvailability } from './use-community-name-availability';
import { Button, Dialog, SegmentedControl, Spinner, Textarea, TextInput } from '@/Components/ui';

interface CreateCommunityDialogProps {
  visible: boolean;
  onHide: () => void;
  onCommunityCreated: (community: Community) => void;
}

const visibilityOptions = communityVisibilityOptions.map((option) => ({
  value: option.visibility,
  label: option.name,
  icon: <CommunityIcon visibility={option.visibility} />,
}));

export default function CreateCommunityDialog(props: CreateCommunityDialogProps) {
  const [loading, setLoading] = useState(false);
  const { showToast } = useAppData();

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

  // Personal communities never take a name (ADR 0033): no check for them
  const nameStatus = useCommunityNameAvailability(form.watch('name'), form.watch('visibility') !== 'personal');
  const nameBlocked = nameStatus === 'checking' || nameStatus === 'taken';

  const handleSubmit = form.handleSubmit(async (data: NewCommunity) => {
    setLoading(true);

    await sleep(MIN_ACTION_TIME);

    const response = await knowiiApiClient.communities.create(data);

    if ('success' === response.type && !response.errors) {
      showToast({
        severity: 'success',
        summary: 'Community created successfully',
      });

      if (response.data) {
        const createdCommunity = response.data;
        props.onCommunityCreated(createdCommunity);
      }
      form.reset();
      props.onHide();
    } else {
      showToast({
        severity: 'error',
        summary: response.message ? response.message : 'Failed to create the community',
        detail: response.message ? response.message : 'Please try again later.',
      });
    }

    setLoading(false);
  });

  return (
    <Dialog
      open={props.visible}
      onClose={props.onHide}
      title={
        <>
          <CommunityIcon visibility={null} />
          New community
        </>
      }
      description="Provide the details of your new community."
      footer={
        <>
          <Button variant="secondary" onClick={props.onHide}>
            Cancel
          </Button>
          <Button type="submit" form="create-community-form" disabled={!form.formState.isValid || nameBlocked} loading={loading}>
            Go ahead!
          </Button>
        </>
      }
    >
      {loading ? (
        <div className="flex flex-row items-center justify-center py-8">
          <Spinner size="lg" label="Creating the community" />
        </div>
      ) : (
        <form id="create-community-form" onSubmit={handleSubmit} noValidate>
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
                  invalid={!!form.formState.errors.name || nameStatus === 'taken'}
                  aria-describedby={form.formState.errors.name ? 'name-error name-status' : 'name-status'}
                  required
                  disabled={form.formState.isSubmitting || loading}
                />
              )}
            />
            <CommunityNameStatus id="name-status" status={nameStatus} />
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

          {/* Visibility */}
          <div className="mt-4">
            <InputLabel id="visibility-label">Community visibility</InputLabel>
            <Controller
              name="visibility"
              control={form.control}
              render={({ field }) => (
                <SegmentedControl<CommunityVisibility>
                  aria-labelledby="visibility-label"
                  className="mt-1"
                  name={field.name}
                  options={visibilityOptions}
                  value={field.value}
                  onChange={field.onChange}
                  onBlur={field.onBlur}
                  disabled={loading}
                />
              )}
            />
            <InputError className="mt-2" message={form.formState.errors.visibility?.message} />
          </div>
        </form>
      )}
    </Dialog>
  );
}
