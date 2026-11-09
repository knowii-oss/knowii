import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { Space, SpaceForm, SpaceGroup, SpacePostingPolicy, SpaceType, SpaceVisibility } from '@knowii/common';
import {
  DEFAULT_TEXTAREA_ROWS,
  knowiiApiClient,
  SPACE_POSTING_POLICIES,
  SPACE_POSTING_POLICY_LABELS,
  SPACE_TYPE_LABELS,
  SPACE_TYPES,
  SPACE_VISIBILITIES,
  SPACE_VISIBILITY_LABELS,
  spaceFormSchema,
  useAppData,
} from '@knowii/common';
import { useEffect } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Dialog, Select, Textarea, TextInput } from '@/Components/ui';

export interface SpaceDialogProps {
  open: boolean;
  communityCuid: string;
  spaceGroups: SpaceGroup[];
  /**
   * The space to edit. Absent: create a new space in `defaultSpaceGroupCuid`.
   */
  space?: Space | undefined;
  defaultSpaceGroupCuid?: string | undefined;
  onClose: () => void;
  onSaved: (space: Space) => void;
}

const typeOptions = SPACE_TYPES.map((type) => ({
  value: type,
  label: `${SPACE_TYPE_LABELS[type].name}: ${SPACE_TYPE_LABELS[type].description}`,
  icon: SPACE_TYPE_LABELS[type].emoji,
}));
const visibilityOptions = SPACE_VISIBILITIES.map((visibility) => ({ value: visibility, label: SPACE_VISIBILITY_LABELS[visibility] }));
const postingPolicyOptions = SPACE_POSTING_POLICIES.map((policy) => ({ value: policy, label: SPACE_POSTING_POLICY_LABELS[policy] }));

function initialValues(space: Space | undefined, defaultSpaceGroupCuid: string | undefined): SpaceForm {
  return {
    name: space?.name ?? '',
    description: space?.description ?? '',
    emoji: space?.emoji ?? '',
    type: space?.type ?? 'discussion',
    visibility: space?.visibility ?? 'members',
    posting_policy: space?.posting_policy ?? 'members',
    space_group_cuid: space?.space_group_cuid ?? defaultSpaceGroupCuid ?? '',
  };
}

export function SpaceDialog({ open, communityCuid, spaceGroups, space, defaultSpaceGroupCuid, onClose, onSaved }: SpaceDialogProps) {
  const { showToast } = useAppData();
  const form = useForm<SpaceForm>({
    resolver: zodResolver(spaceFormSchema),
    defaultValues: initialValues(space, defaultSpaceGroupCuid),
    mode: 'onChange',
  });

  useEffect(() => {
    if (open) {
      form.reset(initialValues(space, defaultSpaceGroupCuid));
    }
  }, [open, space, defaultSpaceGroupCuid, form]);

  const onSubmit = form.handleSubmit(async (data) => {
    const result = space
      ? await knowiiApiClient.communities.spaces.update(communityCuid, space.cuid, {
          name: data.name,
          description: data.description,
          emoji: data.emoji,
          visibility: data.visibility,
          posting_policy: data.posting_policy,
          space_group_cuid: data.space_group_cuid,
        })
      : await knowiiApiClient.communities.spaces.create(communityCuid, data);

    if (result.ok) {
      showToast({ severity: 'success', summary: space ? 'Space updated' : 'Space created' });
      onSaved(result.data);
      onClose();
    } else {
      for (const [field, messages] of Object.entries(result.errors)) {
        if (field in data && messages[0]) {
          form.setError(field as keyof SpaceForm, { message: messages[0] });
        }
      }
      showToast({ severity: 'error', summary: result.message });
    }
  });

  const errors = form.formState.errors;
  const groupOptions = spaceGroups.map((group) => ({ value: group.cuid, label: group.name }));

  return (
    <Dialog
      open={open}
      onClose={onClose}
      size="lg"
      title={space ? `Edit ${space.name}` : 'New space'}
      description={
        space ? 'The type of a space cannot change.' : 'A space holds one kind of content. Choose its type with care: it cannot change.'
      }
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="space-form" disabled={!form.formState.isValid} loading={form.formState.isSubmitting}>
            {space ? 'Save' : 'Create'}
          </Button>
        </>
      }
    >
      <form id="space-form" onSubmit={onSubmit} noValidate className="grid grid-cols-1 gap-4 sm:grid-cols-6">
        <div className="sm:col-span-5">
          <InputLabel htmlFor="space-name">Name</InputLabel>
          <Controller
            name="name"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="space-name"
                wrapperClassName="mt-1"
                {...field}
                autoFocus
                required
                invalid={!!errors.name}
                aria-describedby={errors.name ? 'space-name-error' : undefined}
              />
            )}
          />
          <InputError id="space-name-error" className="mt-2" message={errors.name?.message} />
        </div>

        <div className="sm:col-span-1">
          <InputLabel htmlFor="space-emoji">Emoji</InputLabel>
          <Controller
            name="emoji"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="space-emoji"
                wrapperClassName="mt-1"
                {...field}
                placeholder="💬"
                invalid={!!errors.emoji}
                aria-describedby={errors.emoji ? 'space-emoji-error' : undefined}
              />
            )}
          />
          <InputError id="space-emoji-error" className="mt-2" message={errors.emoji?.message} />
        </div>

        <div className="sm:col-span-6">
          <InputLabel htmlFor="space-description">Description</InputLabel>
          <Controller
            name="description"
            control={form.control}
            render={({ field }) => (
              <Textarea
                id="space-description"
                wrapperClassName="mt-1"
                rows={DEFAULT_TEXTAREA_ROWS}
                {...field}
                invalid={!!errors.description}
                aria-describedby={errors.description ? 'space-description-error' : undefined}
              />
            )}
          />
          <InputError id="space-description-error" className="mt-2" message={errors.description?.message} />
        </div>

        <div className="sm:col-span-6">
          <InputLabel htmlFor="space-type">Type</InputLabel>
          <Controller
            name="type"
            control={form.control}
            render={({ field }) => (
              <Select<SpaceType>
                id="space-type"
                className="mt-1"
                options={typeOptions}
                value={field.value}
                onChange={field.onChange}
                disabled={!!space}
              />
            )}
          />
        </div>

        <div className="sm:col-span-3">
          <InputLabel htmlFor="space-group">Space group</InputLabel>
          <Controller
            name="space_group_cuid"
            control={form.control}
            render={({ field }) => (
              <Select<string>
                id="space-group"
                className="mt-1"
                options={groupOptions}
                value={field.value}
                onChange={field.onChange}
                invalid={!!errors.space_group_cuid}
              />
            )}
          />
          <InputError className="mt-2" message={errors.space_group_cuid?.message} />
        </div>

        <div className="sm:col-span-3">
          <InputLabel htmlFor="space-visibility">Who can read</InputLabel>
          <Controller
            name="visibility"
            control={form.control}
            render={({ field }) => (
              <Select<SpaceVisibility>
                id="space-visibility"
                className="mt-1"
                options={visibilityOptions}
                value={field.value}
                onChange={field.onChange}
              />
            )}
          />
        </div>

        <div className="sm:col-span-6">
          <InputLabel htmlFor="space-posting-policy">Who can post</InputLabel>
          <Controller
            name="posting_policy"
            control={form.control}
            render={({ field }) => (
              <Select<SpacePostingPolicy>
                id="space-posting-policy"
                className="mt-1"
                options={postingPolicyOptions}
                value={field.value}
                onChange={field.onChange}
              />
            )}
          />
        </div>
      </form>
    </Dialog>
  );
}
