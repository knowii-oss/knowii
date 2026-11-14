import { useEffect, useRef } from 'react';
import { FaArrowDown, FaArrowUp, FaCircleCheck, FaXmark } from 'react-icons/fa6';
import { MAX_LENGTH_UPLOAD_ALT } from '@knowii/common';
import { Button } from './Button';
import { Spinner } from './Spinner';
import { TextInput } from './TextInput';

/**
 * An image that waits in the editor for its description, then for its upload (#1207)
 */
export interface PendingImageItem {
  id: string;
  file: File;
  previewUrl: string;
  alt: string;
  error: string | null;
  status: 'waiting' | 'uploading' | 'uploaded' | 'failed';
  // The address of the stored image, once it is uploaded: a second try does not upload it again
  url: string | null;
}

export interface ImageInsertPanelProps {
  baseId: string;
  items: PendingImageItem[];
  /**
   * The uploads run: nothing changes in the list meanwhile
   */
  busy: boolean;
  /**
   * A problem with the whole insert (too many images for the text, files left out)
   */
  message: string | null;
  onChange: (items: PendingImageItem[]) => void;
  onInsert: () => void;
  onCancel: () => void;
}

/**
 * The id of the description field of an image: the editor gives it the focus
 */
export const imageAltId = (baseId: string, itemId: string) => `${baseId}-image-alt-${itemId}`;

/**
 * The images that a member adds to a text, before they go in (#1207): a preview, a required description (the alt
 * text, which is also the caption in the gallery), the state of the upload, and buttons to reorder or remove.
 * One image keeps the short form; several get a numbered list.
 */
