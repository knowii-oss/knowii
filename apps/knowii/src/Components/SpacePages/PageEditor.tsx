import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useMemo } from 'react';
import type { LessonForm, Page } from '@knowii/common';
import {
  knowiiApiClient,
  lessonFormSchema,
  MAX_LENGTH_POST_BODY,
  MAX_LENGTH_POST_TITLE,
  pageFormSchema,
  POST_EDITOR_ROWS,
  sectionFormSchema,
  useAppData,
} from '@knowii/common';
import { useImageUploader } from '@/Components/Uploads/use-image-uploader';
import { Button, Dialog, DialogCloseButton, InputError, InputLabel, MarkdownEditor, TextInput } from '@/Components/ui';
import { KeptTextNotice } from '@/Components/KeptText/KeptTextNotice';
import { useKeptText } from '@/Components/KeptText/use-kept-text';

/**
 * page: a page of a page space (#1179). lesson: a lesson of a course space, with an optional video (#1188). section:
 * a heading that groups the pages or lessons after it, a title only (#1188).
 */
export type PageEditorMode = 'page' | 'lesson' | 'section';

export interface PageEditorProps {
  open: boolean;
  communityCuid: string;
  spaceCuid: string;
  spaceName: string;
  mode?: PageEditorMode;
  /**
   * The page to edit (with its Markdown body). Absent: a new page at the end of the space.
   */
  page?: Page | undefined;
  onClose: () => void;
  onSaved: (page: Page) => void;
}

const NOUNS: Record<PageEditorMode, string> = { page: 'page', lesson: 'lesson', section: 'section' };
const SCHEMAS = { page: pageFormSchema, lesson: lessonFormSchema, section: sectionFormSchema };

function text(value: string | boolean | undefined): string {
  return typeof value === 'string' ? value : '';
}

/**
 * Write or edit a page, a lesson or a section: a title, a Markdown text with images, a video for a lesson.
 */
