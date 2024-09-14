import AppLayout from '@/Layouts/AppLayout';
import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { useRef, useState } from 'react';
import { Toast } from 'primereact/toast';
import { InputText } from 'primereact/inputtext';
import { InputTextarea } from 'primereact/inputtextarea';
import { ProgressSpinner } from 'primereact/progressspinner';
import { useForm } from 'react-hook-form';

import InputError from '@/Components/InputError';
import CommunityBox from '@/Components/CommunityBox';
import { Community, knowiiApiClient, NewCommunity } from '@knowii/common';
import CardGroup from '@/Components/CardGroup';
import InputLabel from '@/Components/InputLabel';
import { InputSwitch } from 'primereact/inputswitch';
import { useImmer } from 'use-immer';

export default function Dashboard() {
  const toastRef = useRef<Toast | null>(null);

  const [loading, setLoading] = useState(false);
  const [creatingCommunity, setCreatingCommunity] = useState(false);

  const [communities, updateCommunities] = useImmer<Community[]>([]);

  const form = useForm<NewCommunity>({
    defaultValues: {
      name: '',
      description: '',
      // New communities are personal by default
      personal: true,
    },
  });

  const createCommunity = async () => {
    setCreatingCommunity(true);
    setLoading(true);

    const newCommunity: NewCommunity = {
      name: form.getValues().name,
      description: form.getValues().description,
      personal: form.getValues().personal ? form.getValues().personal : false,
    };

    const response = await knowiiApiClient.createCommunity(newCommunity);

    if ('success' === response.type && !response.errors) {
      closeCreateCommunityModal();
      form.reset();
      toastRef.current?.show({
        severity: 'success',
        summary: 'Community created successfully',
      });

      if (response.data) {
        updateCommunities((draft) => {
          // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
          draft.push(response.data!);
        });
      }
    } else {
      toastRef.current?.show({
        severity: 'error',
        summary: 'Failed to create the community',
        detail: response.message,
      });
    }

    setLoading(false);
  };

  const openCreateCommunityModal = () => {
    setCreatingCommunity(true);
  };

  const closeCreateCommunityModal = () => {
    setCreatingCommunity(false);
    form.reset();
  };

  return (
    <>
      <AppLayout title="Dashboard" pageTitle="Dashboard">
        <Toast position="bottom-center" ref={toastRef} />
        <h2 className="text-2xl font-bold mb-6 text-gray-800 border-b-2 border-primary-500 pb-2 block text-center sm:text-left">
          My Communities
        </h2>
        <CardGroup className="mt-4">
          {/* TODO add link to open the given community page. The link should use the slug */}
          {communities.map((item) => (
            <CommunityBox key={item.cuid} community={item} creationMode={false} link={`/communities/${item.cuid}`} />
          ))}
          <CommunityBox creationMode={true} clickHandler={openCreateCommunityModal} />
        </CardGroup>
      </AppLayout>
      <Dialog
        header="New community"
        closeOnEscape={true}
        visible={creatingCommunity}
        className="w-full sm:w-[75vw] md:w-[60vw] lg:w-[40vw] xl:w-[30vw]"
        onHide={() => closeCreateCommunityModal()}
        footer={
          <>
            <Button severity="secondary" label="Cancel" onClick={closeCreateCommunityModal} />
            <Button onClick={createCommunity} label="Go ahead!" disabled={!form.formState.isValid || loading} className="ml-2" />
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
            <form>
              {/* Name */}
              <div className="mt-4 col-span-6 sm:col-span-4">
                <InputLabel htmlFor="name">Name</InputLabel>
                <div className="p-inputgroup mt-1">
                  <InputText
                    id="name"
                    type="name"
                    className="mt-1 block w-full"
                    {...form.register('name', {
                      required: true,
                      validate: (value) => value.trim().length >= 3 && value.trim().length <= 255,
                      // TODO add validation constraints
                      // Reference: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/apps/knowii/pages/communities/create/index.tsx
                      // add regex pattern
                    })}
                    aria-invalid={form.formState.errors.name ? true : false}
                    autoComplete="name"
                    required
                    disabled={form.formState.isSubmitting || form.formState.isLoading}
                  />
                </div>

                {form.formState.errors.name && <InputError className="mt-2" message={form.formState.errors.name.message} />}
              </div>
              {/* Description */}
              <div className="mt-4 col-span-6 sm:col-span-4">
                <InputLabel htmlFor="description">Description</InputLabel>
                <div className="p-inputgroup mt-1">
                  <InputTextarea
                    id="description"
                    className="mt-1 block w-full"
                    {...form.register('description', {
                      required: false,
                      maxLength: 255,
                    })}
                    aria-invalid={form.formState.errors.description ? true : false}
                    disabled={loading}
                  />
                </div>

                {/* Personal */}
                <div className="mt-4">
                  <InputLabel htmlFor="description">Personal (only for your eyes)</InputLabel>
                  <InputSwitch
                    id="personal"
                    className="mt-1 block"
                    {...form.register('personal')}
                    onChange={(e) => {
                      form.setValue('personal', e.target.value);
                      form.trigger('personal');
                    }}
                    checked={form.watch('personal')}
                    disabled={loading}
                  />
                </div>
              </div>
            </form>
          </>
        )}
      </Dialog>
    </>
  );
}
