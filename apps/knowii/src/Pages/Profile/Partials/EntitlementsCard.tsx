import { format } from 'date-fns';
import type { AccountEntitlements } from '@knowii/common';
import ActionSection from '@/Components/ActionSection';

interface Props {
  entitlements: AccountEntitlements;
}

function CheckedOn({ value }: { value: string }) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : <time dateTime={date.toISOString()}>{format(date, 'PPP')}</time>;
}

/**
 * What the connected account gives access to (#1386, ADR 0047): the entitlement keys that the sign-in provider reported at the
 * last check, and the date of that check. Only the keys: the provider keeps the details. Shown when the instance reads
 * entitlements and the member connected the provider account.
 */
export default function EntitlementsCard({ entitlements }: Props) {
  return (
    <ActionSection
      title="What your account gives access to"
      description="The communities of this site may open spaces for you because of what your connected account holds."
    >
      {entitlements.keys.length === 0 ? (
        <p className="text-sm">Nothing yet.</p>
      ) : (
        <ul aria-label="What your account gives access to" className="flex flex-wrap gap-2">
          {entitlements.keys.map((key) => (
            <li key={key} className="rounded-md border border-border px-2 py-1 font-mono text-sm">
              {key}
            </li>
          ))}
        </ul>
      )}
      <p className="mt-4 text-sm text-muted">
        {entitlements.checked_at === null ? (
          'Not checked yet.'
        ) : (
          <>
            Last checked on <CheckedOn value={entitlements.checked_at} />.
          </>
        )}{' '}
        It is read again when something changes at the provider, and every night.
      </p>
    </ActionSection>
  );
}