export function ImageInsertPanel({ baseId, items, busy, message, onChange, onInsert, onCancel }: ImageInsertPanelProps) {
  const several = items.length > 1;
  const list = useRef<HTMLOListElement>(null);
  // The button to focus again after an image moved: React moves its DOM node, and the browser drops the focus
  const movedButton = useRef<string | null>(null);

  useEffect(() => {
    if (movedButton.current !== null) {
      list.current?.querySelector<HTMLButtonElement>(`[data-move="${movedButton.current}"]`)?.focus();
      movedButton.current = null;
    }
  }, [items]);

  const update = (id: string, changes: Partial<PendingImageItem>) =>
    onChange(items.map((item) => (item.id === id ? { ...item, ...changes } : item)));

  const move = (index: number, by: -1 | 1) => {
    const target = index + by;
    const item = items[index];
    const other = items[target];
    if (busy || !item || !other) {
      return;
    }
    const next = [...items];
    next[index] = other;
    next[target] = item;
    movedButton.current = `${item.id}:${by}`;
    onChange(next);
  };

  const remove = (index: number) => {
    if (busy) {
      return;
    }
    const removed = items[index];
    if (removed) {
      URL.revokeObjectURL(removed.previewUrl);
    }
    const next = items.filter((_, position) => position !== index);
    if (next.length === 0) {
      onCancel();
      return;
    }
    onChange(next);
    // The description of the image that took its place, or of the last one
    const neighbour = next[Math.min(index, next.length - 1)];
    if (neighbour) {
      window.setTimeout(() => document.getElementById(imageAltId(baseId, neighbour.id))?.focus(), 0);
    }
  };

  return (
    <div
      role="group"
      aria-label={several ? `Add ${items.length} images` : 'Add an image'}
      className="mx-2 mb-2 flex flex-col gap-3 rounded-lg border border-border bg-surface p-3"
      // Escape anywhere in the panel (a description, a move button, "Insert"): cancel the images, not the dialog
      // around the editor, which would close with the text
      onKeyDown={(event) => {
        if (event.key === 'Escape') {
          event.preventDefault();
          event.stopPropagation();
          event.nativeEvent.stopImmediatePropagation();
          if (!busy) {
            onCancel();
          }
        }
      }}
    >
      {/* Ten images are a long list: from the sm width it scrolls, so the buttons under it stay near (on a phone, the
          page or the chat composer scrolls). The padding keeps the focus rings visible */}
      <ol ref={list} className="-m-1 flex flex-col gap-3 p-1 sm:max-h-[40vh] sm:overflow-y-auto">
        {items.map((item, index) => {
          const altId = imageAltId(baseId, item.id);
          const number = index + 1;
          return (
            <li key={item.id} className="flex gap-2">
              {/* Decorative preview: the description next to it is the alternative. A small square on a phone, where
                  ten images make a long list */}
              <img
                src={item.previewUrl}
                alt=""
                className="size-12 shrink-0 self-start rounded-md object-cover sm:size-auto sm:max-h-24 sm:max-w-32 sm:object-contain"
              />
              <div className="flex min-w-0 grow flex-col gap-1">
                <label htmlFor={altId} className="text-sm font-bold">
                  {several ? `Description of image ${number} of ${items.length} (alt text)` : 'Image description (alt text)'}
                </label>
                {/* On a phone the description takes the whole line, and the buttons go under it */}
                <div className="flex flex-wrap items-start justify-end gap-1">
                  <TextInput
                    id={altId}
                    wrapperClassName="grow basis-full sm:basis-0"
                    value={item.alt}
                    maxLength={MAX_LENGTH_UPLOAD_ALT}
                    placeholder="What does the image show?"
                    aria-describedby={`${baseId}-image-alt-hint`}
                    invalid={item.error !== null}
                    readOnly={busy || item.status === 'uploaded'}
                    onChange={(event) => update(item.id, { alt: event.target.value, error: null })}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter') {
                        event.preventDefault();
                        onInsert();
                      }
                    }}
                  />
                  {several && (
                    <>
                      <Button
                        variant="ghost"
                        iconOnly
                        data-move={`${item.id}:-1`}
                        aria-label={`Move image ${number} up`}
                        // aria-disabled, not disabled: a disabled button loses the focus at the ends of the list
                        aria-disabled={index === 0 || busy || undefined}
                        onClick={() => move(index, -1)}
                      >
                        <FaArrowUp aria-hidden="true" />
                      </Button>
                      <Button
                        variant="ghost"
                        iconOnly
                        data-move={`${item.id}:1`}
                        aria-label={`Move image ${number} down`}
                        aria-disabled={index === items.length - 1 || busy || undefined}
                        onClick={() => move(index, 1)}
                      >
                        <FaArrowDown aria-hidden="true" />
                      </Button>
                      <Button
                        variant="ghost"
                        iconOnly
                        aria-label={`Remove image ${number}`}
                        aria-disabled={busy || undefined}
                        onClick={() => remove(index)}
                      >
                        <FaXmark aria-hidden="true" />
                      </Button>
                    </>
                  )}
                </div>
                <p className="truncate text-xs text-muted">{item.file.name}</p>
                {item.status === 'uploading' && (
                  <p className="flex items-center gap-2 text-sm text-muted">
                    {/* The spinner is the status for screen readers */}
                    <Spinner size="sm" label={`Uploading image ${number}`} />
                    <span aria-hidden="true">Uploading…</span>
                  </p>
                )}
                {item.status === 'uploaded' && (
                  <p role="status" className="flex items-center gap-2 text-sm text-muted">
                    <FaCircleCheck aria-hidden="true" className="text-success" />
                    Uploaded
                  </p>
                )}
                {item.error && (
                  <p role="alert" className="text-sm text-danger-text">
                    {item.error}
                  </p>
                )}
              </div>
            </li>
          );
        })}
      </ol>
      <p id={`${baseId}-image-alt-hint`} className="text-xs text-muted">
        Screen readers read this text{several ? ', and it is the caption of the image in the gallery' : ''}. Describe what matters in the
        image.
      </p>
      {message && (
        <p role="alert" className="text-sm text-danger-text">
          {message}
        </p>
      )}
      <div className="flex justify-end gap-2">
        <Button size="sm" variant="secondary" aria-disabled={busy || undefined} onClick={() => !busy && onCancel()}>
          Cancel
        </Button>
        {/* aria-busy, not loading: a disabled button loses the focus, and a failed upload keeps the panel open */}
        <Button size="sm" aria-busy={busy || undefined} onClick={() => !busy && onInsert()}>
          {several ? `Insert the ${items.length} images` : 'Insert the image'}
        </Button>
      </div>
    </div>
  );
}
