import { useState } from 'react';
import { FaLock } from 'react-icons/fa';
import InputError from '@/Components/InputError';
import ActionSection from '@/Components/ActionSection';
import { Button, Dialog, Spinner, TextInput } from '@/Components/ui';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { DASHBOARD_URL, knowiiApiClient, useAppData } from '@knowii/common';
import { router } from '@inertiajs/react';

interface Props {
  communityName: string;
  communityCuid: string;
}

export default function DeleteCommunityForm(props: Props) {
  const { showToast } = useAppData();

  const deleteCommunitySchema = z.object({
    communityName: z.string().regex(new RegExp(`^${props.communityName}$`, 'i'), 'Community name must match exactly'),
  });

  type DeleteCommunityFormData = z.infer<typeof deleteCommunitySchema>;

  const deleteCommunityForm = useForm<DeleteCommunityFormData>({
    resolver: zodResolver(deleteCommunitySchema),
    defaultValues: {
      communityName: '',
    },
    mode: 'onChange',
    reValidateMode: 'onChange',
  });

  const deleteCommunity = deleteCommunityForm.handleSubmit(async () => {
    setLoading(true);

    const succeeded = await knowiiApiClient.communities.delete({
      community: {
        cuid: props.communityCuid,
      },
    });

    if (succeeded) {
      showToast({
        severity: 'success',
        summary: 'Community deleted successfully',
      });

      deleteCommunityForm.reset();
      router.visit(route(DASHBOARD_URL), {
        preserveState: false,
      });
    } else {
      showToast({
        severity: 'error',
        summary: 'Failed to delete the community',
        detail: 'Please try again later.',
      });
    }

    setLoading(false);
  });

  const [confirmingCommunityDeletion, setConfirmingCommunityDeletion] = useState(false);

  const [loading, setLoading] = useState(false);

  const confirmCommunityDeletion = () => {
    setConfirmingCommunityDeletion(true);
  };

  const closeModal = () => {
    setConfirmingCommunityDeletion(false);
    deleteCommunityForm.reset();
  };

  return (
    <ActionSection title={'Delete Community'} description={'Permanently delete this community.'}>
      <div className="max-w-xl text-sm text-muted">
        Once your community is deleted, its spaces, posts, resources and interactions will all be permanently removed.
      </div>

      <div className="mt-5 flex items-center">
        <Button variant="danger" onClick={confirmCommunityDeletion}>
          Delete Community
        </Button>
      </div>

      <Dialog
        open={confirmingCommunityDeletion}
        onClose={closeModal}
        title="Are you sure you want to delete this community?"
        footer={
          <>
            <Button variant="secondary" onClick={closeModal}>
              Cancel
            </Button>
            <Button
              type="submit"
              form="delete-community-form"
              variant="danger"
              disabled={!deleteCommunityForm.formState.isValid}
              loading={loading}
            >
              Go ahead!
            </Button>
          </>
        }
      >
        {loading ? (
          <div className="flex flex-row items-center justify-center py-8">
            <Spinner size="lg" label="Deleting the community" />
          </div>
        ) : (
          <form id="delete-community-form" onSubmit={deleteCommunity} noValidate>
            <label htmlFor="confirm-community-name" className="text-sm">
              Please enter the full community name to confirm you would like to permanently delete it: "{props.communityName}".
            </label>
            <div className="mt-4">
              <Controller
                name="communityName"
                control={deleteCommunityForm.control}
                render={({ field }) => (
                  <TextInput
                    {...field}
                    id="confirm-community-name"
                    type="text"
                    icon={<FaLock />}
                    autoFocus
                    autoComplete="off"
                    required
                    invalid={!!deleteCommunityForm.formState.errors.communityName}
                    disabled={deleteCommunityForm.formState.isSubmitting || loading}
                  />
                )}
              />
              <InputError className="mt-2" message={deleteCommunityForm.formState.errors.communityName?.message} />
            </div>
          </form>
        )}
      </Dialog>
    </ActionSection>
  );
}
