import { useId, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { ApiResult, InvitationAccessGroup, InviteLink, InviteLinkForm, MemberTag } from '@knowii/common';
import {
  browserTimeZone,
  instantToZonedDateTime,
  inviteLinkFormSchema,
  knowiiApiClient,
  MAX_INVITE_LINK_USES,
  MAX_LENGTH_INVITE_LINK_NAME,
  timeZoneInWords,
  useAppData,
  zonedDateTimeToIso,
} from '@knowii/common';
import { FaCopy } from 'react-icons/fa6';
import { Button, Dialog, DialogCloseButton, InputError, InputLabel, TextInput, useAccountTimeZone } from '@/Components/ui';
import type { InvitationGrants } from '@/Components/Communities/InvitationGrantsPicker';
import { InvitationGrantsPicker, NO_INVITATION_GRANTS } from '@/Components/Communities/InvitationGrantsPicker';
import { INVITE_LINK_WORDS } from './invite-link-words';
import { useCopyInviteLink } from './use-copy-invite-link';

/**
 * A group of the community, with whether a rule on an entitlement feeds it (a link cannot give it)
 */
export interface InviteLinkGroupChoice extends InvitationAccessGroup {
  fedByRule: boolean;
}

export interface InviteLinkFormDialogProps {
  communityCuid: string;
  open: boolean;
  /**
   * The link to change, or null for a new link. The caller gives a new key for each opening: the form starts from the
   * link, or empty
   */
  link: InviteLink | null;
  accessGroups: readonly InviteLinkGroupChoice[];
  memberTags: readonly MemberTag[];
  canGiveAccessGroups: boolean;
  canGiveMemberTags: boolean;
  onClose: () => void;
  /**
   * After a save: the link as the server answers it
   */
  onSaved: (link: InviteLink) => void;
}

const formSchema = z
  .object({
    name: inviteLinkFormSchema.shape.name,
    ends_date: z.string(),
    ends_time: z.string(),
    max_uses: z.string().refine(
      (value) => {
        const text = value.trim();
        return text === '' || (/^\d+$/.test(text) && Number(text) >= 1 && Number(text) <= MAX_INVITE_LINK_USES);
      },
      { error: `Enter a number from 1 to ${MAX_INVITE_LINK_USES}` },
    ),
  })
  .superRefine((values, context) => {
    if ((values.ends_date === '') !== (values.ends_time === '')) {
      context.addIssue({
        code: 'custom',
        path: [values.ends_date === '' ? 'ends_date' : 'ends_time'],
        message: INVITE_LINK_WORDS.endsNeedsBoth,
      });
    }
  });
type FormInput = z.input<typeof formSchema>;
type FormOutput = z.output<typeof formSchema>;

const EMPTY: FormInput = { name: '', ends_date: '', ends_time: '', max_uses: '' };

/**
 * The same cuids, in any order
 */
function sameItems(a: readonly string[], b: readonly string[]): boolean {
  return a.length === b.length && a.every((item) => b.includes(item));
}

/**
 * The fields of a link as the form shows them: the end in the zone of the account
 */
function valuesOf(link: InviteLink | null, zone: string): FormInput {
  if (link === null) {
    return EMPTY;
  }
  const end = link.expires_at === null ? null : instantToZonedDateTime(link.expires_at, zone);
  return {
    name: link.name,
    ends_date: end?.date ?? '',
    ends_time: end?.time ?? '',
    max_uses: link.max_uses === null ? '' : String(link.max_uses),
  };
}

/**
 * The dialog of a new invite link and of a change (#1391, ADR 0062): the name, the access groups and the tags (the
 * picker of the invitations, where a group that a rule feeds cannot be chosen), the end in the zone of the account and
 * the maximum number of joins, with the warning under the groups and the tags. After a create, the dialog shows the
 * address with "Copy link".
 */
export function InviteLinkFormDialog({
  communityCuid,
  open,
  link,
  accessGroups,
  memberTags,
  canGiveAccessGroups,
  canGiveMemberTags,
  onClose,
  onSaved,
}: InviteLinkFormDialogProps) {
  const { showToast } = useAppData();
  const zone = useAccountTimeZone() ?? browserTimeZone() ?? 'UTC';
  const id = useId();
  const warningId = `${id}-warning`;
  const [grants, setGrants] = useState<InvitationGrants>(() =>
    link === null
      ? NO_INVITATION_GRANTS
      : { accessGroups: link.access_groups.map((group) => group.cuid), memberTags: link.member_tags.map((tag) => tag.cuid) },
  );
  const [grantsChanged, setGrantsChanged] = useState(false);
  // A group that a rule on an entitlement feeds, as the choices or the link know it: a link never gives it. The choices
  // know it only when the feature of the rules is on; the link knows it from the server in every case
  const fedByRule = (cuid: string): boolean =>
    accessGroups.some((group) => group.cuid === cuid && group.fedByRule) ||
    (link?.access_groups.some((group) => group.cuid === cuid && group.fed_by_rule) ?? false);
  const [grantsError, setGrantsError] = useState<string | null>(null);
  // The link that the create made: the dialog shows its address
  const [created, setCreated] = useState<InviteLink | null>(null);
  const copy = useCopyInviteLink();

  const form = useForm<FormInput, unknown, FormOutput>({ resolver: zodResolver(formSchema), defaultValues: valuesOf(link, zone) });
  const { errors } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    let expiresAt: string | null = null;
    if (values.ends_date !== '' && values.ends_time !== '') {
      const instant = zonedDateTimeToIso(values.ends_date, values.ends_time, zone);
      if (instant.kind === 'instant') {
        expiresAt = instant.iso;
      } else if (instant.kind === 'twice') {
        // The first of the two: the link ends at the earlier one
        expiresAt = instant.summer.iso;
      } else {
        form.setError('ends_time', { message: INVITE_LINK_WORDS.endsInvalid });
        return;
      }
    }
    const maxText = values.max_uses.trim();
    const body: InviteLinkForm = {
      name: values.name,
      // Only a group known as fed by a rule leaves the list: a group that the choices do not know (a list that did not
      // load) stays, and the server checks it
      access_groups: grants.accessGroups.filter((cuid) => !fedByRule(cuid)),
      member_tags: grants.memberTags,
      expires_at: expiresAt,
      max_uses: maxText === '' ? null : Number(maxText),
    };

    let result: ApiResult<InviteLink>;
    if (link === null) {
      result = await knowiiApiClient.communities.inviteLinks.create(communityCuid, body);
    } else {
      // An end that did not change is not sent again: the end of an ended link is in the past. The groups and the tags
      // are sent only when the person changed them: an edit of the name never drops a group
      const before = valuesOf(link, zone);
      const endChanged = before.ends_date !== values.ends_date || before.ends_time !== values.ends_time;
      const groupsChanged = !sameItems(
        grants.accessGroups,
        link.access_groups.map((group) => group.cuid),
      );
      const tagsChanged = !sameItems(
        grants.memberTags,
        link.member_tags.map((tag) => tag.cuid),
      );
      result = await knowiiApiClient.communities.inviteLinks.update(communityCuid, link.cuid, {
        name: body.name,
        ...(groupsChanged ? { access_groups: body.access_groups } : {}),
        ...(tagsChanged ? { member_tags: body.member_tags } : {}),
        max_uses: body.max_uses,
        ...(endChanged ? { expires_at: body.expires_at } : {}),
      });
    }

    if (!result.ok) {
      let shown = false;
      for (const [field, messages] of Object.entries(result.errors)) {
        const message = messages[0];
        if (!message) {
          continue;
        }
        if (field === 'name' || field === 'max_uses') {
          form.setError(field, { message });
          shown = true;
        } else if (field === 'expires_at') {
          form.setError('ends_date', { message });
          shown = true;
        } else if (field === 'access_groups' || field === 'member_tags') {
          setGrantsError(message);
          shown = true;
        }
      }
      if (!shown) {
        showToast({ severity: 'error', summary: result.message });
      }
      return;
    }

    onSaved(result.data);
    if (link === null) {
      setCreated(result.data);
      return;
    }
    showToast({ severity: 'success', summary: INVITE_LINK_WORDS.saved(result.data.name) });
    onClose();
  });

  const refusal = (group: InvitationAccessGroup): string | null => (fedByRule(group.cuid) ? INVITE_LINK_WORDS.fedByRuleShort : null);

  const busy = form.formState.isSubmitting;

  if (created !== null) {
    return (
      <Dialog
        open={open}
        onClose={onClose}
        title={INVITE_LINK_WORDS.created}
        description={INVITE_LINK_WORDS.createdDescription}
        footer={<DialogCloseButton variant="primary">{INVITE_LINK_WORDS.done}</DialogCloseButton>}
      >
        <div className="flex flex-col gap-3">
          <InputLabel htmlFor={`${id}-address`}>{INVITE_LINK_WORDS.addressLabel}</InputLabel>
          <TextInput id={`${id}-address`} value={created.url} readOnly onFocus={(event) => event.currentTarget.select()} />
          <div>
            <Button
              variant="secondary"
              leftIcon={<FaCopy aria-hidden="true" />}
              aria-label={INVITE_LINK_WORDS.copyLinkOf(created.name)}
              onClick={() => void copy(created.url)}
              autoFocus
            >
              {INVITE_LINK_WORDS.copyLink}
            </Button>
          </div>
        </div>
      </Dialog>
    );
  }

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={link === null ? INVITE_LINK_WORDS.newTitle : INVITE_LINK_WORDS.editTitle(link.name)}
      hasUnsavedChanges={(form.formState.isDirty || grantsChanged) && !busy}
      footer={
        <>
          <DialogCloseButton>{INVITE_LINK_WORDS.cancel}</DialogCloseButton>
          <Button type="submit" form={`${id}-form`} loading={busy}>
            {link === null ? INVITE_LINK_WORDS.create : INVITE_LINK_WORDS.save}
          </Button>
        </>
      }
    >
      <form id={`${id}-form`} onSubmit={(event) => void onSubmit(event)} noValidate className="flex flex-col gap-5">
        <div>
          <InputLabel htmlFor={`${id}-name`}>{INVITE_LINK_WORDS.nameLabel}</InputLabel>
          <TextInput
            id={`${id}-name`}
            wrapperClassName="mt-1"
            autoFocus
            autoComplete="off"
            maxLength={MAX_LENGTH_INVITE_LINK_NAME + 8}
            placeholder={INVITE_LINK_WORDS.namePlaceholder}
            invalid={!!errors.name}
            aria-describedby={`${id}-name-hint${errors.name ? ` ${id}-name-error` : ''}`}
            {...form.register('name')}
          />
          <p id={`${id}-name-hint`} className="mt-1 text-sm text-muted">
            {INVITE_LINK_WORDS.nameHint}
          </p>
          <InputError id={`${id}-name-error`} className="mt-2" message={errors.name?.message} />
        </div>

        <div className="flex flex-col gap-2">
          <p className="text-sm font-bold">{INVITE_LINK_WORDS.groupsAndTags}</p>
          <InvitationGrantsPicker
            accessGroups={accessGroups}
            memberTags={memberTags}
            canGiveAccessGroups={canGiveAccessGroups}
            canGiveMemberTags={canGiveMemberTags}
            value={grants}
            onChange={(value) => {
              // The picker gives only the items that it shows: a group or a tag that the choices do not know (a list that
              // did not load) stays on the link
              setGrants((current) => ({
                accessGroups: [
                  ...value.accessGroups,
                  ...current.accessGroups.filter((cuid) => !accessGroups.some((group) => group.cuid === cuid)),
                ],
                memberTags: [...value.memberTags, ...current.memberTags.filter((cuid) => !memberTags.some((tag) => tag.cuid === cuid))],
              }));
              setGrantsChanged(true);
              setGrantsError(null);
            }}
            accessGroupRefusal={refusal}
            description={INVITE_LINK_WORDS.pickerDescription}
            tagLimitText={INVITE_LINK_WORDS.tagLimit}
            aria-describedby={warningId}
          />
          <p id={warningId} className="text-sm text-muted">
            {INVITE_LINK_WORDS.warning}
          </p>
          {grantsError && (
            <p role="alert" className="text-sm text-danger-text">
              {grantsError}
            </p>
          )}
        </div>

        <fieldset className="flex flex-col gap-2">
          <legend className="text-sm font-bold">{INVITE_LINK_WORDS.endsLabel}</legend>
          <p id={`${id}-ends-hint`} className="text-sm text-muted">
            {INVITE_LINK_WORDS.endsHint(timeZoneInWords(zone))}
          </p>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <InputLabel htmlFor={`${id}-ends-date`}>{INVITE_LINK_WORDS.endsDate}</InputLabel>
              <TextInput
                id={`${id}-ends-date`}
                type="date"
                wrapperClassName="mt-1"
                invalid={!!errors.ends_date}
                aria-describedby={`${id}-ends-hint${errors.ends_date ? ` ${id}-ends-date-error` : ''}`}
                {...form.register('ends_date')}
              />
              <InputError id={`${id}-ends-date-error`} className="mt-2" message={errors.ends_date?.message} />
            </div>
            <div>
              <InputLabel htmlFor={`${id}-ends-time`}>{INVITE_LINK_WORDS.endsTime}</InputLabel>
              <TextInput
                id={`${id}-ends-time`}
                type="time"
                wrapperClassName="mt-1"
                invalid={!!errors.ends_time}
                aria-describedby={`${id}-ends-hint${errors.ends_time ? ` ${id}-ends-time-error` : ''}`}
                {...form.register('ends_time')}
              />
              <InputError id={`${id}-ends-time-error`} className="mt-2" message={errors.ends_time?.message} />
            </div>
          </div>
        </fieldset>

        <div>
          <InputLabel htmlFor={`${id}-max`}>{INVITE_LINK_WORDS.maxLabel}</InputLabel>
          <TextInput
            id={`${id}-max`}
            type="text"
            inputMode="numeric"
            wrapperClassName="mt-1 sm:max-w-48"
            autoComplete="off"
            invalid={!!errors.max_uses}
            aria-describedby={`${id}-max-hint${errors.max_uses ? ` ${id}-max-error` : ''}`}
            {...form.register('max_uses')}
          />
          <p id={`${id}-max-hint`} className="mt-1 text-sm text-muted">
            {INVITE_LINK_WORDS.maxHint}
          </p>
          <InputError id={`${id}-max-error`} className="mt-2" message={errors.max_uses?.message} />
        </div>
      </form>
    </Dialog>
  );
}
