import { Controller, useForm, useWatch } from 'react-hook-form';
import { FaTriangleExclamation } from 'react-icons/fa6';
import { zodResolver } from '@hookform/resolvers/zod';
import type { AccessGroup, Space, SpaceForm, SpaceGroup, SpacePostingPolicy, SpaceType, SpaceVisibility } from '@knowii/common';
import {
  ACCESS_SOURCE_LABELS,
  accessChangeOnMove,
  createSpaceFormWithGroupsSchema,
  DEFAULT_TEXTAREA_ROWS,
  knowiiApiClient,
  SPACE_NOTIFICATION_LEVEL_LABELS,
  SPACE_POSTING_POLICIES,
  SPACE_POSTING_POLICY_LABELS,
  SPACE_TYPE_LABELS,
  SPACE_TYPES,
  SPACE_VISIBILITIES,
  SPACE_VISIBILITY_LABELS,
  openingAccessGroups,
  spaceGroupHasAccessGroups,
  useAppData,
} from '@knowii/common';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { PUBLIC_CHAT_SPACE_FORM_NOTE, PUBLIC_SPACE_FORM_WARNING } from './PublicSpaceNotice';
import { Button, Checkbox, Dialog, DialogCloseButton, Select, Textarea, TextInput } from '@/Components/ui';

export interface SpaceDialogProps {
  open: boolean;
  communityCuid: string;
  spaceGroups: SpaceGroup[];
  /**
   * The space to edit. Absent: create a new space in `defaultSpaceGroupCuid`.
   */
  space?: Space | undefined;
  defaultSpaceGroupCuid?: string | undefined;
  onClose: () => void;
  onSaved: (space: Space) => void;
  /**
   * The address of the page of a space. Given: the toast after a create offers "Open the space" (#1576).
   */
  spaceHref?: ((space: Space) => string) | undefined;
}

const typeOptions = SPACE_TYPES.map((type) => ({
  value: type,
  label: `${SPACE_TYPE_LABELS[type].name}: ${SPACE_TYPE_LABELS[type].description}`,
  icon: SPACE_TYPE_LABELS[type].emoji,
}));
const visibilityOptions = SPACE_VISIBILITIES.map((visibility) => ({ value: visibility, label: SPACE_VISIBILITY_LABELS[visibility] }));
const postingPolicyOptions = SPACE_POSTING_POLICIES.map((policy) => ({ value: policy, label: SPACE_POSTING_POLICY_LABELS[policy] }));

type DefaultLevel = 'all' | 'mine';

const defaultLevelOptions = (type: SpaceType) => [
  ...(type === 'discussion' ? [{ value: 'all' as const, label: SPACE_NOTIFICATION_LEVEL_LABELS.all.name }] : []),
  { value: 'mine' as const, label: SPACE_NOTIFICATION_LEVEL_LABELS.mine.name },
];

// What members get when the manager chose nothing: Everything where only moderators post in a discussion space (#1475)
const builtInDefaultLevel = (type: SpaceType, postingPolicy: SpacePostingPolicy): DefaultLevel =>
  type === 'discussion' && postingPolicy === 'moderators' ? 'all' : 'mine';

function initialValues(space: Space | undefined, defaultSpaceGroupCuid: string | undefined): SpaceForm {
  return {
    name: space?.name ?? '',
    description: space?.description ?? '',
    emoji: space?.emoji ?? '',
    type: space?.type ?? 'discussion',
    visibility: space?.visibility ?? 'members',
    posting_policy: space?.posting_policy ?? 'members',
    space_group_cuid: space?.space_group_cuid ?? defaultSpaceGroupCuid ?? '',
    access_groups: (space?.access_groups ?? []).map((group) => group.cuid),
    url: space?.url ?? '',
    // Undefined: the manager chose nothing, so the field shows the built-in default of "Who can post" and follows it (#1509)
    default_notification_level: space?.default_notification_level ?? undefined,
  };
}

