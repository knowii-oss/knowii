import type { ReactNode } from 'react';
import { useCallback, useEffect, useId, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import type {
  CommunityPermissions,
  MemberTag,
  MemberTagColor,
  MemberTagForm,
  MemberTagFormInput,
  MemberTagVisibility,
} from '@knowii/common';
import {
  DEFAULT_MEMBER_TAG_COLOR,
  DEFAULT_MEMBER_TAG_VISIBILITY,
  knowiiApiClient,
  MAX_LENGTH_MEMBER_TAG_NAME,
  MEMBER_TAG_COLOR_LABELS,
  MEMBER_TAG_COLORS,
  MEMBER_TAG_PERSONAL_DATA_NOTICE,
  MEMBER_TAG_VISIBILITY_LABELS,
  memberTagFormSchema,
  useAppData,
} from '@knowii/common';
import { FaLock, FaPen, FaPlus, FaTags, FaTrash } from 'react-icons/fa6';
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  Dialog,
  DialogCloseButton,
  EmptyState,
  InputError,
  InputLabel,
  SegmentedControl,
  Select,
  TextInput,
} from '@/Components/ui';
import { MemberTagList } from '@/Components/Members/MemberTagList';

export interface MemberTagsManagerProps {
  communityCuid: string;
  permissions: Pick<CommunityPermissions, 'canManageMemberTags'>;
  /**
   * Changes when the tags changed elsewhere on the page (a tag put on a member): the list loads again
   */
  refreshKey?: number;
  /**
   * After a change of a tag (a create, a rename, a delete): the member list of the page shows the tags again
   */
  onChanged?: () => void;
}

const COLOR_OPTIONS = MEMBER_TAG_COLORS.map((color) => ({ value: color, label: MEMBER_TAG_COLOR_LABELS[color] }));

const VISIBILITY_OPTIONS: { value: MemberTagVisibility; label: string; icon?: ReactNode }[] = [
  { value: 'managers', label: MEMBER_TAG_VISIBILITY_LABELS.managers, icon: <FaLock aria-hidden="true" /> },
  { value: 'members', label: MEMBER_TAG_VISIBILITY_LABELS.members },
];

/**
 * "1 member" or "3 members"
 */
export function memberCount(count: number): string {
  return count === 1 ? '1 member' : `${count} members`;
}

/**
 * The question before a tag that members have becomes visible to every member (#1389, decision 16)
 */
export function showToMembersQuestion(count: number): string {
  return `${count === 1 ? '1 member has' : `${count} members have`} this tag. Every member of the community will see it on them.`;
}

/**
 * The question before a delete (#1389, decision 19)
 */
export function deleteQuestion(count: number): string {
  return `${count === 1 ? '1 member loses' : `${count} members lose`} it. This cannot be undone.`;
}

interface MemberTagFieldsProps {
  idPrefix: string;
  form: ReturnType<typeof useForm<MemberTagFormInput, unknown, MemberTagForm>>;
  noticeId: string;
}

/**
 * The fields of a tag: its name, its color with the tag as it shows, who sees it, and the text on personal data
 */
function MemberTagFields({ idPrefix, form, noticeId }: MemberTagFieldsProps) {
  const { errors } = form.formState;
  const name = form.watch('name');
  const color = form.watch('color');
  const visibility = form.watch('visibility');
  const preview = name.trim() === '' ? 'Tag' : name;

  return (
    <div className="flex flex-col gap-4">
      <div>
        <InputLabel htmlFor={`${idPrefix}-name`}>Name</InputLabel>
        <Controller
          name="name"
          control={form.control}
          render={({ field }) => (
            <TextInput
              id={`${idPrefix}-name`}
              wrapperClassName="mt-1"
              autoComplete="off"
              maxLength={MAX_LENGTH_MEMBER_TAG_NAME + 8}
              placeholder="Founder"
              {...field}
              invalid={!!errors.name}
              aria-describedby={errors.name ? `${idPrefix}-name-error` : undefined}
            />
          )}
        />
        <InputError id={`${idPrefix}-name-error`} className="mt-2" message={errors.name?.message} />
      </div>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-4">
        <div className="sm:w-48">
          <InputLabel htmlFor={`${idPrefix}-color`} id={`${idPrefix}-color-label`}>
            Color
          </InputLabel>
          <Controller
            name="color"
            control={form.control}
            render={({ field }) => (
              <Select<MemberTagColor>
                id={`${idPrefix}-color`}
                className="mt-1"
                options={COLOR_OPTIONS}
                value={field.value}
                onChange={field.onChange}
                aria-describedby={`${idPrefix}-preview`}
              />
            )}
          />
        </div>
        <p id={`${idPrefix}-preview`} className="flex min-h-10 items-center gap-2 text-sm text-muted">
          <span>Shows as</span>
          <Badge color={color} icon={visibility === 'managers' ? <FaLock /> : undefined}>
            {preview}
          </Badge>
        </p>
      </div>
      <div>
        <p id={`${idPrefix}-visibility-label`} className="mb-1 text-sm font-bold">
          Who sees it
        </p>
        <Controller
          name="visibility"
          control={form.control}
          render={({ field }) => (
            <SegmentedControl<MemberTagVisibility>
              name={`${idPrefix}-visibility`}
              aria-labelledby={`${idPrefix}-visibility-label`}
              options={VISIBILITY_OPTIONS}
              value={field.value}
              onChange={field.onChange}
              description={
                field.value === 'managers'
                  ? 'The owner, the admins and the moderators who manage members. The tagged member does not see it, except in the export of their data.'
                  : 'Every member of the community, on the members who have it.'
              }
            />
          )}
        />
      </div>
      <p id={noticeId} className="text-sm text-muted">
        {MEMBER_TAG_PERSONAL_DATA_NOTICE}
      </p>
    </div>
  );
}

