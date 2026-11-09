import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { knowiiApiClient, useAppData } from '@knowii/common';
import { FaCamera, FaTrash } from 'react-icons/fa6';
import ActionSection from '@/Components/ActionSection';
import InputError from '@/Components/InputError';
import { Avatar, Button } from '@/Components/ui';

interface Props {
  name: string;
  avatarUrl: string | null | undefined;
}

/**
 * The picture of the member (#109, ADR 0021): uploaded at once, cropped to a square and re-encoded on the server.
 */
export default function UpdateAvatarForm({ name, avatarUrl }: Props) {
  const { showToast } = useAppData();
  const fileInput = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | undefined>(undefined);

  // The new avatar shows everywhere (header, member lists) after a reload of the shared props
  const reloadUser = () => router.reload({ only: ['auth'] });

  const upload = async (file: File) => {
    setBusy(true);
    setError(undefined);
    const result = await knowiiApiClient.users.updateAvatar(file);
    setBusy(false);
    if (fileInput.current) {
      fileInput.current.value = '';
    }
    if (!result.ok) {
      setError(result.errors['file']?.[0] ?? result.message);
      return;
    }
    showToast({ severity: 'success', summary: 'Picture saved' });
    reloadUser();
  };

  const remove = async () => {
    setBusy(true);
    const result = await knowiiApiClient.users.deleteAvatar();
    setBusy(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: 'Picture removed' });
    reloadUser();
  };

  return (
    <ActionSection title="Profile picture" description="The other members see it next to your name. JPEG, PNG, WebP or GIF, up to 10 MB.">
      <div className="flex flex-wrap items-center gap-4">
        <Avatar name={name} imageUrl={avatarUrl} size="xl" />
        <input
          ref={fileInput}
          id="avatar-file"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif"
          className="sr-only"
          tabIndex={-1}
          aria-hidden="true"
          onChange={(event) => {
            const file = event.target.files?.[0];
            if (file) {
              void upload(file);
            }
          }}
        />
        <Button
          variant="secondary"
          loading={busy}
          leftIcon={<FaCamera aria-hidden="true" />}
          aria-describedby={error ? 'avatar-error' : undefined}
          onClick={() => fileInput.current?.click()}
        >
          {avatarUrl ? 'Change the picture' : 'Choose a picture'}
        </Button>
        {avatarUrl && (
          <Button variant="ghost" disabled={busy} leftIcon={<FaTrash aria-hidden="true" />} onClick={() => void remove()}>
            Remove
          </Button>
        )}
      </div>
      <InputError id="avatar-error" className="mt-2" message={error} />
    </ActionSection>
  );
}
