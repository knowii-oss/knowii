import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { useRoute } from 'ziggy-js';
import type { ApiToken, Permission } from '@knowii/common';
import {
  API_TOKEN_PERMISSION_LABELS,
  API_TOKENS_DESTROY_URL,
  API_TOKENS_STORE_URL,
  API_TOKENS_UPDATE_URL,
  useAppData,
} from '@knowii/common';
import { FaKey } from 'react-icons/fa6';
import AppLayout from '@/Layouts/AppLayout';
import { breadcrumbHome } from '@/Components/BreadcrumbHome';
import ActionSection from '@/Components/ActionSection';
import FormSection from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Separator from '@/Components/Separator';
import type { BreadcrumbItem } from '@/Components/ui';
import { Button, Checkbox, Dialog, TextInput } from '@/Components/ui';

interface Props {
  tokens: ApiToken[];
  availablePermissions: Permission[];
  defaultPermissions: Permission[];
}

function PermissionChoices(props: { idPrefix: string; available: Permission[]; value: string[]; onChange: (value: string[]) => void }) {
  return (
    <fieldset className="grid gap-2 sm:grid-cols-2">
      <legend className="sr-only">Permissions</legend>
      {props.available.map((permission) => (
        <label key={permission} className="flex cursor-pointer items-start gap-2 text-sm">
          <Checkbox
            name={`${props.idPrefix}-${permission}`}
            checked={props.value.includes(permission)}
            onChange={(event) =>
              props.onChange(event.target.checked ? [...props.value, permission] : props.value.filter((item) => item !== permission))
            }
            className="mt-0.5"
          />
          <span>
            <span className="font-bold">{API_TOKEN_PERMISSION_LABELS[permission] ?? permission}</span>
            <code className="block text-xs text-muted">{permission}</code>
          </span>
        </label>
      ))}
    </fieldset>
  );
}

/**
 * Personal API tokens (#689, ADR 0022): create a token with the abilities it needs, see it once, change its abilities,
 * delete it. Scripts and the browser extension (ADR 0018) use them.
 */
