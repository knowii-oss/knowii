import { useEffect } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { CommunityResource, NewCommunityResource, ResourceLevel, Space } from '@knowii/common';
import {
  DEFAULT_TEXTAREA_ROWS,
  knowiiApiClient,
  MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION,
  MAX_LENGTH_COMMUNITY_RESOURCE_NAME,
  newCommunityResourceSchema,
  resourceLevelSchemaOptions,
  useAppData,
} from '@knowii/common';
import ResourceIcon from '@/Components/Resources/ResourceIcon';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Dialog, Select, Textarea, TextInput } from '@/Components/ui';

const levelOptions = resourceLevelSchemaOptions.map((option) => ({ value: option.level, label: option.name, icon: option.icon }));

const EMPTY_FORM: NewCommunityResource = { url: '', name: '', description: '', level: 'unknown' };

interface CreateResourceDialogProps {
  open: boolean;
  communityCuid: string;
  // The resources space that receives the resource (ADR 0015)
  space: Space;
  onClose: () => void;
  onResourceCreated: (newResource: CommunityResource) => void;
}

/**
 * Add a web article to a resources space. The server fetches the page (title, description, cover image, readable
 * content), which takes a few seconds (#1131).
 */
export function CreateResourceDialog({ open, communityCuid, space, onClose, onResourceCreated }: CreateResourceDialogProps) {
  const { showToast } = useAppData();
  const form = useForm<NewCommunityResource>({
    resolver: zodResolver(newCommunityResourceSchema),
    defaultValues: EMPTY_FORM,
    mode: 'onTouched',
  });

  useEffect(() => {
    if (open) {
      form.reset(EMPTY_FORM);
    }
  }, [open, form]);

  const onSubmit = form.handleSubmit(async (data) => {
    const result = await knowiiApiClient.resources.create(communityCuid, space.cuid, data);

    if (result.ok) {
      showToast({ severity: 'success', summary: 'Resource added', detail: 'Knowii captures the page now.' });
      onResourceCreated(result.data);
      onClose();
      return;
    }

    // Validation errors go next to their field; business errors (video URL, duplicate) in the toast
    for (const [field, messages] of Object.entries(result.errors)) {
      if (field in EMPTY_FORM && messages[0]) {
        form.setError(field as keyof NewCommunityResource, { message: messages[0] });
      }
    }
    showToast({ severity: 'error', summary: result.message });
  });

  const errors = form.formState.errors;
  const submitting = form.formState.isSubmitting;

  return (
    <Dialog
      open={open}
      onClose={submitting ? () => undefined : onClose}
      size="lg"
      title={
        <>
          <ResourceIcon />
          Add a resource
        </>
      }
      description={`Share a link in ${space.name}. Knowii then reads the page to get its title, summary, cover image and article.`}
      footer={
        <>
          <Button variant="ghost" onClick={() => form.reset(EMPTY_FORM)} disabled={submitting}>
            Reset
          </Button>
          <Button variant="secondary" onClick={onClose} disabled={submitting}>
            Cancel
          </Button>
          <Button type="submit" form="resource-form" loading={submitting}>
            {submitting ? 'Reading the page…' : 'Add'}
          </Button>
        </>
      }
    >
      <form id="resource-form" onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <div>
          <InputLabel htmlFor="resource-url">Link</InputLabel>
          <Controller
            name="url"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="resource-url"
                type="url"
                wrapperClassName="mt-1"
                {...field}
                autoFocus
                required
                placeholder="https://"
                invalid={!!errors.url}
                aria-describedby={errors.url ? 'resource-url-error' : undefined}
              />
            )}
          />
          <InputError id="resource-url-error" className="mt-2" message={errors.url?.message} />
        </div>

        <div>
          <InputLabel htmlFor="resource-name">Name in this community (optional)</InputLabel>
          <Controller
            name="name"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="resource-name"
                wrapperClassName="mt-1"
                {...field}
                maxLength={MAX_LENGTH_COMMUNITY_RESOURCE_NAME}
                invalid={!!errors.name}
                aria-describedby={errors.name ? 'resource-name-error resource-name-hint' : 'resource-name-hint'}
              />
            )}
          />
          <p id="resource-name-hint" className="mt-1 text-xs text-muted">
            Leave it empty to use the title of the page. The article keeps its own title.
          </p>
          <InputError id="resource-name-error" className="mt-2" message={errors.name?.message} />
        </div>

        <div>
          <InputLabel htmlFor="resource-level">Level</InputLabel>
          <Controller
            name="level"
            control={form.control}
            render={({ field }) => (
              <Select<ResourceLevel>
                id="resource-level"
                className="mt-1"
                options={levelOptions}
                value={field.value}
                onChange={field.onChange}
              />
            )}
          />
          <InputError className="mt-2" message={errors.level?.message} />
        </div>

        <div>
          <InputLabel htmlFor="resource-description">Why it is useful (optional)</InputLabel>
          <Controller
            name="description"
            control={form.control}
            render={({ field }) => (
              <Textarea
                id="resource-description"
                wrapperClassName="mt-1"
                {...field}
                value={field.value ?? ''}
                rows={DEFAULT_TEXTAREA_ROWS}
                maxLength={MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION}
                invalid={!!errors.description}
                aria-describedby={errors.description ? 'resource-description-error' : undefined}
              />
            )}
          />
          <InputError id="resource-description-error" className="mt-2" message={errors.description?.message} />
        </div>
      </form>
    </Dialog>
  );
}
