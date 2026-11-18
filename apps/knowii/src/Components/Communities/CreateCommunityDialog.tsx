import { Controller, useForm, useWatch } from 'react-hook-form';
import { router } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import { zodResolver } from '@hookform/resolvers/zod';

import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import type { NewCommunity, Community, CommunityVisibility } from '@knowii/common';
import {
  COMMUNITY_URL,
  COMMUNITY_VISIBILITY_DESCRIPTIONS,
  communityVisibilityOptions,
  newCommunitySchema,
  knowiiApiClient,
  MIN_ACTION_TIME,
  sleep,
  useAppData,
  DEFAULT_TEXTAREA_ROWS,
  DPA_URL,
} from '@knowii/common';
import { useRef, useState } from 'react';
import CommunityIcon from './CommunityIcon';
import { CommunityNameStatus } from './CommunityNameStatus';
import { useCommunityNameAvailability } from './use-community-name-availability';
import { Button, Dialog, DialogCloseButton, SegmentedControl, Spinner, Textarea, TextInput } from '@/Components/ui';

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
  const route = useRoute();

  const form = useForm<NewCommunity>({
    resolver: zodResolver(newCommunitySchema),
    defaultValues: {
      name: '',
      description: '',
      // Decision of the owner (#1576): "Public" only marks a community for a list that does not exist yet
      visibility: 'private',
    },
    mode: 'onChange',
    reValidateMode: 'onChange',
  });

  const name = useWatch({ control: form.control, name: 'name' });
  const description = useWatch({ control: form.control, name: 'description' });
  const visibility = useWatch({ control: form.control, name: 'visibility' });

  // Personal communities never take a name (ADR 0033): no check for them
  const nameStatus = useCommunityNameAvailability(name, visibility !== 'personal');
  const nameBlocked = nameStatus === 'checking' || nameStatus === 'taken';
  // Only the text counts (#1563): a change of the visibility alone closes the dialog without a question
  const hasText = name !== '' || description !== '';

  const submitRef = useRef<HTMLButtonElement>(null);

  const handleSubmit = form.handleSubmit(async (data: NewCommunity) => {
    setLoading(true);

    await sleep(MIN_ACTION_TIME);

    const response = await knowiiApiClient.communities.create(data);

    if ('success' === response.type && !response.errors) {
      const done = () => {
        showToast({
          severity: 'success',
          summary: 'Community created successfully',
        });
        form.reset();
        setLoading(false);
        props.onHide();
      };

      if (!response.data) {
        done();
        return;
      }

      const createdCommunity = response.data;
      props.onCommunityCreated(createdCommunity);
      // The next step is in the new community (#1576). The dialog stays busy until the page is there: the focus stays on
      // the busy button, and the toast shows on the new page (the Toaster lives outside the pages)
      router.visit(route(COMMUNITY_URL, { communitySlug: createdCommunity.slug }), { onFinish: done });
      return;
    }

    showToast({
      severity: 'error',
      summary: response.message ? response.message : 'Failed to create the community',
      detail: response.message ? response.message : 'Please try again later.',
    });
    setLoading(false);
  });

  return (
    <Dialog
      open={props.visible}
      onClose={props.onHide}
      // A closed dialog keeps no text: "Discard" really discards it
      onAfterClose={() => form.reset()}
      title={
        <>
          <CommunityIcon visibility={null} />
          New community
        </>
      }
      description="Provide the details of your new community."
      // Escape, a click outside and "Cancel" ask before they lose the text (#1563). A create closes without a question.
      hasUnsavedChanges={hasText && !loading}
      footer={
        <>
          <DialogCloseButton>Cancel</DialogCloseButton>
          <Button
            ref={submitRef}
            type="submit"
            form="create-community-form"
            disabled={!form.formState.isValid || nameBlocked}
            loading={loading}
          >
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
        <form
          id="create-community-form"
          onSubmit={(event) => {
            // The spinner takes the place of the form: a field that has the focus (Enter in the name) would lose it. The
            // busy button keeps it instead (#1564).
            if (event.currentTarget.contains(document.activeElement)) {
              submitRef.current?.focus();
            }
            void handleSubmit(event);
          }}
          noValidate
        >
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
                  // The approved texts (#1512), not new ones
                  description={COMMUNITY_VISIBILITY_DESCRIPTIONS[field.value]}
                />
              )}
            />
            <InputError className="mt-2" message={form.formState.errors.visibility?.message} />
          </div>

          {/* A shared community holds the data of other people: the owner accepts the DPA (GDPR art. 28, #1353). Approved
              texts: the first line is the decision of the owner of #1576 */}
          {visibility !== 'personal' && (
            <>
              <p className="mt-4 text-xs text-muted">You will hold the data of your members; this agreement says how.</p>
              <p id="create-community-dpa" className="mt-1 text-xs text-muted">
                By creating a community, you accept the{' '}
                <a href={route(DPA_URL)} target="_blank" rel="noreferrer" className="simple-link">
                  data processing agreement
                </a>
                .
              </p>
            </>
          )}
        </form>
      )}
    </Dialog>
  );
}
