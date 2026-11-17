import { useState } from 'react';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { OIDC_CONNECT_URL, knowiiApiClient, useAppData, useTypedPage } from '@knowii/common';
import type { ConnectedAccount } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import ActionSection from '@/Components/ActionSection';
import ConfirmsPassword from '@/Components/ConfirmsPassword';
import { Button } from '@/Components/ui';

interface Props {
  accounts: ConnectedAccount[];
  hasPassword: boolean;
}

function LinkedOn({ value }: { value: string }) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : <time dateTime={date.toISOString()}>{format(date, 'PPP')}</time>;
}

/**
 * The accounts of other providers that the member connected to sign in (#1385, ADR 0045): connected or not, since when,
 * "Connect" (the flow of the provider, which comes back here) and "Disconnect" (always allowed: the account keeps its
 * address, so a sign-in link or a password reset still works). Never shows the identifier at the provider.
 */
export default function ConnectedAccountsForm({ accounts, hasPassword }: Props) {
  const page = useTypedPage();
  const route = useRoute();
  // The server answers with the address of the provider, as a full page visit
  const connect = () => router.post(route(OIDC_CONNECT_URL));
  const { showToast } = useAppData();
  const [busy, setBusy] = useState<string | null>(null);
  const oidc = page.props.oidc;

  if (!oidc) {
    return null;
  }

  // The account at the provider of this instance. Accounts of another provider (an earlier configuration) are listed
  // too, so that the member can remove them
  const hasCurrent = accounts.some((account) => account.current);

  const disconnect = async (account: ConnectedAccount) => {
    setBusy(account.cuid);
    const result = await knowiiApiClient.users.identities.remove(account.cuid);
    setBusy(null);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: `${account.label} is disconnected` });
    router.reload({ only: ['connectedAccounts', 'hasPassword', 'entitlements'] });
  };

  return (
    <ActionSection title="Connected accounts" description="Sign in with an account of another service, instead of a password.">
      {oidc.error && (
        <p role="alert" className="mb-4 text-sm font-medium text-danger-text">
          {oidc.error}
        </p>
      )}
      {oidc.status && (
        <p role="status" className="mb-4 text-sm font-medium text-success">
          {oidc.status}
        </p>
      )}

      <ul className="flex flex-col gap-4">
        {accounts.map((account) => (
          <li key={account.cuid} className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="font-bold">{account.label}</p>
              <p className="text-sm text-muted">
                Connected on <LinkedOn value={account.linked_at} />
              </p>
            </div>
            <Button
              variant="secondary"
              size="sm"
              loading={busy === account.cuid}
              aria-label={`Disconnect ${account.label}`}
              onClick={() => void disconnect(account)}
            >
              Disconnect
            </Button>
          </li>
        ))}
        {!hasCurrent && (
          <li className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="font-bold">{oidc.label}</p>
              <p className="text-sm text-muted">Not connected</p>
            </div>
            {/* A POST with the CSRF token, after the password: a link could be opened by another site (#1385) */}
            {hasPassword ? (
              <ConfirmsPassword onConfirm={connect}>
                {(start) => (
                  <Button variant="secondary" size="sm" aria-label={`Connect ${oidc.label}`} onClick={start}>
                    Connect
                  </Button>
                )}
              </ConfirmsPassword>
            ) : (
              <Button variant="secondary" size="sm" aria-label={`Connect ${oidc.label}`} onClick={connect}>
                Connect
              </Button>
            )}
          </li>
        )}
      </ul>

      {!hasPassword && (
        <p className="mt-4 text-sm text-muted">
          This account has no password: you sign in with {oidc.label} or with a link sent to your email address, and you set a password with
          &ldquo;Forgot your password?&rdquo; on the sign-in page.
        </p>
      )}
    </ActionSection>
  );
}
