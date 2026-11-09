import { useEffect } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { Community, CommunityVisibility, NewCommunity } from '@knowii/common';
import {
  COMMUNITY_VISIBILITY_DESCRIPTIONS,
  communityVisibilityOptions,
  DEFAULT_TEXTAREA_ROWS,
  knowiiApiClient,
  newCommunitySchema,
  useAppData,
} from '@knowii/common';
import { FaCircleInfo } from 'react-icons/fa6';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Card, CardBody, CardHeader, SegmentedControl, Textarea, TextInput } from '@/Components/ui';
import CommunityIcon from './CommunityIcon';

export interface UpdateCommunityFormProps {
  community: Community;
  onSaved?: (community: Community) => void;
}

// A shared community moves between private and public; a personal community stays personal (#1129)
const sharedVisibilityOptions = communityVisibilityOptions
  .filter((option) => option.visibility !== 'personal')
  .map((option) => ({ value: option.visibility, label: option.name, icon: <CommunityIcon visibility={option.visibility} /> }));

const FIELDS = ['name', 'description', 'visibility'] as const;

/**
 * The details of a community, changed by its owner (#1129). The address (slug) of the community stays the same.
 */
export function UpdateCommunityForm({ community, onSaved }: UpdateCommunityFormProps) {
  const { showToast } = useAppData();
  const values = { name: community.name, description: community.description, visibility: community.visibility };
  const form = useForm<NewCommunity>({ resolver: zodResolver(newCommunitySchema), defaultValues: values });
  const { errors } = form.formState;

  useEffect(() => {
    form.reset({ name: community.name, description: community.description, visibility: community.visibility });
  }, [community.name, community.description, community.visibility, form]);

  const onSubmit = form.handleSubmit(async (data) => {
    const result = await knowiiApiClient.communities.update(community.cuid, data);
    if (!result.ok) {
      let fieldError = false;
      for (const [field, messages] of Object.entries(result.errors)) {
        const known = FIELDS.find((name) => name === field);
        if (known && messages[0]) {
          form.setError(known, { message: messages[0] });
          fieldError = true;
        }
      }
      if (!fieldError) {
        showToast({ severity: 'error', summary: result.message });
      }
      return;
    }
    form.reset({ name: result.data.name, description: result.data.description, visibility: result.data.visibility });
    showToast({ severity: 'success', summary: 'Community details saved' });
    onSaved?.(result.data);
  });

  return (
    <Card aria-labelledby="community-details-title">
      <CardHeader title={<span id="community-details-title">Community details</span>} icon={<FaCircleInfo aria-hidden="true" />} />
      <CardBody>
        <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
          <div>
            <InputLabel htmlFor="community-name">Name</InputLabel>
            <Controller
              name="name"
              control={form.control}
              render={({ field }) => (
                <TextInput
                  id="community-name"
                  wrapperClassName="mt-1"
                  {...field}
                  required
                  invalid={!!errors.name}
                  aria-describedby={errors.name ? 'community-name-error community-name-hint' : 'community-name-hint'}
                />
              )}
            />
            <p id="community-name-hint" className="mt-1 text-xs text-muted">
              The address of the community stays the same, so links keep working.
            </p>
            <InputError id="community-name-error" className="mt-2" message={errors.name?.message} />
          </div>

          <div>
            <InputLabel htmlFor="community-description">Description</InputLabel>
            <Controller
              name="description"
              control={form.control}
              render={({ field }) => (
                <Textarea
                  id="community-description"
                  wrapperClassName="mt-1"
                  {...field}
                  rows={DEFAULT_TEXTAREA_ROWS}
                  invalid={!!errors.description}
                  aria-describedby={errors.description ? 'community-description-error' : undefined}
                />
              )}
            />
            <InputError id="community-description-error" className="mt-2" message={errors.description?.message} />
          </div>

          <div>
            <InputLabel id="community-visibility-label">Visibility</InputLabel>
            {community.visibility === 'personal' ? (
              <p className="mt-1 text-sm text-muted">Personal. A personal community stays personal.</p>
            ) : (
              <Controller
                name="visibility"
                control={form.control}
                render={({ field }) => (
                  <>
                    <SegmentedControl<CommunityVisibility>
                      aria-labelledby="community-visibility-label"
                      className="mt-1"
                      name={field.name}
                      options={sharedVisibilityOptions}
                      value={field.value}
                      onChange={field.onChange}
                      onBlur={field.onBlur}
                    />
                    <p className="mt-1 text-xs text-muted">{COMMUNITY_VISIBILITY_DESCRIPTIONS[field.value]}</p>
                  </>
                )}
              />
            )}
            <InputError className="mt-2" message={errors.visibility?.message} />
          </div>

          <div className="flex justify-end">
            <Button type="submit" disabled={!form.formState.isDirty} loading={form.formState.isSubmitting}>
              Save
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}