export default function ApiTokensPage({ tokens, availablePermissions, defaultPermissions }: Props) {
  const route = useRoute();
  const { showToast } = useAppData();
  const createForm = useForm({ name: '', permissions: [...defaultPermissions] as string[] });
  const updateForm = useForm({ permissions: [] as string[] });
  const [managing, setManaging] = useState<ApiToken | null>(null);
  const [deleting, setDeleting] = useState<ApiToken | null>(null);
  const [newToken, setNewToken] = useState<string | null>(null);

  const breadcrumbItems: BreadcrumbItem[] = [{ label: 'API tokens', icon: <FaKey /> }];

  const create = () => {
    createForm.post(route(API_TOKENS_STORE_URL), {
      preserveScroll: true,
      onSuccess: (response) => {
        const flash = (response.props as unknown as { jetstream: { flash: { token?: string } } }).jetstream.flash;
        setNewToken(flash.token ?? null);
        createForm.reset();
      },
    });
  };

  const openManage = (token: ApiToken) => {
    updateForm.setData('permissions', token.abilities);
    setManaging(token);
  };

  const update = () => {
    if (!managing) {
      return;
    }
    updateForm.put(route(API_TOKENS_UPDATE_URL, { token: managing.id }), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setManaging(null);
        showToast({ severity: 'success', summary: 'Permissions saved' });
      },
    });
  };

  const destroy = () => {
    if (!deleting) {
      return;
    }
    router.delete(route(API_TOKENS_DESTROY_URL, { token: deleting.id }), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setDeleting(null);
        showToast({ severity: 'success', summary: 'Token deleted' });
      },
    });
  };

  const copy = async () => {
    if (newToken) {
      await navigator.clipboard.writeText(newToken);
      showToast({ severity: 'success', summary: 'Token copied' });
    }
  };

  return (
    <AppLayout contentWidth="form" browserPageTitle="API tokens" breadcrumbItems={breadcrumbItems} breadcrumbHome={breadcrumbHome()}>
      <FormSection
        onSubmit={(event) => {
          event.preventDefault();
          create();
        }}
        title="Create an API token"
        description="Scripts, tools and the browser extension use a token instead of your password. Give each one only the permissions it needs."
        renderActions={() => (
          <Button type="submit" loading={createForm.processing} disabled={createForm.data.name.trim() === ''}>
            Create
          </Button>
        )}
      >
        <div className="col-span-6 sm:col-span-4">
          <InputLabel htmlFor="token-name">Name</InputLabel>
          <TextInput
            id="token-name"
            wrapperClassName="mt-1"
            value={createForm.data.name}
            onChange={(event) => createForm.setData('name', event.target.value)}
            placeholder="For example: browser extension"
            invalid={!!createForm.errors.name}
            aria-describedby={createForm.errors.name ? 'token-name-error' : undefined}
          />
          <InputError id="token-name-error" className="mt-2" message={createForm.errors.name} />
        </div>
        <div className="col-span-6">
          <InputLabel>Permissions</InputLabel>
          <div className="mt-2">
            <PermissionChoices
              idPrefix="create"
              available={availablePermissions}
              value={createForm.data.permissions}
              onChange={(value) => createForm.setData('permissions', value)}
            />
          </div>
        </div>
      </FormSection>

      {tokens.length > 0 && (
        <>
          <Separator />
          <ActionSection
            title="Your API tokens"
            description="Delete a token that you no longer use. A deleted token stops working at once."
          >
            <ul className="flex flex-col divide-y divide-border" aria-label="Your API tokens">
              {tokens.map((token) => (
                <li key={token.id} className="flex flex-wrap items-center gap-3 py-3">
                  <div className="flex min-w-0 flex-1 flex-col">
                    <span className="truncate font-bold">{token.name}</span>
                    <span className="text-sm text-muted">
                      {token.last_used_ago ? `Last used ${token.last_used_ago}` : 'Never used'}
                      {token.expires_at && `, expires ${new Date(token.expires_at).toLocaleString()}`}
                    </span>
                  </div>
                  <Button variant="ghost" size="sm" onClick={() => openManage(token)} aria-label={`Permissions of ${token.name}`}>
                    Permissions
                  </Button>
                  <Button variant="ghost" size="sm" onClick={() => setDeleting(token)} aria-label={`Delete ${token.name}`}>
                    Delete
                  </Button>
                </li>
              ))}
            </ul>
          </ActionSection>
        </>
      )}

      <Dialog
        open={newToken !== null}
        onClose={() => setNewToken(null)}
        title="Your new API token"
        description="Copy it now: you will not see it again. Keep it in a password manager."
        footer={
          <>
            <Button variant="secondary" onClick={() => void copy()}>
              Copy
            </Button>
            <Button onClick={() => setNewToken(null)}>Close</Button>
          </>
        }
      >
        <code className="block rounded-lg bg-well p-3 font-mono text-sm break-all select-all">{newToken}</code>
      </Dialog>

      <Dialog
        open={managing !== null}
        onClose={() => setManaging(null)}
        title={`Permissions of ${managing?.name ?? ''}`}
        footer={
          <>
            <Button variant="secondary" onClick={() => setManaging(null)}>
              Cancel
            </Button>
            <Button onClick={update} loading={updateForm.processing}>
              Save
            </Button>
          </>
        }
      >
        <PermissionChoices
          idPrefix="update"
          available={availablePermissions}
          value={updateForm.data.permissions}
          onChange={(value) => updateForm.setData('permissions', value)}
        />
      </Dialog>

      <Dialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        size="sm"
        title={`Delete ${deleting?.name ?? ''}?`}
        description="The token stops working at once. Scripts that use it fail."
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleting(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={destroy}>
              Delete
            </Button>
          </>
        }
      />
    </AppLayout>
  );
}
