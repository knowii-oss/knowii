import type { FormEventHandler } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { FaLock } from 'react-icons/fa';
import type { CommunityMember } from '@knowii/common';
import { knowiiApiClient, useAppData } from '@knowii/common';
import ActionSection from '@/Components/ActionSection';
import InputError from '@/Components/InputError';
import type { SelectOption } from '@/Components/ui';
import { Button, Dialog, PasswordInput, Select, Spinner } from '@/Components/ui';

interface Props {
  communityCuid: string;
  communityName: string;
  /**
   * Changes when the members changed: the list of admins loads again
   */
  refreshKey?: number;
  /**
   * Called once the community has a new owner: the page loads its permissions again
   */
  onTransferred: () => void;
}

// The owner comes first, then the admins: one page holds every admin of a normal community
const ADMINS_PAGE_SIZE = 100;

/**
 * The owner gives the community to one of its admins and becomes an admin (#1339, ADR 0035). They confirm with their
 * password: they cannot take the community back by themselves.
 */
export default function TransferOwnershipForm({ communityCuid, communityName, refreshKey = 0, onTransferred }: Props) {
  const { showToast } = useAppData();

  const [open, setOpen] = useState(false);
  const [admins, setAdmins] = useState<CommunityMember[] | null>(null);
  const [memberCuid, setMemberCuid] = useState<string | null>(null);
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);
  const passwordRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    let cancelled = false;
    void knowiiApiClient.communities.members.list(communityCuid, ADMINS_PAGE_SIZE, 0).then((result) => {
      if (cancelled) {
        return;
      }
      if (!result.ok) {
        setAdmins([]);
        showToast({ severity: 'error', summary: result.message });
        return;
      }
      const found = result.data.items.filter((member) => member.role === 'admin');
      setAdmins(found);
      setMemberCuid((current) => (found.some((member) => member.cuid === current) ? current : null));
    });
    return () => {
      cancelled = true;
    };
  }, [open, communityCuid, refreshKey, showToast]);

  // The admins are there: the focus goes to the first field (the dialog holds it while the list loads)
  const hasLoadedAdmins = admins !== null && admins.some((member) => member.role === 'admin');
  useEffect(() => {
    if (open && hasLoadedAdmins) {
      document.getElementById('transfer-ownership-member')?.focus();
    }
  }, [open, hasLoadedAdmins]);

  // A wrong password: the focus goes back to the field, once it is enabled again (a disabled field refuses the focus)
  const passwordError = errors['password']?.[0];
  useEffect(() => {
    if (!busy && passwordError !== undefined) {
      passwordRef.current?.focus();
    }
  }, [busy, passwordError]);

  const options = useMemo<SelectOption<string>[]>(
    () => (admins ?? []).map((member) => ({ value: member.cuid, label: `${member.name} (@${member.username})` })),
    [admins],
  );

  const close = () => {
    if (!busy) {
      setOpen(false);
    }
  };

  const reset = () => {
    setPassword('');
    setErrors({});
    setMemberCuid(null);
    setAdmins(null);
  };

  const transfer: FormEventHandler = async (event) => {
    event.preventDefault();
    if (memberCuid === null) {
      setErrors({ member: ['Choose the new owner.'] });
      return;
    }
    setBusy(true);
    setErrors({});
    const result = await knowiiApiClient.communities.transferOwnership(communityCuid, memberCuid, password);
    setBusy(false);
    if (!result.ok) {
      setErrors(result.errors);
      if (!result.errors['password']) {
        showToast({ severity: 'error', summary: result.message });
      }
      return;
    }
    const newOwner = admins?.find((member) => member.cuid === memberCuid);
    showToast({ severity: 'success', summary: `${newOwner?.name ?? 'The new owner'} now owns ${communityName}` });
    setOpen(false);
    onTransferred();
  };

  const loading = admins === null;
  const hasAdmins = options.length > 0;

  return (
    <ActionSection title="Transfer the ownership" description="Give this community to one of its admins.">
      <div className="max-w-xl text-sm text-muted">
        The new owner can change the details of the community, choose its admins and delete it. You become an admin. Only the new owner can
        give the community back to you.
      </div>

      <div className="mt-5 flex items-center">
        <Button variant="danger" onClick={() => setOpen(true)}>
          Transfer the ownership
        </Button>
      </div>

      <Dialog
        open={open}
        onClose={close}
        onAfterClose={reset}
        title={`Transfer the ownership of ${communityName}?`}
        footer={
          <>
            <Button variant="secondary" onClick={close} disabled={busy}>
              Cancel
            </Button>
            {hasAdmins && (
              <Button type="submit" form="transfer-ownership-form" variant="danger" loading={busy}>
                Transfer
              </Button>
            )}
          </>
        }
      >
        {loading && (
          <div className="flex justify-center py-6">
            <Spinner size="lg" label="Loading the admins" />
          </div>
        )}

        {!loading && !hasAdmins && (
          <p className="text-sm" role="status">
            This community has no admin. Make a member an admin first, in the members list: only an admin can become the owner.
          </p>
        )}

        {!loading && hasAdmins && (
          <form id="transfer-ownership-form" onSubmit={(event) => void transfer(event)} noValidate className="flex flex-col gap-4">
            <div>
              <label htmlFor="transfer-ownership-member" className="text-sm">
                New owner (an admin of the community)
              </label>
              <Select<string>
                id="transfer-ownership-member"
                className="mt-1"
                options={options}
                value={memberCuid}
                onChange={(value) => {
                  setMemberCuid(value);
                  setErrors((current) => ({ ...current, member: [] }));
                }}
                placeholder="Choose an admin"
                invalid={!!errors['member']?.length}
                disabled={busy}
              />
              <InputError className="mt-2" message={errors['member']?.[0]} />
            </div>
            <div>
              <label htmlFor="transfer-ownership-password" className="text-sm">
                Your password, to confirm
              </label>
              <PasswordInput
                id="transfer-ownership-password"
                className="mt-1"
                icon={<FaLock />}
                ref={passwordRef}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
                autoComplete="current-password"
                invalid={!!errors['password']?.length}
                disabled={busy}
              />
              <InputError className="mt-2" message={errors['password']?.[0]} />
            </div>
          </form>
        )}
      </Dialog>
    </ActionSection>
  );
}