/**
 * The server errors of a field of the form, or a toast
 */
function applyErrors(
  form: ReturnType<typeof useForm<MemberTagFormInput, unknown, MemberTagForm>>,
  result: { message: string; errors: Record<string, string[]> },
  showError: (message: string) => void,
): void {
  let shown = false;
  for (const [field, messages] of Object.entries(result.errors)) {
    if ((field === 'name' || field === 'color' || field === 'visibility') && messages[0]) {
      form.setError(field, { message: messages[0] });
      shown = true;
    }
  }
  if (!shown) {
    showError(result.message);
  }
}

/**
 * The section "Member tags" of the settings (#1389, ADR 0060): the tags of the community with their color, who sees
 * them and their member count. The owner and the admins create, edit and delete them; a moderator who manages members
 * reads the list, and puts the tags on members from the member list.
 */
export function MemberTagsManager({ communityCuid, permissions, refreshKey = 0, onChanged }: MemberTagsManagerProps) {
  const { showToast } = useAppData();
  const canManage = permissions.canManageMemberTags;
  const [tags, setTags] = useState<MemberTag[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<MemberTag | null>(null);
  // The question before a tag that members have becomes visible to every member
  const [askingVisibility, setAskingVisibility] = useState(false);
  const [deleting, setDeleting] = useState<MemberTag | null>(null);
  const [busy, setBusy] = useState(false);
  const formId = useId();
  const noticeId = `${formId}-notice`;
  const editNoticeId = `${formId}-edit-notice`;

  const showError = useCallback((message: string) => showToast({ severity: 'error', summary: message }), [showToast]);

  const load = useCallback(async () => {
    const result = await knowiiApiClient.communities.memberTags.list(communityCuid);
    setLoading(false);
    if (!result.ok) {
      showError(result.message);
      return;
    }
    setTags(result.data);
  }, [communityCuid, showError]);

  useEffect(() => {
    let active = true;
    void knowiiApiClient.communities.memberTags.list(communityCuid).then((result) => {
      if (!active) {
        return;
      }
      setLoading(false);
      if (!result.ok) {
        showError(result.message);
        return;
      }
      setTags(result.data);
    });
    return () => {
      active = false;
    };
  }, [communityCuid, showError, refreshKey]);

  const createForm = useForm<MemberTagFormInput, unknown, MemberTagForm>({
    resolver: zodResolver(memberTagFormSchema),
    defaultValues: { name: '', color: DEFAULT_MEMBER_TAG_COLOR, visibility: DEFAULT_MEMBER_TAG_VISIBILITY },
  });

  const editForm = useForm<MemberTagFormInput, unknown, MemberTagForm>({
    resolver: zodResolver(memberTagFormSchema),
    defaultValues: { name: '', color: DEFAULT_MEMBER_TAG_COLOR, visibility: DEFAULT_MEMBER_TAG_VISIBILITY },
  });

  const onCreate = createForm.handleSubmit(async (data) => {
    const result = await knowiiApiClient.communities.memberTags.create(communityCuid, data);
    if (!result.ok) {
      applyErrors(createForm, result, showError);
      return;
    }
    // The form stays on the page: the focus goes back in the name field, for the next tag (#1618)
    createForm.setFocus('name');
    createForm.reset({ name: '', color: data.color, visibility: data.visibility });
    showToast({ severity: 'success', summary: `Tag "${result.data.name}" created` });
    void load();
    onChanged?.();
  });

  const openEdit = (tag: MemberTag) => {
    editForm.reset({ name: tag.name, color: tag.color, visibility: tag.visibility });
    setAskingVisibility(false);
    setEditing(tag);
  };

  const closeEdit = () => {
    setEditing(null);
    setAskingVisibility(false);
  };

  const saveEdit = async (data: MemberTagForm) => {
    if (!editing) {
      return;
    }
    setBusy(true);
    const result = await knowiiApiClient.communities.memberTags.update(communityCuid, editing.cuid, data);
    setBusy(false);
    if (!result.ok) {
      setAskingVisibility(false);
      applyErrors(editForm, result, showError);
      return;
    }
    showToast({ severity: 'success', summary: `Tag "${result.data.name}" saved` });
    closeEdit();
    void load();
    onChanged?.();
  };

  const onEdit = editForm.handleSubmit(async (data) => {
    if (!editing) {
      return;
    }
    // A tag that members have, shown to every member at once: ask first
    if (!askingVisibility && editing.visibility === 'managers' && data.visibility === 'members' && (editing.members_count ?? 0) > 0) {
      setAskingVisibility(true);
      return;
    }
    await saveEdit(data);
  });

  const confirmDelete = async () => {
    if (!deleting) {
      return;
    }
    setBusy(true);
    const result = await knowiiApiClient.communities.memberTags.remove(communityCuid, deleting.cuid);
    setBusy(false);
    if (!result.ok) {
      showError(result.message);
      return;
    }
    showToast({ severity: 'success', summary: `Tag "${deleting.name}" deleted` });
    setTags((current) => current.filter((tag) => tag.cuid !== deleting.cuid));
    setDeleting(null);
    onChanged?.();
  };

  return (
    <Card aria-labelledby="member-tags-title">
      <CardHeader title={<span id="member-tags-title">Member tags</span>} icon={<FaTags aria-hidden="true" />} />
      <CardBody className="flex flex-col gap-6">
        <p className="text-sm text-muted">
          Labels on members, for example &quot;Founder&quot;. A tag gives no access: access groups do that. Put tags on members from the
          member list.
        </p>

        {canManage && (
          <form onSubmit={onCreate} noValidate aria-label="Create a tag" aria-describedby={noticeId} className="flex flex-col gap-4">
            <MemberTagFields idPrefix={`${formId}-new`} form={createForm} noticeId={noticeId} />
            <div>
              <Button type="submit" loading={createForm.formState.isSubmitting} leftIcon={<FaPlus aria-hidden="true" />}>
                Create the tag
              </Button>
            </div>
          </form>
        )}

        {!loading && tags.length === 0 ? (
          <EmptyState
            icon={<FaTags aria-hidden="true" />}
            title="No member tags yet"
            description={canManage ? 'Create the first tag with the form above.' : 'The owner and the admins create the tags.'}
          />
        ) : (
          <ul className="flex flex-col divide-y divide-border" aria-label="Member tags" aria-busy={loading}>
            {tags.map((tag) => (
              <li key={tag.cuid} className="flex flex-wrap items-center gap-3 py-3">
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                  <MemberTagList tags={[tag]} />
                  <span className="text-sm text-muted">
                    {MEMBER_TAG_VISIBILITY_LABELS[tag.visibility]}
                    {tag.members_count !== null && <>, {memberCount(tag.members_count)}</>}
                  </span>
                </div>
                {canManage && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="sm"
                      leftIcon={<FaPen aria-hidden="true" />}
                      aria-label={`Edit ${tag.name}`}
                      onClick={() => openEdit(tag)}
                    >
                      Edit
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      leftIcon={<FaTrash aria-hidden="true" />}
                      aria-label={`Delete ${tag.name}`}
                      onClick={() => setDeleting(tag)}
                    >
                      Delete
                    </Button>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardBody>

      <Dialog
        open={editing !== null}
        onClose={closeEdit}
        title={askingVisibility ? 'Show this tag to the members?' : `Edit the tag "${editing?.name ?? ''}"`}
        hasUnsavedChanges={!askingVisibility && editForm.formState.isDirty && !busy}
        footer={
          askingVisibility ? (
            <>
              <Button variant="secondary" onClick={() => setAskingVisibility(false)}>
                Back
              </Button>
              <Button type="submit" form={`${formId}-edit-form`} loading={busy} autoFocus>
                Show it to the members
              </Button>
            </>
          ) : (
            <>
              <DialogCloseButton>Cancel</DialogCloseButton>
              <Button type="submit" form={`${formId}-edit-form`} loading={busy}>
                Save
              </Button>
            </>
          )
        }
      >
        <form id={`${formId}-edit-form`} onSubmit={onEdit} noValidate aria-describedby={askingVisibility ? undefined : editNoticeId}>
          {askingVisibility ? (
            <p className="font-bold">{showToMembersQuestion(editing?.members_count ?? 0)}</p>
          ) : (
            <MemberTagFields idPrefix={`${formId}-edit`} form={editForm} noticeId={editNoticeId} />
          )}
        </form>
      </Dialog>

      <Dialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        size="sm"
        title={`Delete the tag "${deleting?.name ?? ''}"?`}
        description={deleteQuestion(deleting?.members_count ?? 0)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleting(null)} autoFocus>
              Cancel
            </Button>
            <Button variant="danger" onClick={() => void confirmDelete()} loading={busy}>
              Delete
            </Button>
          </>
        }
      />
    </Card>
  );
}
