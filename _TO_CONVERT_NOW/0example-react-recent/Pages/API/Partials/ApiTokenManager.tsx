import React, { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import ActionMessage from '@/Components/ActionMessage';
import ActionSection from '@/Components/ActionSection';
import Checkbox from '@/Components/Checkbox';
import ConfirmationModal from '@/Components/ConfirmationModal';
import DangerButton from '@/Components/DangerButton';
import DialogModal from '@/Components/DialogModal';
import FormSection from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SectionBorder from '@/Components/SectionBorder';
import TextInput from '@/Components/TextInput';
import { FormEventHandler } from 'react';

interface ApiTokenManagerProps {
  tokens: Array<any>;
  availablePermissions: Array<string>;
  defaultPermissions: Array<string>;
}

const ApiTokenManager: React.FC<ApiTokenManagerProps> = ({ tokens, availablePermissions, defaultPermissions }) => {
  const page: any = usePage();
  const [displayingToken, setDisplayingToken] = useState(false);
  const [managingPermissionsFor, setManagingPermissionsFor] = useState<any>(null);
  const [apiTokenBeingDeleted, setApiTokenBeingDeleted] = useState<any>(null);

  const createApiTokenForm = useForm({
    name: '',
    permissions: defaultPermissions,
  });

  const updateApiTokenForm = useForm({
    permissions: [] as String[],
  });

  const deleteApiTokenForm = useForm({});

  const createApiToken: FormEventHandler = (e) => {
    e.preventDefault();
    createApiTokenForm.post(route('api-tokens.store'), {
      preserveScroll: true,
      onSuccess: () => {
        setDisplayingToken(true);
        createApiTokenForm.reset();
      },
    });
  };

  const manageApiTokenPermissions = (token: any) => {
    updateApiTokenForm.setData('permissions', token.abilities);
    setManagingPermissionsFor(token);
  };

  const updateApiToken: FormEventHandler = (e) => {
    e.preventDefault();
    updateApiTokenForm.put(route('api-tokens.update', managingPermissionsFor?.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setManagingPermissionsFor(null),
    });
  };

  const confirmApiTokenDeletion = (token: any) => {
    setApiTokenBeingDeleted(token);
  };

  const deleteApiToken: FormEventHandler = (e) => {
    e.preventDefault();

    deleteApiTokenForm.delete(route('api-tokens.destroy', apiTokenBeingDeleted?.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setApiTokenBeingDeleted(null),
    });
  };

  return (
    <div>
      {/* Generate API Token */}
      <FormSection
        onSubmit={createApiToken}
        title="Create API Token"
        description="API tokens allow third-party services to authenticate with our application on your behalf."
        actions={
          <>
            <ActionMessage on={createApiTokenForm.recentlySuccessful}>Created.</ActionMessage>
            <PrimaryButton className={createApiTokenForm.processing ? 'opacity-25' : ''} disabled={createApiTokenForm.processing}>
              Create
            </PrimaryButton>
          </>
        }
      >
        <div className="col-span-6 sm:col-span-4">
          <InputLabel htmlFor="name" value="Name" />
          <TextInput
            id="name"
            value={createApiTokenForm.data.name}
            onChange={(e) => createApiTokenForm.setData('name', e.target.value)}
            type="text"
            className="block w-full mt-1"
            autoFocus
          />
          <InputError message={createApiTokenForm.errors.name} className="mt-2" />
        </div>

        {availablePermissions.length > 0 && (
          <div className="col-span-6">
            <InputLabel htmlFor="permissions" value="Permissions" />
            <div className="grid grid-cols-1 gap-4 mt-2 md:grid-cols-2">
              {availablePermissions.map((permission) => (
                <label className="flex items-center" key={permission}>
                  <Checkbox
                    checked={createApiTokenForm.data.permissions.includes(permission)}
                    onChange={(e) => {
                      if (e.target.checked) {
                        createApiTokenForm.setData('permissions', [...createApiTokenForm.data.permissions, permission]);
                      } else {
                        createApiTokenForm.setData(
                          'permissions',
                          createApiTokenForm.data.permissions.filter((p) => p !== permission),
                        );
                      }
                    }}
                  />
                  <span className="text-sm text-gray-600 ms-2 dark:text-gray-400">{permission}</span>
                </label>
              ))}
            </div>
          </div>
        )}
      </FormSection>

      {tokens.length > 0 && (
        <>
          <SectionBorder />
          <div className="mt-10 sm:mt-0">
            <ActionSection
              title="Manage API Tokens"
              description="You may delete any of your existing tokens if they are no longer needed."
              content={
                <div className="space-y-6">
                  {tokens.map((token) => (
                    <div key={token.id} className="flex items-center justify-between">
                      <div className="break-all dark:text-white">{token.name}</div>
                      <div className="flex items-center ms-2">
                        {token.last_used_ago && <div className="text-sm text-gray-400">Last used {token.last_used_ago}</div>}
                        {availablePermissions.length > 0 && (
                          <button
                            className="text-sm text-gray-400 underline cursor-pointer ms-6"
                            onClick={() => manageApiTokenPermissions(token)}
                          >
                            Permissions
                          </button>
                        )}
                        <button className="text-sm text-red-500 cursor-pointer ms-6" onClick={() => confirmApiTokenDeletion(token)}>
                          Delete
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              }
            />
          </div>
        </>
      )}

      {/* Token Value Modal */}
      <DialogModal
        show={displayingToken}
        onClose={() => setDisplayingToken(false)}
        title="API Token"
        content={
          <>
            <div>Please copy your new API token. For your security, it won't be shown again.</div>
            {page.props.jetstream.flash.token && (
              <div className="px-4 py-2 mt-4 font-mono text-sm text-gray-500 break-all bg-gray-100 rounded dark:bg-gray-900">
                {page.props.jetstream.flash.token}
              </div>
            )}
          </>
        }
        footer={<SecondaryButton onClick={() => setDisplayingToken(false)}>Close</SecondaryButton>}
      />

      {/* API Token Permissions Modal */}
      <DialogModal
        show={!!managingPermissionsFor}
        onClose={() => setManagingPermissionsFor(null)}
        title="API Token Permissions"
        content={
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {availablePermissions.map((permission) => (
              <label className="flex items-center" key={permission}>
                <Checkbox
                  checked={updateApiTokenForm.data.permissions.includes(permission)}
                  onChange={(e) => {
                    if (e.target.checked) {
                      updateApiTokenForm.setData('permissions', [...updateApiTokenForm.data.permissions, permission]);
                    } else {
                      updateApiTokenForm.setData(
                        'permissions',
                        updateApiTokenForm.data.permissions.filter((p) => p !== permission),
                      );
                    }
                  }}
                />
                <span className="text-sm text-gray-600 ms-2 dark:text-gray-400">{permission}</span>
              </label>
            ))}
          </div>
        }
        footer={
          <>
            <SecondaryButton onClick={() => setManagingPermissionsFor(null)}>Cancel</SecondaryButton>
            <PrimaryButton
              className={updateApiTokenForm.processing ? 'ms-3 opacity-25' : 'ms-3'}
              disabled={updateApiTokenForm.processing}
              onClick={updateApiToken}
            >
              Save
            </PrimaryButton>
          </>
        }
      />

      {/* Delete Token Confirmation Modal */}
      <ConfirmationModal
        show={!!apiTokenBeingDeleted}
        onClose={() => setApiTokenBeingDeleted(null)}
        title="Delete API Token"
        content="Are you sure you would like to delete this API token?"
        footer={
          <>
            <SecondaryButton onClick={() => setApiTokenBeingDeleted(null)}>Cancel</SecondaryButton>
            <DangerButton
              className={deleteApiTokenForm.processing ? 'ms-3 opacity-25' : 'ms-3'}
              disabled={deleteApiTokenForm.processing}
              onClick={deleteApiToken}
            >
              Delete
            </DangerButton>
          </>
        }
      />
    </div>
  );
};

export default ApiTokenManager;
