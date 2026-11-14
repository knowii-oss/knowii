import type { FormEventHandler } from 'react';
import { useEffect, useId, useState } from 'react';
import { FaArrowDown, FaArrowUp, FaFileArrowDown, FaLink, FaPen, FaPlus, FaTrash } from 'react-icons/fa6';
import type { CourseMaterial, CourseMaterialKind } from '@knowii/common';
import {
  COURSE_MATERIAL_EXTENSIONS,
  courseMaterialLinkFormSchema,
  courseMaterialTitleFormSchema,
  formatFileSize,
  isCourseMaterialFileName,
  knowiiApiClient,
  MAX_COURSE_MATERIAL_SIZE_BYTES,
  MAX_COURSE_MATERIALS_PER_TARGET,
  MAX_LENGTH_COURSE_MATERIAL_TITLE,
  safeHttpUrl,
  useAppData,
} from '@knowii/common';
import InputError from '@/Components/InputError';
import { Button, Card, CardBody, CardHeader, Dialog, SegmentedControl, TextInput } from '@/Components/ui';

/**
 * Where materials go: the whole course (pageCuid null), a section or a lesson
 */
export interface CourseMaterialTarget {
  pageCuid: string | null;
  /**
   * Heading of the group. Leave out when the card shows one target only (a lesson page).
   */
  label?: string;
}

interface Props {
  communityCuid: string;
  spaceCuid: string;
  targets: CourseMaterialTarget[];
  canManage: boolean;
  title?: string;
}

interface AddState {
  target: CourseMaterialTarget;
  kind: CourseMaterialKind;
}

const KIND_OPTIONS = [
  { value: 'file', label: 'File' },
  { value: 'link', label: 'Link' },
] as const;

const ACCEPT = COURSE_MATERIAL_EXTENSIONS.map((extension) => `.${extension}`).join(',');

const firstIssue = (issues: { path: PropertyKey[]; message: string }[], field: string) =>
  issues.find((issue) => issue.path[0] === field)?.message;

/**
 * The supplementary materials of a course (#1336, ADR 0037): files that members download and links, for the course,
 * its sections or one lesson. The managers of the course add, rename, reorder and delete them. Members see the card
 * only when it has something.
 */
