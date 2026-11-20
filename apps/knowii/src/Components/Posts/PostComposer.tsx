import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useMemo, useState } from 'react';
import type { KeptTextFields, Post, PostForm } from '@knowii/common';
import { knowiiApiClient, MAX_LENGTH_POST_BODY, MAX_LENGTH_POST_TITLE, POST_EDITOR_ROWS, postFormSchema, useAppData } from '@knowii/common';
import { useMentionSource } from '@/Components/Members/use-mention-source';
import { useImageUploader } from '@/Components/Uploads/use-image-uploader';
import { Button, Checkbox, Dialog, DialogCloseButton, InputError, InputLabel, MarkdownEditor, TextInput } from '@/Components/ui';
import { KeptTextNotice } from '@/Components/KeptText/KeptTextNotice';
import { useKeptText } from '@/Components/KeptText/use-kept-text';

// A new post opens empty. An edit of a post opens with the post and keeps nothing in the browser (#1622).
const EMPTY_POST: KeptTextFields = { title: '', body: '', notify_now: false };

function text(value: string | boolean | undefined): string {
  return typeof value === 'string' ? value : '';
}

export interface PostComposerProps {
  open: boolean;
  communityCuid: string;
  spaceCuid: string;
  spaceName: string;
  /**
   * The post to edit (with its Markdown body). Absent: write a new post.
   */
  post?: Post | undefined;
  /**
   * The member can moderate the space: a new post can tell its followers now, without the 5 minutes of waiting (#1497)
   */
  canModerate?: boolean | undefined;
  onClose: () => void;
  onSaved: (post: Post) => void;
}

export function PostComposer({
  open,
  communityCuid,
  spaceCuid,
  spaceName,
  post,
  canModerate = false,
  onClose,
  onSaved,
}: PostComposerProps) {
  const { showToast } = useAppData();
  const mentions = useMentionSource(communityCuid);
  const images = useImageUploader(communityCuid);
  const renderPreview = useMemo(
    () => (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal, communityCuid),
    [communityCuid],
  );
  // "Tell the followers of this space now": off by default, the post tells them after 5 minutes. Null: the person did
  // not choose, the kept text gives the choice (#1622)
  const [notifyNowChoice, setNotifyNow] = useState<boolean | null>(null);
  const form = useForm<PostForm>({
    resolver: zodResolver(postFormSchema),
    defaultValues: { title: post?.title ?? '', body: post?.body ?? '' },
    mode: 'onChange',
  });
  // The text of a new post stays in the browser until it is published or discarded (#1622)
  const kept = useKeptText({ kind: 'post', place: post ? null : spaceCuid, start: EMPTY_POST });
  const { load: loadKeptText, change: keepText } = kept;
  const notifyNow = notifyNowChoice ?? kept.restored?.['notify_now'] === true;

  useEffect(() => {
    if (!open) {
      return;
    }
    form.reset({ title: post?.title ?? '', body: post?.body ?? '' });
    const fields = loadKeptText();
    if (fields) {
      // The start of the form stays the default: the kept text counts as a change, and a close asks first (#1563)
      form.reset({ title: text(fields['title']), body: text(fields['body']) }, { keepDefaultValues: true });
    }
  }, [open, post, form, loadKeptText]);

  // Only what the person types is kept (the fields call it after their change): a reset of the form is not a change
  const keepForm = (notify = notifyNow) => {
    const { title, body } = form.getValues();
    keepText({ title, body, notify_now: notify });
  };

  const changeNotifyNow = (checked: boolean) => {
    setNotifyNow(checked);
    keepForm(checked);
  };

  // The choice does not stay for the next post
  const close = () => {
    setNotifyNow(null);
    onClose();
  };

  // Escape, a click outside or "Cancel": after "Discard" when the form had changes (#1563), at once without changes
  const closeByPerson = () => {
    if (form.formState.isDirty) {
      kept.discardForm();
    } else {
      kept.flush();
    }
    close();
  };

  // "Discard" of the line above the form: the form is empty again, and the focus goes to the title. The focus moves
  // first: the button goes away, and a reset forgets the fields until the next render.
  const discardKeptText = () => {
    form.setFocus('title');
    kept.remove();
    form.reset({ title: '', body: '' });
    setNotifyNow(null);
  };

  const onSubmit = form.handleSubmit(async (data) => {
    const result = post
      ? await knowiiApiClient.communities.posts.update(communityCuid, spaceCuid, post.cuid, data)
      : await knowiiApiClient.communities.posts.create(
          communityCuid,
          spaceCuid,
          canModerate && notifyNow ? { ...data, notify_now: true } : data,
        );

    if (result.ok) {
      kept.remove();
      showToast({ severity: 'success', summary: post ? 'Post saved' : 'Post published' });
      onSaved(result.data);
      close();
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
      onClose={closeByPerson}
      size="lg"
      title={post ? 'Edit the post' : `New post in ${spaceName}`}
      // Escape, a click outside and "Cancel" ask before they lose the text (#1563). A save closes without a question.
      hasUnsavedChanges={form.formState.isDirty && !form.formState.isSubmitting}
      footer={
        <>
          <DialogCloseButton>Cancel</DialogCloseButton>
          <Button type="submit" form="post-form" disabled={!form.formState.isValid} loading={form.formState.isSubmitting}>
            {post ? 'Save' : 'Publish'}
          </Button>
        </>
      }
    >
      <form id="post-form" onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <KeptTextNotice state={kept.state} changedAt={kept.changedAt} onDiscard={discardKeptText} />
        <div>
          <InputLabel htmlFor="post-title">Title</InputLabel>
          <Controller
            name="title"
            control={form.control}
            render={({ field }) => (
              <TextInput
                id="post-title"
                wrapperClassName="mt-1"
                {...field}
                onChange={(event) => {
                  field.onChange(event);
                  keepForm();
                }}
                autoFocus
                required
                maxLength={MAX_LENGTH_POST_TITLE}
                invalid={!!errors.title}
                aria-describedby={errors.title ? 'post-title-error' : undefined}
              />
            )}
          />
          <InputError id="post-title-error" className="mt-2" message={errors.title?.message} />
        </div>
        <div>
          <InputLabel htmlFor="post-body">Post</InputLabel>
          <div className="mt-1">
            <Controller
              name="body"
              control={form.control}
              render={({ field }) => (
                <MarkdownEditor
                  id="post-body"
                  value={field.value}
                  onChange={(value) => {
                    field.onChange(value);
                    keepForm();
                  }}
                  onBlur={field.onBlur}
                  ref={field.ref}
                  renderPreview={renderPreview}
                  mentions={mentions}
                  images={images}
                  rows={POST_EDITOR_ROWS}
                  maxLength={MAX_LENGTH_POST_BODY}
                  invalid={!!errors.body}
                  aria-describedby={errors.body ? 'post-body-error' : undefined}
                />
              )}
            />
          </div>
          <InputError id="post-body-error" className="mt-2" message={errors.body?.message} />
        </div>
        {!post && canModerate && (
          <label className="flex min-h-11 cursor-pointer items-start gap-3">
            <Checkbox
              name="notify_now"
              checked={notifyNow}
              onChange={(event) => changeNotifyNow(event.target.checked)}
              aria-describedby="post-notify-now-hint"
            />
            <span>
              <span className="font-bold">Tell the followers of this space now</span>
              <span id="post-notify-now-hint" className="block text-sm text-muted">
                Without it, the post tells them after 5 minutes. An edit or a deletion in that time tells nobody.
              </span>
            </span>
          </label>
        )}
      </form>
    </Dialog>
  );
}
