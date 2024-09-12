import AppLayout from '@/Layouts/AppLayout';
import { Button } from 'primereact/button';
import { FormEventHandler, useRef, useState } from 'react';
import { Toast } from 'primereact/toast';
import classNames from 'classnames';
import InputLabel from '@/Components/InputLabel';
import { InputText } from 'primereact/inputtext';
import InputError from '@/Components/InputError';
import FormSection from '@/Components/FormSection';
import { InputTextarea } from 'primereact/inputtextarea';
import { HttpStatus } from '@knowii/common';

export default function CreateCommunity() {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [processing, setProcessing] = useState(false);

  const [nameError, setNameError] = useState('');

  const toastRef = useRef<Toast | null>(null);

  const createCommunity: FormEventHandler = async (e) => {
    e.preventDefault();

    setProcessing(true);

    // TODO implement
    const response = await fetch('/api/v1/communities', {
      method: 'post',
      //referrer: "", // No referer
      headers: new Headers({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      }),
      body: JSON.stringify({
        name,
        description,
      }),
    });

    if (HttpStatus.CREATED === response.status) {
      toastRef.current?.show({
        severity: 'success',
        summary: 'Community created successfully',
      });
    }

    const responseAsJson = await response.json();
    console.log(responseAsJson);
  };

  return (
    <AppLayout title="New Community" pageTitle="New Community">
      <FormSection
        onSubmit={createCommunity}
        title={'Community Information'}
        description={`Provide the details of your new community.`}
        renderActions={() => (
          <>
            <Toast position="bottom-center" ref={toastRef} />

            <Button className={classNames({ 'opacity-25': processing })} disabled={processing} onClick={createCommunity}>
              Create
            </Button>
          </>
        )}
      >
        {/* Name */}
        <div className="col-span-6 sm:col-span-4">
          <InputLabel htmlFor="name">Name</InputLabel>
          <div className="p-inputgroup mt-1">
            <InputText
              id="name"
              type="name"
              className="mt-1 block w-full"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoComplete="name"
              required
              disabled={processing}
            />
          </div>
          <InputError className="mt-2" message={nameError} />
        </div>

        {/* Description */}
        <div className="col-span-6 sm:col-span-4">
          <InputLabel htmlFor="description">Description</InputLabel>
          <div className="p-inputgroup mt-1">
            <InputTextarea
              id="description"
              className="mt-1 block w-full"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              disabled={processing}
            />
          </div>
        </div>
      </FormSection>
    </AppLayout>
  );
}
