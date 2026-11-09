import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useId } from 'react';
import type { CommentForm as CommentFormValues } from '@knowii/common';
import { COMMENT_EDITOR_ROWS, commentFormSchema, knowiiApiClient, MAX_LENGTH_COMMENT_BODY } from '@knowii/common';
import InputError from '@/Components/InputError';
import { Button, MarkdownEditor } from '@/Components/ui';

export interface CommentFormProps {
  /**
   * Accessible name of the editor ("Write a comment", "Reply to Ada")
   */
  label: string;
  submitLabel: string;
  initialBody?: string;
  autoFocus?: boolean;
  /**
   * Saves the comment. Returns the field error to show, or null on success (the form then clears itself).
   */
  onSubmit: (values: CommentFormValues) => Promise<string | null>;
  onCancel?: () => void;
}

const renderPreview = (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal);

/**
 * Comment, reply and edit form: a compact Markdown editor with its buttons.
 */
export function CommentForm({ label, submitLabel, initialBody = '', autoFocus = false, onSubmit, onCancel }: CommentFormProps) {
  const id = useId();
  const form = useForm<CommentFormValues>({
    resolver: zodResolver(commentFormSchema),
    defaultValues: { body: initialBody },
    mode: 'onChange',
  });

  useEffect(() => {
    if (autoFocus) {
      form.setFocus('body');
    }
  }, [autoFocus, form]);

  const submit = form.handleSubmit(async (values) => {
    const error = await onSubmit(values);
    if (error) {
      form.setError('body', { message: error });
    } else {
      form.reset({ body: '' });
    }
  });

  const error = form.formState.errors.body?.message;

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-2" aria-label={label}>
      <label htmlFor={`${id}-body`} className="sr-only">
        {label}
      </label>
      <Controller
        name="body"
        control={form.control}
        render={({ field }) => (
          <MarkdownEditor
            id={`${id}-body`}
            value={field.value}
            onChange={field.onChange}
            onBlur={field.onBlur}
            ref={field.ref}
            renderPreview={renderPreview}
            rows={COMMENT_EDITOR_ROWS}
            maxLength={MAX_LENGTH_COMMENT_BODY}
            invalid={!!error}
            aria-describedby={error ? `${id}-error` : undefined}
          />
        )}
      />
      <InputError id={`${id}-error`} message={error} />
      <div className="flex justify-end gap-2">
        {onCancel && (
          <Button size="sm" variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button size="sm" type="submit" disabled={!form.formState.isValid} loading={form.formState.isSubmitting}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
