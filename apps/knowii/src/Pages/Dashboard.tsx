import AppLayout from '@/Layouts/AppLayout';
import CommunityBox from '@/Components/CommunityBox';
import { COMMUNITY_API_BASE_PATH, HttpStatus, NewCommunity } from '@knowii/common';
import CardGroup from '@/Components/CardGroup';
import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import React, { useRef, useState } from 'react';
import { Toast } from 'primereact/toast';
import InputLabel from '@/Components/InputLabel';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import { InputTextarea } from 'primereact/inputtextarea';
import { ProgressSpinner } from 'primereact/progressspinner';
import { useForm } from 'react-hook-form';

export default function Dashboard() {
  const toastRef = useRef<Toast | null>(null);

  const [loading, setLoading] = useState(false);
  const [creatingCommunity, setCreatingCommunity] = useState(false);

  const form = useForm<NewCommunity>();

  const createCommunity = async () => {
    setCreatingCommunity(true);

    // FIXME show loading spinner
    setLoading(true);

    const newCommunity: NewCommunity = {
      name: form.getValues('name'),
      description: form.getValues('description'),
      // FIXME add support for creating public ones
      personal_community: true,
    };

    // FIXME extract request making logic to reusable function
    const response = await fetch(COMMUNITY_API_BASE_PATH, {
      method: 'post',
      headers: new Headers({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
      body: JSON.stringify(newCommunity),
    });

    //const responseAsJson = await response.json();

    if (HttpStatus.CREATED === response.status) {
      closeCreateCommunityModal();
      form.reset();
      toastRef.current?.show({
        severity: 'success',
        summary: 'Community created successfully',
      });

      // FIXME extract new community details from the response and add to the list of communities
    } else {
      form.setError('name', { message: 'Name is already taken' });
      toastRef.current?.show({
        severity: 'error',
        summary: 'Failed to create the community',
      });

      // FIXME load error details and show
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
        {/* TODO add link to open the given community page */}
        <CardGroup className="mt-4">
          {/* TODO add link to open the given community page. The link should use the slug */}
          {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20].map((item) => (
            <CommunityBox
              key={item}
              community={{
                name: `Community ${item}`,
                description: `This is the description for community ${item}.`,
                cuid: '',
                created_at: '',
                updated_at: '',
                personal_community: false,
              }}
              creationMode={false}
              link={'/communities/' + item}
            />
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
            <Button label="Go ahead!" onClick={createCommunity} disabled={!form.formState.isValid || loading} className="ml-2" />
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
              </div>
            </form>
          </>
        )}
      </Dialog>
    </>
  );
}
