import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { chatMessageFormSchema, knowiiApiClient, MAX_LENGTH_CHAT_MESSAGE, useAppData } from '@knowii/common';
import { FaPaperPlane } from 'react-icons/fa6';
import { useMentionSource } from '@/Components/Members/use-mention-source';
import { useImageUploader } from '@/Components/Uploads/use-image-uploader';
import { Button, MarkdownEditor } from '@/Components/ui';

export interface ChatComposerProps {
  id: string;
  communityCuid: string;
  label: string;
  /**
   * Send the text; resolve true when the message is saved (the field then empties)
   */
  onSend: (body: string) => Promise<boolean>;
  /**
   * Up arrow in an empty field: edit the last message of the member (#1185)
   */
  onEditLast?: (() => void) | undefined;
  /**
   * Initial text (an edit)
   */
  initialValue?: string;
  submitLabel?: string;
  onCancel?: (() => void) | undefined;
  /**
   * Take the focus when shown, with the caret at the end (an edit opened with the Up arrow)
   */
  autoFocus?: boolean;
}

/**
 * The message field of a chat (#1185): Markdown with mentions and images. Enter sends, Shift+Enter adds a line, Up
 * arrow in an empty field edits the last own message, Escape cancels an edit.
 */
export function ChatComposer({
  id,
  communityCuid,
  label,
  onSend,
  onEditLast,
  initialValue = '',
  submitLabel = 'Send',
  onCancel,
  autoFocus = false,
}: ChatComposerProps) {
  const { showToast } = useAppData();
  const [value, setValue] = useState(initialValue);
  const [sending, setSending] = useState(false);
  const textarea = useRef<HTMLTextAreaElement>(null);
  const mentions = useMentionSource(communityCuid);
  const images = useImageUploader(communityCuid);
  const renderPreview = useMemo(
    () => (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal, communityCuid),
    [communityCuid],
  );

  useEffect(() => {
    const field = textarea.current;
    if (autoFocus && field) {
      field.focus();
      field.setSelectionRange(field.value.length, field.value.length);
    }
  }, [autoFocus]);

  const send = async () => {
    // The field stays usable while a message is sent (the focus stays in it); a second Enter waits
    if (sending) {
      return;
    }
    const parsed = chatMessageFormSchema.safeParse({ body: value });
    if (!parsed.success) {
      if (value.trim() !== '') {
        showToast({ severity: 'error', summary: parsed.error.issues[0]?.message ?? 'The message is not valid' });
      }
      return;
    }
    setSending(true);
    const saved = await onSend(value);
    setSending(false);
    if (saved) {
      setValue('');
      textarea.current?.focus();
    }
  };

  const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.altKey && !event.metaKey && !event.nativeEvent.isComposing) {
      event.preventDefault();
      void send();
      return;
    }
    if (event.key === 'ArrowUp' && value === '' && onEditLast) {
      event.preventDefault();
      onEditLast();
      return;
    }
    if (event.key === 'Escape' && onCancel) {
      event.preventDefault();
      onCancel();
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={id} className="sr-only">
        {label}
      </label>
      <MarkdownEditor
        id={id}
        ref={textarea}
        value={value}
        onChange={setValue}
        onKeyDown={onKeyDown}
        autoFocus={autoFocus}
        renderPreview={renderPreview}
        mentions={mentions}
        images={images}
        rows={2}
        maxLength={MAX_LENGTH_CHAT_MESSAGE}
        placeholder={label}
        aria-describedby={`${id}-hint`}
      />
      <div className="flex items-center justify-between gap-2">
        <p id={`${id}-hint`} className="text-xs text-muted">
          Enter sends, Shift+Enter adds a line{onEditLast ? ', Up arrow edits your last message' : ''}
          {onCancel ? ', Escape cancels' : ''}.
        </p>
        <span className="flex gap-2">
          {onCancel && (
            <Button variant="ghost" size="sm" onClick={onCancel}>
              Cancel
            </Button>
          )}
          <Button size="sm" onClick={() => void send()} loading={sending}>
            <FaPaperPlane aria-hidden="true" />
            {submitLabel}
          </Button>
        </span>
      </div>
    </div>
  );
}
