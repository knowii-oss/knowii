import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useMemo } from 'react';
import type { Page, PageForm } from '@knowii/common';
import { knowiiApiClient, MAX_LENGTH_POST_BODY, MAX_LENGTH_POST_TITLE, POST_EDITOR_ROWS, pageFormSchema, useAppData } from '@knowii/common';
import { useImageUploader } from '@/Components/Uploads/use-image-uploader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Dialog, MarkdownEditor, TextInput } from '@/Components/ui';

export interface PageEditorProps {
  open: boolean;
  communityCuid: string;
  spaceCuid: string;
  spaceName: string;
  /**
   * The page to edit (with its Markdown body). Absent: a new page at the end of the space.
   */
  page?: Page | undefined;
  onClose: () => void;
  onSaved: (page: Page) => void;
}

/**
 * Write or edit a page of a page space (#1179): a title and a Markdown text, with images.
 */
export function PageEditor({ open, communityCuid, spaceCuid, spaceName, page, onClose, onSaved }: PageEditorProps) {
  const { showToast } = useAppData();
  const images = useImageUploader(communityCuid);
  // Pages have no mentions: the preview renders plain Markdown
  const renderPreview = useMemo(() => (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal), []);
  const form = useForm<PageForm>({
    resolver: zodResolver(pageFormSchema),
    defaultValues: { title: page?.title ?? '', body: page?.body ?? '' },
    mode: 'onChange',
  });

  useEffect(() => {
    if (open) {
      form.reset({ title: page?.title ?? '', body: page?.body ?? '' });
    }
  }, [open, page, form]);

  const onSubmit = form.handleSubmit(async (data) => {
    const result = page
      ? await knowiiApiClient.communities.pages.update(communityCuid, spaceCuid, page.cuid, data)
      : await knowiiApiClient.communities.pages.create(communityCuid, spaceCuid, data);

    if (result.ok) {
      showToast({ severity: 'success', summary: page ? 'Page saved' : 'Page created' });
      onSaved(result.data);
      onClose();
      return;
    }
    for (const field of ['title', 'body'] as const) {
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
      onClose={onClose}
      size="lg"
      title={page ? 'Edit the page' : `New page in ${spaceName}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="page-form" disabled={!form.formState.isValid} loading={form.formState.isSubmitting}>
            {page ? 'Save' : 'Create'}
          </Button>
        </>
      }
    >
      <form id="page-form" onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
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
                  onChange={field.onChange}
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
      </form>
    </Dialog>
  );
}
