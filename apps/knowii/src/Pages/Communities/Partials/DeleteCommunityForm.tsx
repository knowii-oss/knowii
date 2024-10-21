import { FormEventHandler, useRef, useState } from 'react';
import { Button } from 'primereact/button';
import { FaLock } from 'react-icons/fa';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import ActionSection from '@/Components/ActionSection';
import { Dialog } from 'primereact/dialog';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { ProgressSpinner } from 'primereact/progressspinner';
import { DASHBOARD_URL, knowiiApiClient, useAppData } from '@knowii/common';
import { router } from '@inertiajs/react';

interface Props {
  communityName: string;
  communityCuid: string;
}

export default function DeleteCommunityForm(props: Props) {
  const appData = useAppData();
  const toast = appData.toast;

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
      cuid: props.communityCuid,
    });

    if (succeeded) {
      toast?.show({
        severity: 'success',
        summary: 'Community deleted successfully',
      });

      deleteCommunityForm.reset();
      router.visit(route(DASHBOARD_URL), {
        preserveState: false,
      });

      // FIXME redirect to the dashboard
    } else {
      toast?.show({
        severity: 'error',
        summary: 'Failed to delete the community',
      });
    }

    setLoading(false);
  });

  const [confirmingCommunityDeletion, setConfirmingCommunityDeletion] = useState(false);

  const [loading, setLoading] = useState(false);

  const communityNameRef = useRef<HTMLInputElement | null>(null);

  const confirmCommunityDeletion: FormEventHandler = () => {
    setConfirmingCommunityDeletion(true);

    setTimeout(() => communityNameRef.current?.focus(), 250);
  };

  const closeModal = () => {
    setConfirmingCommunityDeletion(false);
    deleteCommunityForm.reset();
  };

  return (
    <ActionSection title={'Delete Community'} description={'Permanently delete this community.'}>
      <div className="max-w-xl text-sm text-gray-600">
        Once your community is deleted, the collections, resources, and interactions will all be permanently removed.
      </div>

      <div className="flex items-center mt-5">
        <Button label="Delete Community" severity="danger" onClick={confirmCommunityDeletion} />
      </div>

      <Dialog
        header="Are you sure you want to delete this community?"
        closeOnEscape={true}
        visible={confirmingCommunityDeletion}
        className="w-full sm:w-[75vw] md:w-[60vw] lg:w-[40vw] xl:w-[30vw]"
        onHide={() => closeModal()}
        footer={
          <>
            <Button severity="secondary" label="Cancel" onClick={closeModal} />
            <Button
              severity="danger"
              label="Go ahead!"
              onClick={deleteCommunity}
              disabled={!deleteCommunityForm.formState.isValid || loading}
              className="ml-2"
            />
          </>
        }
      >
        {loading ? (
          <div className="flex flex-row justify-center items-center">
            <ProgressSpinner />
          </div>
        ) : (
          <>
            Please enter the full community name to confirm you would like to permanently delete it: "{props.communityName}".
            <form onSubmit={deleteCommunity}>
              <div className="mt-4">
                <div className="p-inputgroup mt-1">
                  <span className="p-inputgroup-addon mt-1">
                    <FaLock />
                  </span>

                  <Controller
                    name="communityName"
                    control={deleteCommunityForm.control}
                    render={({ field }) => (
                      <InputText
                        {...field}
                        type="text"
                        className="mt-1 w-full"
                        required
                        aria-invalid={deleteCommunityForm.formState.errors.communityName ? true : false}
                        disabled={deleteCommunityForm.formState.isSubmitting || loading}
                      />
                    )}
                  />
                </div>
                {deleteCommunityForm.formState.errors.communityName && (
                  <InputError className="mt-2" message={deleteCommunityForm.formState.errors.communityName.message} />
                )}
              </div>
            </form>
          </>
        )}
      </Dialog>
    </ActionSection>
  );
}