export function PageEditor({ open, communityCuid, spaceCuid, spaceName, mode = 'page', page, onClose, onSaved }: PageEditorProps) {
  const { showToast } = useAppData();
  const images = useImageUploader(communityCuid);
  // Pages have no mentions: the preview renders plain Markdown
  const renderPreview = useMemo(() => (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal), []);
  const values = useMemo(
    () => ({ title: page?.title ?? '', body: page?.body ?? '', video_url: page?.video?.url ?? '' }),
    [page?.title, page?.body, page?.video?.url],
  );
  // One form for the three modes: the schema of the mode decides what is checked
  const form = useForm<LessonForm>({
    resolver: zodResolver(SCHEMAS[mode] as typeof lessonFormSchema),
    defaultValues: values,
    mode: 'onChange',
  });
  const noun = NOUNS[mode];
  // The text of a page or a lesson stays in the browser until it is saved or discarded (#1622): the place is the page
  // for an edit, the space for a new one. A section is a title only and is not kept.
  const kept = useKeptText({
    kind: 'page',
    place: mode === 'section' ? null : (page?.cuid ?? spaceCuid),
    start: values,
    updatedAt: page?.updated_at,
  });
  const { load: loadKeptText, change: keepText } = kept;

  useEffect(() => {
    if (!open) {
      return;
    }
    form.reset(values);
    const fields = loadKeptText();
    if (fields) {
      // The start of the form stays the page: the kept text counts as a change, and a close asks first (#1563)
      form.reset(
        { title: text(fields['title']), body: text(fields['body']), video_url: text(fields['video_url']) },
        { keepDefaultValues: true },
      );
    }
  }, [open, values, form, loadKeptText]);

  // Only what the person types is kept (the fields call it after their change): a reset of the form is not a change
  const keepForm = () => {
    const { title, body, video_url } = form.getValues();
    keepText({ title, body, video_url });
  };

  // Escape, a click outside or "Cancel": after "Discard" when the form had changes (#1563), at once without changes
  const closeByPerson = () => {
    if (form.formState.isDirty) {
      kept.discardForm();
    } else {
      kept.flush();
    }
    onClose();
  };

  // "Use my text" (the page changed on the server after the text was written): the kept text goes in the form
  const putKeptTextInForm = () => {
    // The focus moves first: the button goes away, and a reset forgets the fields until the next render
    form.setFocus('title');
    const fields = kept.use();
    if (fields) {
      form.reset(
        { title: text(fields['title']), body: text(fields['body']), video_url: text(fields['video_url']) },
        { keepDefaultValues: true },
      );
    }
  };

  // "Discard" of the line above the form: the form opens again as without a kept text, and the focus goes to the title
  const discardKeptText = () => {
    form.setFocus('title');
    kept.remove();
    form.reset(values);
  };

  const onSubmit = form.handleSubmit(async (data) => {
    const fields =
      mode === 'section'
        ? { title: data.title }
        : mode === 'lesson'
          ? { title: data.title, body: data.body, video_url: data.video_url }
          : { title: data.title, body: data.body };
    const result = page
      ? await knowiiApiClient.communities.pages.update(communityCuid, spaceCuid, page.cuid, fields)
      : mode === 'section'
        ? await knowiiApiClient.communities.pages.create(communityCuid, spaceCuid, { kind: 'section', title: data.title })
        : await knowiiApiClient.communities.pages.create(communityCuid, spaceCuid, { ...fields, body: data.body });

    if (result.ok) {
      kept.remove();
      const label = noun.charAt(0).toUpperCase() + noun.slice(1);
      showToast({ severity: 'success', summary: page ? `${label} saved` : `${label} created` });
      onSaved(result.data);
      onClose();
      return;
    }
    for (const field of ['title', 'body', 'video_url'] as const) {
      const message = result.errors[field]?.[0];
      if (message) {
        form.setError(field, { message });
      }
    }
    showToast({ severity: 'error', summary: result.message });
  });

  const errors = form.formState.errors;

  return (
    <Dialog
      open={open}
      onClose={closeByPerson}
      size={mode === 'section' ? 'md' : 'lg'}
      title={page ? `Edit the ${noun}` : `New ${noun} in ${spaceName}`}
      description={mode === 'section' ? 'A section is a heading for the lessons or pages that follow it.' : undefined}
      // Escape, a click outside and "Cancel" ask before they lose the text (#1563). A save closes without a question.
      hasUnsavedChanges={form.formState.isDirty && !form.formState.isSubmitting}
      footer={
        <>
          <DialogCloseButton>Cancel</DialogCloseButton>
          <Button type="submit" form="page-form" disabled={!form.formState.isValid} loading={form.formState.isSubmitting}>
            {page ? 'Save' : 'Create'}
          </Button>
        </>
      }
    >
      <form id="page-form" onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <KeptTextNotice state={kept.state} changedAt={kept.changedAt} noun={noun} onUse={putKeptTextInForm} onDiscard={discardKeptText} />
        <div>
          <InputLabel htmlFor="page-title">Title</InputLabel>
          <Controller
            name="title"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="page-title"
                wrapperClassName="mt-1"
                {...field}
                onChange={(event) => {
                  field.onChange(event);
                  keepForm();
                }}
                data-autofocus
                required
                maxLength={MAX_LENGTH_POST_TITLE}
                invalid={!!errors.title}
                aria-describedby={errors.title ? 'page-title-error' : undefined}
              />
            )}
          />
          <InputError id="page-title-error" className="mt-2" message={errors.title?.message} />
        </div>

        {mode === 'lesson' && (
          <div>
            <InputLabel htmlFor="page-video">Video (optional)</InputLabel>
            <Controller
              name="video_url"
              control={form.control}
              render={({ field }) => (
                <TextInput
                  id="page-video"
                  type="url"
                  inputMode="url"
                  wrapperClassName="mt-1"
                  {...field}
                  onChange={(event) => {
                    field.onChange(event);
                    keepForm();
                  }}
                  placeholder="https://www.youtube.com/watch?v=…"
                  invalid={!!errors.video_url}
                  aria-describedby={errors.video_url ? 'page-video-hint page-video-error' : 'page-video-hint'}
                />
              )}
            />
            <p id="page-video-hint" className="mt-1 text-xs text-muted">
              A YouTube or Vimeo address. The player loads only when a member presses Play.
            </p>
            <InputError id="page-video-error" className="mt-2" message={errors.video_url?.message} />
          </div>
        )}

        {mode !== 'section' && (
          <div>
            <InputLabel htmlFor="page-body">Text</InputLabel>
            <div className="mt-1">
              <Controller
                name="body"
                control={form.control}
                render={({ field }) => (
                  <MarkdownEditor
                    id="page-body"
                    value={field.value}
                    onChange={(value) => {
                      field.onChange(value);
                      keepForm();
                    }}
                    onBlur={field.onBlur}
                    ref={field.ref}
                    renderPreview={renderPreview}
                    images={images}
                    rows={POST_EDITOR_ROWS}
                    maxLength={MAX_LENGTH_POST_BODY}
                    invalid={!!errors.body}
                    aria-describedby={errors.body ? 'page-body-error' : undefined}
                  />
                )}
              />
            </div>
            <InputError id="page-body-error" className="mt-2" message={errors.body?.message} />
          </div>
        )}
      </form>
    </Dialog>
  );
}
