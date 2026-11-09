import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect } from 'react';
import type { Post, PostForm } from '@knowii/common';
import { knowiiApiClient, MAX_LENGTH_POST_BODY, MAX_LENGTH_POST_TITLE, POST_EDITOR_ROWS, postFormSchema, useAppData } from '@knowii/common';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Dialog, MarkdownEditor, TextInput } from '@/Components/ui';

export interface PostComposerProps {
  open: boolean;
  communityCuid: string;
  spaceCuid: string;
  spaceName: string;
  /**
   * The post to edit (with its Markdown body). Absent: write a new post.
   */
  post?: Post | undefined;
  onClose: () => void;
  onSaved: (post: Post) => void;
}

const renderPreview = (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal);

export function PostComposer({ open, communityCuid, spaceCuid, spaceName, post, onClose, onSaved }: PostComposerProps) {
  const { showToast } = useAppData();
  const form = useForm<PostForm>({
    resolver: zodResolver(postFormSchema),
    defaultValues: { title: post?.title ?? '', body: post?.body ?? '' },
    mode: 'onChange',
  });

  useEffect(() => {
    if (open) {
      form.reset({ title: post?.title ?? '', body: post?.body ?? '' });
    }
  }, [open, post, form]);

  const onSubmit = form.handleSubmit(async (data) => {
    const result = post
      ? await knowiiApiClient.communities.posts.update(communityCuid, spaceCuid, post.cuid, data)
      : await knowiiApiClient.communities.posts.create(communityCuid, spaceCuid, data);

    if (result.ok) {
      showToast({ severity: 'success', summary: post ? 'Post saved' : 'Post published' });
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
      title={post ? 'Edit the post' : `New post in ${spaceName}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="post-form" disabled={!form.formState.isValid} loading={form.formState.isSubmitting}>
            {post ? 'Save' : 'Publish'}
          </Button>
        </>
      }
    >
      <form id="post-form" onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
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
                  onChange={field.onChange}
                  onBlur={field.onBlur}
                  ref={field.ref}
                  renderPreview={renderPreview}
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
      </form>
    </Dialog>
  );
}
