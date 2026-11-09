import { useEffect } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type { Community, ModeratorPermissions } from '@knowii/common';
import {
  knowiiApiClient,
  MODERATOR_PERMISSION_LABELS,
  MODERATOR_PERMISSIONS,
  moderatorPermissionsSchema,
  useAppData,
} from '@knowii/common';
import { FaUserShield } from 'react-icons/fa6';
import { Button, Card, CardBody, CardHeader, Checkbox } from '@/Components/ui';

export interface ModeratorPermissionsFormProps {
  community: Community;
  onSaved?: (community: Community) => void;
}

/**
 * The rights of the moderators of a community, switched by the owner and the admins (#1240, ADR 0017).
 * Owners and admins always have every right.
 */
export function ModeratorPermissionsForm({ community, onSaved }: ModeratorPermissionsFormProps) {
  const { showToast } = useAppData();
  const form = useForm<ModeratorPermissions>({
    resolver: zodResolver(moderatorPermissionsSchema),
    defaultValues: community.moderator_permissions,
  });

  useEffect(() => {
    form.reset(community.moderator_permissions);
  }, [community.moderator_permissions, form]);

  const onSubmit = form.handleSubmit(async (data) => {
    const result = await knowiiApiClient.communities.updateModeratorPermissions(community.cuid, data);
    if (result.ok) {
      form.reset(result.data.moderator_permissions);
      showToast({ severity: 'success', summary: 'Moderator rights saved' });
      onSaved?.(result.data);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  });

  return (
    <Card aria-labelledby="moderator-rights-title">
      <CardHeader title={<span id="moderator-rights-title">Moderator rights</span>} icon={<FaUserShield aria-hidden="true" />} />
      <CardBody>
        <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
          <p className="text-sm text-muted">What the moderators of this community can do. Owners and admins can always do all of it.</p>
          <fieldset className="flex flex-col gap-3">
            <legend className="sr-only">Moderator rights</legend>
            {MODERATOR_PERMISSIONS.map((permission) => (
              <Controller
                key={permission}
                name={permission}
                control={form.control}
                render={({ field }) => (
                  <label className="flex cursor-pointer items-start gap-3">
                    <Checkbox
                      name={field.name}
                      checked={field.value}
                      onChange={(event) => field.onChange(event.target.checked)}
                      onBlur={field.onBlur}
                      aria-describedby={`moderator-right-${permission}`}
                      className="mt-0.5"
                    />
                    <span className="flex flex-col">
                      <span className="font-bold">{MODERATOR_PERMISSION_LABELS[permission].name}</span>
                      <span id={`moderator-right-${permission}`} className="text-sm text-muted">
                        {MODERATOR_PERMISSION_LABELS[permission].description}
                      </span>
                    </span>
                  </label>
                )}
              />
            ))}
          </fieldset>
          <div className="flex justify-end">
            <Button type="submit" disabled={!form.formState.isDirty} loading={form.formState.isSubmitting}>
              Save
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}