export function CourseMaterials({ communityCuid, spaceCuid, targets, canManage, title = 'Materials' }: Props) {
  const { showToast } = useAppData();
  const headingId = useId();
  const formId = useId();

  const [materials, setMaterials] = useState<CourseMaterial[] | null>(null);
  const [adding, setAdding] = useState<AddState | null>(null);
  const [renaming, setRenaming] = useState<CourseMaterial | null>(null);
  const [deleting, setDeleting] = useState<CourseMaterial | null>(null);
  const [busy, setBusy] = useState(false);

  // The fields of the add and rename dialogs
  const [titleValue, setTitleValue] = useState('');
  const [urlValue, setUrlValue] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string | undefined>>({});

  useEffect(() => {
    const controller = new AbortController();
    void knowiiApiClient.communities.materials.list(communityCuid, spaceCuid, controller.signal).then((result) => {
      if (controller.signal.aborted) {
        return;
      }
      // A reader keeps an empty card away; a failure is not worth an error for a list that is often empty
      setMaterials(result.ok ? result.data : []);
    });
    return () => controller.abort();
  }, [communityCuid, spaceCuid]);

  const of = (target: CourseMaterialTarget) =>
    (materials ?? []).filter((material) => material.page_cuid === target.pageCuid).sort((a, b) => a.position - b.position);

  const shown = targets.filter((target) => canManage || of(target).length > 0);
  if (materials === null || shown.length === 0) {
    return null;
  }

  const resetFields = () => {
    setTitleValue('');
    setUrlValue('');
    setFile(null);
    setErrors({});
  };

  const add: FormEventHandler = async (event) => {
    event.preventDefault();
    if (adding === null) {
      return;
    }
    const pageCuid = adding.target.pageCuid;

    if (adding.kind === 'link') {
      const parsed = courseMaterialLinkFormSchema.safeParse({ title: titleValue, url: urlValue });
      if (!parsed.success) {
        setErrors({ title: firstIssue(parsed.error.issues, 'title'), url: firstIssue(parsed.error.issues, 'url') });
        return;
      }
      setBusy(true);
      const result = await knowiiApiClient.communities.materials.addLink(communityCuid, spaceCuid, pageCuid, parsed.data);
      setBusy(false);
      if (!result.ok) {
        setErrors({ title: result.errors['title']?.[0], url: result.errors['url']?.[0], form: result.message });
        return;
      }
      setMaterials((current) => [...(current ?? []), result.data]);
    } else {
      const parsed = courseMaterialTitleFormSchema.safeParse({ title: titleValue });
      const fileError =
        file === null
          ? 'Choose a file'
          : !isCourseMaterialFileName(file.name)
            ? 'This type of file is not accepted'
            : file.size > MAX_COURSE_MATERIAL_SIZE_BYTES
              ? `The file is too large (maximum ${formatFileSize(MAX_COURSE_MATERIAL_SIZE_BYTES)})`
              : undefined;
      if (!parsed.success || file === null || fileError !== undefined) {
        setErrors({ title: parsed.success ? undefined : firstIssue(parsed.error.issues, 'title'), file: fileError });
        return;
      }
      setBusy(true);
      const result = await knowiiApiClient.communities.materials.addFile(communityCuid, spaceCuid, pageCuid, {
        title: parsed.data.title,
        file,
      });
      setBusy(false);
      if (!result.ok) {
        setErrors({ title: result.errors['title']?.[0], file: result.errors['file']?.[0], form: result.message });
        return;
      }
      setMaterials((current) => [...(current ?? []), result.data]);
    }

    showToast({ severity: 'success', summary: 'Material added' });
    setAdding(null);
  };

  const rename: FormEventHandler = async (event) => {
    event.preventDefault();
    if (renaming === null) {
      return;
    }
    const isLink = renaming.kind === 'link';
    const parsed = isLink
      ? courseMaterialLinkFormSchema.safeParse({ title: titleValue, url: urlValue })
      : courseMaterialTitleFormSchema.safeParse({ title: titleValue });
    if (!parsed.success) {
      setErrors({ title: firstIssue(parsed.error.issues, 'title'), url: firstIssue(parsed.error.issues, 'url') });
      return;
    }
    setBusy(true);
    const result = await knowiiApiClient.communities.materials.update(communityCuid, spaceCuid, renaming.cuid, parsed.data);
    setBusy(false);
    if (!result.ok) {
      setErrors({ title: result.errors['title']?.[0], url: result.errors['url']?.[0], form: result.message });
      return;
    }
    setMaterials((current) => (current ?? []).map((material) => (material.cuid === result.data.cuid ? result.data : material)));
    setRenaming(null);
  };

  const confirmDelete = async () => {
    if (deleting === null) {
      return;
    }
    setBusy(true);
    const result = await knowiiApiClient.communities.materials.delete(communityCuid, spaceCuid, deleting.cuid);
    setBusy(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setMaterials((current) => (current ?? []).filter((material) => material.cuid !== deleting.cuid));
    setDeleting(null);
  };

  const move = async (target: CourseMaterialTarget, index: number, by: -1 | 1) => {
    const order = of(target).map((material) => material.cuid);
    const other = index + by;
    const moved = order[index];
    const swapped = order[other];
    if (moved === undefined || swapped === undefined) {
      return;
    }
    order[index] = swapped;
    order[other] = moved;
    const result = await knowiiApiClient.communities.materials.reorder(communityCuid, spaceCuid, target.pageCuid, order);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    setMaterials(result.data);
  };

  const row = (target: CourseMaterialTarget, material: CourseMaterial, index: number, count: number) => {
    const link = material.kind === 'link' ? safeHttpUrl(material.url ?? '') : null;
    return (
      <li key={material.cuid} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2">
        {material.kind === 'file' ? (
          <FaFileArrowDown aria-hidden="true" className="shrink-0 text-muted" />
        ) : (
          <FaLink aria-hidden="true" className="shrink-0 text-muted" />
        )}
        <span className="flex min-w-0 grow flex-col">
          {material.kind === 'file' ? (
            <a
              href={knowiiApiClient.communities.materials.downloadUrl(communityCuid, spaceCuid, material.cuid)}
              download={material.file_name ?? true}
              className="touch-target font-bold break-words hover:underline"
            >
              {material.title}
              <span className="sr-only"> (download)</span>
            </a>
          ) : link ? (
            <a href={link} target="_blank" rel="noopener noreferrer" className="touch-target font-bold break-words hover:underline">
              {material.title}
              <span className="sr-only"> (opens in a new tab)</span>
            </a>
          ) : (
            <span className="font-bold break-words">{material.title}</span>
          )}
          <span className="text-xs break-all text-muted">
            {material.kind === 'file'
              ? `${material.file_name ?? 'File'}${material.size_bytes === null ? '' : ` · ${formatFileSize(material.size_bytes)}`}`
              : (material.url ?? '')}
          </span>
        </span>
        {canManage && (
          <span className="flex shrink-0 items-center">
            <Button
              variant="ghost"
              size="sm"
              iconOnly
              aria-label={`Move "${material.title}" up`}
              // aria-disabled, not disabled: a disabled button loses the focus when the material reaches the end of the list
              aria-disabled={index === 0 || undefined}
              onClick={() => index > 0 && void move(target, index, -1)}
            >
              <FaArrowUp aria-hidden="true" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              iconOnly
              aria-label={`Move "${material.title}" down`}
              aria-disabled={index === count - 1 || undefined}
              onClick={() => index < count - 1 && void move(target, index, 1)}
            >
              <FaArrowDown aria-hidden="true" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              iconOnly
              aria-label={`Edit "${material.title}"`}
              onClick={() => {
                resetFields();
                setTitleValue(material.title);
                setUrlValue(material.url ?? '');
                setRenaming(material);
              }}
            >
              <FaPen aria-hidden="true" />
            </Button>
            <Button variant="ghost" size="sm" iconOnly aria-label={`Delete "${material.title}"`} onClick={() => setDeleting(material)}>
              <FaTrash aria-hidden="true" />
            </Button>
          </span>
        )}
      </li>
    );
  };

  return (
    <Card as="section" aria-labelledby={headingId}>
      <CardHeader title={<span id={headingId}>{title}</span>} icon={<FaFileArrowDown aria-hidden="true" />} />
      <CardBody className="flex flex-col gap-4">
        {shown.map((target) => {
          const items = of(target);
          const groupId = `${headingId}-${target.pageCuid ?? 'course'}`;
          return (
            <div key={target.pageCuid ?? 'course'} className="flex flex-col gap-2">
              {target.label !== undefined && (
                <h3 id={groupId} className="text-sm font-extrabold tracking-wide uppercase">
                  {target.label}
                </h3>
              )}
              {items.length > 0 ? (
                <ul
                  aria-labelledby={target.label === undefined ? headingId : groupId}
                  className="flex flex-col divide-y divide-border rounded-lg border border-border bg-well"
                >
                  {items.map((material, index) => row(target, material, index, items.length))}
                </ul>
              ) : (
                <p className="text-sm text-muted">No materials yet.</p>
              )}
              {canManage && items.length < MAX_COURSE_MATERIALS_PER_TARGET && (
                <div>
                  <Button
                    variant="secondary"
                    size="sm"
                    aria-label={target.label === undefined ? 'Add a material' : `Add a material to ${target.label}`}
                    onClick={() => {
                      resetFields();
                      setAdding({ target, kind: 'file' });
                    }}
                  >
                    <FaPlus aria-hidden="true" />
                    Add a material
                  </Button>
                </div>
              )}
            </div>
          );
        })}
      </CardBody>

      <Dialog
        open={adding !== null}
        onClose={() => !busy && setAdding(null)}
        size="sm"
        title={adding?.target.label === undefined ? 'Add a material' : `Add a material to ${adding.target.label}`}
        footer={
          <>
            <Button variant="secondary" onClick={() => setAdding(null)} disabled={busy}>
              Cancel
            </Button>
            <Button type="submit" form={`${formId}-add`} loading={busy}>
              Add
            </Button>
          </>
        }
      >
        <form id={`${formId}-add`} onSubmit={(event) => void add(event)} noValidate className="flex flex-col gap-4">
          <SegmentedControl<CourseMaterialKind>
            aria-label="Kind of material"
            options={KIND_OPTIONS}
            value={adding?.kind ?? 'file'}
            onChange={(kind) => {
              setErrors({});
              setAdding((current) => (current === null ? null : { ...current, kind }));
            }}
            disabled={busy}
          />
          <div>
            <label htmlFor={`${formId}-title`} className="text-sm">
              Title
            </label>
            <TextInput
              id={`${formId}-title`}
              className="mt-1"
              value={titleValue}
              onChange={(event) => setTitleValue(event.target.value)}
              maxLength={MAX_LENGTH_COURSE_MATERIAL_TITLE}
              required
              autoFocus
              invalid={!!errors['title']}
              disabled={busy}
            />
            <InputError className="mt-2" message={errors['title']} />
          </div>
          {adding?.kind === 'link' ? (
            <div>
              <label htmlFor={`${formId}-url`} className="text-sm">
                Address
              </label>
              <TextInput
                id={`${formId}-url`}
                className="mt-1"
                type="url"
                inputMode="url"
                placeholder="https://"
                value={urlValue}
                onChange={(event) => setUrlValue(event.target.value)}
                required
                invalid={!!errors['url']}
                disabled={busy}
              />
              <InputError className="mt-2" message={errors['url']} />
            </div>
          ) : (
            <div>
              <label htmlFor={`${formId}-file`} className="text-sm">
                File
              </label>
              <input
                id={`${formId}-file`}
                type="file"
                accept={ACCEPT}
                className="mt-1 block w-full text-sm file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-overlay/10 file:px-3 file:py-2 file:text-primary"
                aria-describedby={`${formId}-file-hint`}
                aria-invalid={errors['file'] ? true : undefined}
                disabled={busy}
                onChange={(event) => {
                  const chosen = event.target.files?.[0] ?? null;
                  setFile(chosen);
                  setErrors((current) => ({ ...current, file: undefined }));
                  // The name of the file is a good first title
                  if (chosen !== null && titleValue.trim() === '') {
                    setTitleValue(chosen.name.replace(/\.[^.]+$/, '').slice(0, MAX_LENGTH_COURSE_MATERIAL_TITLE));
                  }
                }}
              />
              <p id={`${formId}-file-hint`} className="mt-1 text-xs text-muted">
                PDF, ZIP, EPUB, Markdown, text, CSV, JSON, Office and OpenDocument files, PNG, JPEG or WebP. Maximum{' '}
                {formatFileSize(MAX_COURSE_MATERIAL_SIZE_BYTES)}.
              </p>
              <InputError className="mt-2" message={errors['file']} />
            </div>
          )}
          <InputError message={errors['title'] || errors['url'] || errors['file'] ? undefined : errors['form']} />
        </form>
      </Dialog>

      <Dialog
        open={renaming !== null}
        onClose={() => !busy && setRenaming(null)}
        size="sm"
        title="Edit the material"
        footer={
          <>
            <Button variant="secondary" onClick={() => setRenaming(null)} disabled={busy}>
              Cancel
            </Button>
            <Button type="submit" form={`${formId}-rename`} loading={busy}>
              Save
            </Button>
          </>
        }
      >
        <form id={`${formId}-rename`} onSubmit={(event) => void rename(event)} noValidate className="flex flex-col gap-4">
          <div>
            <label htmlFor={`${formId}-rename-title`} className="text-sm">
              Title
            </label>
            <TextInput
              id={`${formId}-rename-title`}
              className="mt-1"
              value={titleValue}
              onChange={(event) => setTitleValue(event.target.value)}
              maxLength={MAX_LENGTH_COURSE_MATERIAL_TITLE}
              required
              autoFocus
              invalid={!!errors['title']}
              disabled={busy}
            />
            <InputError className="mt-2" message={errors['title']} />
          </div>
          {renaming?.kind === 'link' && (
            <div>
              <label htmlFor={`${formId}-rename-url`} className="text-sm">
                Address
              </label>
              <TextInput
                id={`${formId}-rename-url`}
                className="mt-1"
                type="url"
                inputMode="url"
                value={urlValue}
                onChange={(event) => setUrlValue(event.target.value)}
                required
                invalid={!!errors['url']}
                disabled={busy}
              />
              <InputError className="mt-2" message={errors['url']} />
            </div>
          )}
          <InputError message={errors['title'] || errors['url'] ? undefined : errors['form']} />
        </form>
      </Dialog>

      <Dialog
        open={deleting !== null}
        onClose={() => !busy && setDeleting(null)}
        size="sm"
        title={`Delete "${deleting?.title ?? ''}"?`}
        description={
          deleting?.kind === 'file' ? 'The file is deleted for good. Members cannot download it any more.' : 'The link is removed.'
        }
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleting(null)} autoFocus disabled={busy}>
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