export function SpaceDialog({
  open,
  communityCuid,
  spaceGroups,
  space,
  defaultSpaceGroupCuid,
  onClose,
  onSaved,
  spaceHref,
}: SpaceDialogProps) {
  const { showToast } = useAppData();
  // The access groups of the community, for a "groups" space (#1241)
  const [accessGroups, setAccessGroups] = useState<AccessGroup[] | null>(null);
  // A group given to the space group counts for the rule "a groups space needs a group" (#1422)
  const resolver = useMemo(
    () => zodResolver(createSpaceFormWithGroupsSchema((cuid) => spaceGroupHasAccessGroups(accessGroups ?? [], cuid))),
    [accessGroups],
  );
  const form = useForm<SpaceForm>({
    resolver,
    defaultValues: initialValues(space, defaultSpaceGroupCuid),
    mode: 'onChange',
  });

  // The manager confirms a move that changes who can read the space (#1422): the key of the move that was confirmed
  const [confirmedMove, setConfirmedMove] = useState<string | null>(null);
  useEffect(() => {
    if (open) {
      form.reset(initialValues(space, defaultSpaceGroupCuid));
    }
  }, [open, space, defaultSpaceGroupCuid, form]);

  const visibility = useWatch({ control: form.control, name: 'visibility' });
  // A link space (#1294) opens a page outside Knowii: a URL, and nobody posts in it
  const spaceType = useWatch({ control: form.control, name: 'type' });
  const isLink = spaceType === 'link';
  const postingPolicy = useWatch({ control: form.control, name: 'posting_policy' });
  // Only a change of the manager is sent: an untouched field stays "no choice", shows the built-in default and follows "Who can post"
  const defaultLevelChanged = !!form.formState.dirtyFields.default_notification_level;
  const spaceGroupCuid = useWatch({ control: form.control, name: 'space_group_cuid' });
  const directGroupCuids = useWatch({ control: form.control, name: 'access_groups' });
  useEffect(() => {
    if (!open || visibility !== 'groups' || accessGroups !== null) {
      return;
    }
    let active = true;
    void knowiiApiClient.communities.accessGroups.list(communityCuid).then((result) => {
      if (active) {
        setAccessGroups(result.ok ? result.data : []);
      }
    });
    return () => {
      active = false;
    };
  }, [open, visibility, accessGroups, communityCuid]);

  // A group of the space group may already open the space: check the rule again with the new list
  useEffect(() => {
    if (accessGroups !== null) {
      void form.trigger('access_groups');
    }
  }, [accessGroups, form]);

  // Moving a "groups" space to another space group changes who can read it (#1422, ADR 0041)
  const movesGroupsSpace = space?.visibility === 'groups' && visibility === 'groups' && spaceGroupCuid !== space.space_group_cuid;
  const moveChange =
    movesGroupsSpace && space && accessGroups !== null
      ? accessChangeOnMove(
          accessGroups,
          { directCuids: (space.access_groups ?? []).map((group) => group.cuid), spaceGroupCuid: space.space_group_cuid },
          { directCuids: directGroupCuids, spaceGroupCuid },
        )
      : null;
  const moveKey = moveChange
    ? [spaceGroupCuid, ...moveChange.gain.map((group) => `+${group.cuid}`), ...moveChange.lose.map((group) => `-${group.cuid}`)].join('|')
    : null;
  const needsMoveConfirmation = moveChange !== null && (moveChange.gain.length > 0 || moveChange.lose.length > 0);
  const moveBlocked = movesGroupsSpace && (accessGroups === null || (needsMoveConfirmation && confirmedMove !== moveKey));
  const openingGroups = accessGroups === null ? [] : openingAccessGroups(accessGroups, directGroupCuids, spaceGroupCuid);

  const close = () => {
    setConfirmedMove(null);
    onClose();
  };

  const onSubmit = form.handleSubmit(async (data) => {
    if (moveBlocked) {
      return;
    }
    const { url, default_notification_level: defaultLevel, ...fields } = data;
    const link = data.type === 'link';
    const result = space
      ? await knowiiApiClient.communities.spaces.update(communityCuid, space.cuid, {
          name: data.name,
          description: data.description,
          emoji: data.emoji,
          visibility: data.visibility,
          posting_policy: data.posting_policy,
          space_group_cuid: data.space_group_cuid,
          ...(data.visibility === 'groups' ? { access_groups: data.access_groups } : {}),
          ...(link ? { url } : {}),
          ...(defaultLevelChanged && defaultLevel ? { default_notification_level: defaultLevel } : {}),
        })
      : await knowiiApiClient.communities.spaces.create(communityCuid, link ? { ...fields, url } : fields);

    if (result.ok) {
      if (space) {
        showToast({ severity: 'success', summary: 'Space updated' });
      } else {
        // The next step of a new space is in the space (#1576)
        showToast({
          severity: 'success',
          summary: 'Space created',
          ...(spaceHref ? { action: { label: 'Open the space', href: spaceHref(result.data) } } : {}),
        });
      }
      onSaved(result.data);
      close();
    } else {
      for (const [field, messages] of Object.entries(result.errors)) {
        if (field in data && messages[0]) {
          form.setError(field as keyof SpaceForm, { message: messages[0] });
        }
      }
      showToast({ severity: 'error', summary: result.message });
    }
  });

  const errors = form.formState.errors;
  const groupOptions = spaceGroups.map((group) => ({ value: group.cuid, label: group.name }));

  return (
    <Dialog
      open={open}
      onClose={close}
      size="lg"
      title={space ? `Edit ${space.name}` : 'New space'}
      description={
        space ? 'The type of a space cannot change.' : 'A space holds one kind of content. Choose its type with care: it cannot change.'
      }
      // Escape, a click outside and "Cancel" ask before they lose the changes (#1563). A save closes without a question.
      hasUnsavedChanges={form.formState.isDirty && !form.formState.isSubmitting}
      footer={
        <>
          <DialogCloseButton>Cancel</DialogCloseButton>
          <Button type="submit" form="space-form" disabled={!form.formState.isValid || moveBlocked} loading={form.formState.isSubmitting}>
            {space ? 'Save' : 'Create'}
          </Button>
        </>
      }
    >
      <form id="space-form" onSubmit={onSubmit} noValidate className="grid grid-cols-1 gap-4 sm:grid-cols-6">
        <div className="sm:col-span-5">
          <InputLabel htmlFor="space-name">Name</InputLabel>
          <Controller
            name="name"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="space-name"
                wrapperClassName="mt-1"
                {...field}
                autoFocus
                required
                invalid={!!errors.name}
                aria-describedby={errors.name ? 'space-name-error' : undefined}
              />
            )}
          />
          <InputError id="space-name-error" className="mt-2" message={errors.name?.message} />
        </div>

        <div className="sm:col-span-1">
          <InputLabel htmlFor="space-emoji">Emoji</InputLabel>
          <Controller
            name="emoji"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="space-emoji"
                wrapperClassName="mt-1"
                {...field}
                placeholder="💬"
                invalid={!!errors.emoji}
                aria-describedby={errors.emoji ? 'space-emoji-error' : undefined}
              />
            )}
          />
          <InputError id="space-emoji-error" className="mt-2" message={errors.emoji?.message} />
        </div>

        <div className="sm:col-span-6">
          <InputLabel htmlFor="space-description">Description</InputLabel>
          <Controller
            name="description"
            control={form.control}
            render={({ field }) => (
              <Textarea
                id="space-description"
                wrapperClassName="mt-1"
                rows={DEFAULT_TEXTAREA_ROWS}
                {...field}
                invalid={!!errors.description}
                aria-describedby={errors.description ? 'space-description-error' : undefined}
              />
            )}
          />
          <InputError id="space-description-error" className="mt-2" message={errors.description?.message} />
        </div>

        <div className="sm:col-span-6">
          <InputLabel htmlFor="space-type">Type</InputLabel>
          <Controller
            name="type"
            control={form.control}
            render={({ field }) => (
              <Select<SpaceType>
                id="space-type"
                className="mt-1"
                options={typeOptions}
                value={field.value}
                onChange={(value) => {
                  field.onChange(value);
                  void form.trigger('url');
                }}
                disabled={!!space}
              />
            )}
          />
        </div>

        {isLink && (
          <div className="sm:col-span-6">
            <InputLabel htmlFor="space-url">Address of the page</InputLabel>
            <Controller
              name="url"
              control={form.control}
              render={({ field }) => (
                <TextInput
                  id="space-url"
                  type="url"
                  inputMode="url"
                  wrapperClassName="mt-1"
                  {...field}
                  placeholder="https://example.com"
                  required
                  invalid={!!errors.url}
                  aria-describedby={errors.url ? 'space-url-help space-url-error' : 'space-url-help'}
                />
              )}
            />
            <p id="space-url-help" className="mt-1 text-xs text-muted">
              The navigation opens this page in a new tab. Knowii never opens it.
            </p>
            <InputError id="space-url-error" className="mt-2" message={errors.url?.message} />
          </div>
        )}

        <div className="sm:col-span-3">
          <InputLabel htmlFor="space-group">Space group</InputLabel>
          <Controller
            name="space_group_cuid"
            control={form.control}
            render={({ field }) => (
              <Select<string>
                id="space-group"
                className="mt-1"
                options={groupOptions}
                value={field.value}
                onChange={(value) => {
                  field.onChange(value);
                  // A group of the new space group may open the space
                  void form.trigger('access_groups');
                }}
                invalid={!!errors.space_group_cuid}
              />
            )}
          />
          <InputError className="mt-2" message={errors.space_group_cuid?.message} />
        </div>

        <div className="sm:col-span-3">
          <InputLabel htmlFor="space-visibility">Who can read</InputLabel>
          <Controller
            name="visibility"
            control={form.control}
            render={({ field }) => (
              <Select<SpaceVisibility>
                id="space-visibility"
                className="mt-1"
                options={visibilityOptions}
                value={field.value}
                onChange={(value) => {
                  field.onChange(value);
                  void form.trigger('access_groups');
                }}
              />
            )}
          />
        </div>

        {visibility === 'public' && (
          <div role="note" className="flex flex-col gap-1 rounded-lg border border-border bg-well p-3 text-sm sm:col-span-6">
            <p>{PUBLIC_SPACE_FORM_WARNING}</p>
            {spaceType === 'chat' && <p>{PUBLIC_CHAT_SPACE_FORM_NOTE}</p>}
          </div>
        )}

        {visibility === 'groups' && (
          <fieldset className="sm:col-span-6" aria-describedby="space-access-groups-help">
            <legend className="block text-sm font-bold text-primary">Access groups that can read</legend>
            <p id="space-access-groups-help" className="text-xs text-muted">
              The owner and the admins always see the space. The access groups of its space group open it too. Manage the groups in the
              settings of the community.
            </p>
            {accessGroups === null ? (
              <p className="mt-2 text-sm text-muted">Loading the access groups…</p>
            ) : accessGroups.length === 0 ? (
              <p className="mt-2 text-sm">This community has no access group yet. Create one in the settings first.</p>
            ) : (
              <Controller
                name="access_groups"
                control={form.control}
                render={({ field }) => (
                  <div className="mt-2 grid gap-2 sm:grid-cols-2">
                    {accessGroups.map((group) => (
                      <label key={group.cuid} className="flex cursor-pointer items-center gap-2 text-sm">
                        <Checkbox
                          name={`access-group-${group.cuid}`}
                          checked={field.value.includes(group.cuid)}
                          onChange={(event) => {
                            field.onChange(
                              event.target.checked ? [...field.value, group.cuid] : field.value.filter((cuid) => cuid !== group.cuid),
                            );
                          }}
                        />
                        <span className="font-bold">{group.name}</span>
                      </label>
                    ))}
                  </div>
                )}
              />
            )}
            <InputError className="mt-2" message={errors.access_groups?.message} />
            {accessGroups !== null && accessGroups.length > 0 && (
              <div className="mt-3">
                <p id="space-opening-title" className="text-sm font-bold text-primary">
                  Access groups that open this space
                </p>
                {openingGroups.length === 0 ? (
                  <p className="text-sm text-muted">No access group opens it yet.</p>
                ) : (
                  <ul aria-labelledby="space-opening-title" className="mt-1 flex flex-col gap-1 text-sm">
                    {openingGroups.map((group) => (
                      <li key={group.cuid}>
                        <span className="font-bold">{group.name}</span>{' '}
                        <span className="text-muted">from {group.sources.map((source) => ACCESS_SOURCE_LABELS[source]).join(' and ')}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            )}
          </fieldset>
        )}

        {movesGroupsSpace && (
          <div className="rounded-lg border border-warning bg-warning/10 p-3 text-sm text-primary sm:col-span-6" id="space-move-warning">
            <div role="alert">
              <p className="flex items-center gap-2 font-bold">
                <FaTriangleExclamation aria-hidden="true" className="text-warning" />
                Moving this space changes who can read it
              </p>
              {moveChange === null ? (
                <p className="mt-2">Loading the access groups…</p>
              ) : !needsMoveConfirmation ? (
                <p className="mt-2">No access group gains or loses this space.</p>
              ) : (
                <>
                  {moveChange.gain.length > 0 && (
                    <p className="mt-2">
                      Will gain it: <strong>{moveChange.gain.map((group) => group.name).join(', ')}</strong>.
                    </p>
                  )}
                  {moveChange.lose.length > 0 && (
                    <p className="mt-2">
                      Will lose it: <strong>{moveChange.lose.map((group) => group.name).join(', ')}</strong>.
                    </p>
                  )}
                </>
              )}
            </div>
            {needsMoveConfirmation && (
              <label className="mt-3 flex cursor-pointer items-center gap-2 font-bold">
                <Checkbox
                  name="confirm-space-move"
                  checked={confirmedMove === moveKey}
                  onChange={(event) => setConfirmedMove(event.target.checked ? moveKey : null)}
                />
                <span>I understand, move the space</span>
              </label>
            )}
          </div>
        )}

        {space && !isLink && (
          <div className="sm:col-span-6">
            <InputLabel htmlFor="space-default-level">What members get by default</InputLabel>
            <Controller
              name="default_notification_level"
              control={form.control}
              render={({ field }) => (
                <Select<DefaultLevel>
                  id="space-default-level"
                  className="mt-1"
                  options={defaultLevelOptions(space.type)}
                  value={field.value ?? builtInDefaultLevel(space.type, postingPolicy)}
                  onChange={field.onChange}
                />
              )}
            />
            <p className="mt-1 text-xs text-muted">
              This applies to the members who did not choose for this space. The choice of a member always wins.
              {space.type !== 'discussion' && ' Everything is only for a discussion space.'}
            </p>
          </div>
        )}

        {!isLink && (
          <div className="sm:col-span-6">
            <InputLabel htmlFor="space-posting-policy">Who can post</InputLabel>
            <Controller
              name="posting_policy"
              control={form.control}
              render={({ field }) => (
                <Select<SpacePostingPolicy>
                  id="space-posting-policy"
                  className="mt-1"
                  options={postingPolicyOptions}
                  value={field.value}
                  onChange={field.onChange}
                />
              )}
            />
          </div>
        )}
      </form>
    </Dialog>
  );
}
