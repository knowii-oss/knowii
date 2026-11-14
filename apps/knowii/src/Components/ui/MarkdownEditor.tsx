import type { KeyboardEvent, ReactNode, Ref } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { FaBold, FaCode, FaImage, FaItalic, FaLink, FaListOl, FaListUl, FaQuoteRight, FaVideo } from 'react-icons/fa6';
import { countMarkdownImages, markdownImage, MAX_IMAGES_PER_CONTENT, MAX_IMAGES_PER_INSERT, parseVideoUrl } from '@knowii/common';
import { cn } from './cn';
import type { PendingImageItem } from './ImageInsertPanel';
import { imageAltId, ImageInsertPanel } from './ImageInsertPanel';
import { MarkdownContent } from './MarkdownContent';
import type { MarkdownFormat, TextSelection } from './markdown-format';
import { applyMarkdownFormat, MARKDOWN_SHORTCUTS } from './markdown-format';
import { mergeRefs } from './merge-refs';
import { Spinner } from './Spinner';
import { formControlClasses } from './form-field';
import { Button } from './Button';
import { TextInput } from './TextInput';

export interface MarkdownEditorProps {
  id: string;
  value: string;
  onChange: (value: string) => void;
  onBlur?: () => void;
  /**
   * Renders the Markdown for the preview tab (the server renderer, so the preview equals the published content).
   * Returns null when the preview fails.
   */
  renderPreview: (markdown: string, signal: AbortSignal) => Promise<string | null>;
  rows?: number;
  maxLength?: number;
  placeholder?: string;
  disabled?: boolean;
  invalid?: boolean;
  'aria-describedby'?: string | undefined;
  /**
   * Extra buttons at the end of the toolbar (for example an image upload)
   */
  toolbarExtra?: ReactNode;
  /**
   * Suggests members after "@" (combobox pattern). Absent: no suggestions.
   */
  mentions?: MentionSource | undefined;
  /**
   * Uploads images (toolbar button, paste, drag and drop). Absent: no image upload.
   */
  images?: ImageUploader | undefined;
  /**
   * Keys of the text field, after the mention suggestions (they keep Enter, arrows and Escape while open). Call
   * preventDefault() to stop the default handling (for example Enter sends a chat message, #1185).
   */
  onKeyDown?: ((event: KeyboardEvent<HTMLTextAreaElement>) => void) | undefined;
  /**
   * The first field of a dialog or a drawer: Headless UI gives it the focus when it opens (data-autofocus)
   */
  autoFocus?: boolean;
  ref?: Ref<HTMLTextAreaElement>;
}

type Tab = 'write' | 'preview';

export interface MentionSuggestion {
  username: string;
  name: string;
}

export interface ImageUploader {
  /**
   * Returns the URL of the stored image, or an error message
   */
  upload: (file: File, alt: string) => Promise<{ url: string } | { error: string }>;
  maxSizeBytes: number;
  acceptedTypes: readonly string[];
}

/**
 * The images that wait for their descriptions and their uploads (#1207): up to MAX_IMAGES_PER_INSERT at a time
 */
interface PendingImages {
  items: PendingImageItem[];
  // Where to insert the images in the text
  position: number;
  // The uploads run
  busy: boolean;
  // A problem with the whole insert
  message: string | null;
}

export interface MentionSource {
  search: (query: string, signal: AbortSignal) => Promise<MentionSuggestion[]>;
}

// "@" at the start or after a space or an opening parenthesis, then the beginning of a username
const MENTION_TRIGGER = /(?:^|[\s(])@([a-zA-Z0-9-]{0,36})$/;
const MENTION_SEARCH_DELAY = 150;

/**
 * The mention being typed before the caret, or null.
 */
export function findMentionQuery(value: string, caret: number): { start: number; query: string } | null {
  const match = MENTION_TRIGGER.exec(value.slice(0, caret));
  if (!match) {
    return null;
  }
  const query = match[1] ?? '';
  return { start: caret - query.length - 1, query };
}

const TOOLBAR: { format: MarkdownFormat; label: string; icon: ReactNode; shortcut?: string }[] = [
  { format: 'bold', label: 'Bold', icon: <FaBold />, shortcut: 'Ctrl+B' },
  { format: 'italic', label: 'Italic', icon: <FaItalic />, shortcut: 'Ctrl+I' },
  { format: 'link', label: 'Link', icon: <FaLink />, shortcut: 'Ctrl+K' },
  { format: 'code', label: 'Code', icon: <FaCode />, shortcut: 'Ctrl+E' },
  { format: 'quote', label: 'Quote', icon: <FaQuoteRight /> },
  { format: 'bulletList', label: 'Bulleted list', icon: <FaListUl /> },
  { format: 'numberedList', label: 'Numbered list', icon: <FaListOl /> },
];

/**
 * Markdown editor with a Write tab (textarea, formatting toolbar, keyboard shortcuts) and a Preview tab.
 * Tabs follow the WAI-ARIA tabs pattern: arrow keys move between them.
 */
export function MarkdownEditor({
  id,
  value,
  onChange,
  onBlur,
  renderPreview,
  rows = 8,
  maxLength,
  placeholder,
  disabled = false,
  invalid = false,
  'aria-describedby': ariaDescribedBy,
  toolbarExtra,
  mentions,
  images,
  onKeyDown,
  autoFocus = false,
  ref,
}: MarkdownEditorProps) {
  const baseId = useId();
  const [tab, setTab] = useState<Tab>('write');
  // The last rendered preview, and the Markdown it was rendered from: it is loading while they differ
  const [preview, setPreview] = useState<{ markdown: string; html: string | null } | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const pendingSelection = useRef<TextSelection | null>(null);
  const writeTabRef = useRef<HTMLButtonElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [pendingImages, setPendingImages] = useState<PendingImages | null>(null);
  const [imageError, setImageError] = useState<string | null>(null);
  // The description field that gets the focus after the member chose or checked images
  const [imageFocus, setImageFocus] = useState<{ id: string } | null>(null);
  // A video address being added (#1206): it goes alone on its line, where the server turns it into a player
  const [pendingVideo, setPendingVideo] = useState<{ url: string; error: string | null; position: number } | null>(null);
  const [mention, setMention] = useState<{ start: number; query: string } | null>(null);
  const [suggestions, setSuggestions] = useState<{ query: string; items: MentionSuggestion[] }>({ query: '', items: [] });
  const [activeSuggestion, setActiveSuggestion] = useState(0);
  const listboxId = `${baseId}-mentions`;
  const visibleSuggestions = mention !== null && suggestions.query === mention.query ? suggestions.items : [];
  const suggestionsOpen = visibleSuggestions.length > 0;
  const previewTabRef = useRef<HTMLButtonElement>(null);
  // The text as it is now: the uploads take time, and the member can write (or send a chat message) meanwhile
  const latestValue = useRef(value);
  // The previews to release when the editor goes away with images that still wait
  const latestPendingImages = useRef<PendingImages | null>(null);

  useEffect(() => {
    latestValue.current = value;
  }, [value]);

  useEffect(() => {
    latestPendingImages.current = pendingImages;
  }, [pendingImages]);

  useEffect(() => () => latestPendingImages.current?.items.forEach((item) => URL.revokeObjectURL(item.previewUrl)), []);

  const writeTabId = `${baseId}-write-tab`;
  const previewTabId = `${baseId}-preview-tab`;
  const writePanelId = `${baseId}-write-panel`;
  const previewPanelId = `${baseId}-preview-panel`;

  // Restore the selection after a formatting command changed the value
  useEffect(() => {
    const selection = pendingSelection.current;
    const textarea = textareaRef.current;
    if (selection && textarea && textarea.value === selection.value) {
      textarea.focus();
      textarea.setSelectionRange(selection.selectionStart, selection.selectionEnd);
      pendingSelection.current = null;
    }
  }, [value]);

  useEffect(() => {
    if (!mentions || mention === null || mention.query === '') {
      return;
    }
    const controller = new AbortController();
    const query = mention.query;
    const timer = window.setTimeout(() => {
      mentions.search(query, controller.signal).then((items) => {
        if (!controller.signal.aborted) {
          setSuggestions({ query, items });
          setActiveSuggestion(0);
        }
      });
    }, MENTION_SEARCH_DELAY);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [mention, mentions]);

  useEffect(() => {
    if (tab !== 'preview') {
      return;
    }
    const controller = new AbortController();
    renderPreview(value, controller.signal).then((html) => {
      if (!controller.signal.aborted) {
        setPreview({ markdown: value, html });
      }
    });
    return () => controller.abort();
  }, [tab, value, renderPreview]);

  const format = (command: MarkdownFormat) => {
    const textarea = textareaRef.current;
    if (!textarea || disabled) {
      return;
    }
    const result = applyMarkdownFormat({ value, selectionStart: textarea.selectionStart, selectionEnd: textarea.selectionEnd }, command);
    pendingSelection.current = result;
    onChange(result.value);
  };

  // Move the focus to a description field: when images are chosen (the file picker had the focus), and when one
  // has no description at the insert
  useEffect(() => {
    if (!imageFocus) {
      return;
    }
    // After the browser gives the focus back to the file input (when the picker closes)
    const timer = window.setTimeout(() => document.getElementById(imageAltId(baseId, imageFocus.id))?.focus(), 50);
    return () => window.clearTimeout(timer);
  }, [imageFocus, baseId]);

  /**
   * Add images to the ones that wait (toolbar button, paste, drop): several at a time (#1207). Files of another
   * type, files that are too large and files over the limit per insert are left out, with a message.
   */
  const addImages = (files: File[]) => {
    if (!images || files.length === 0 || pendingImages?.busy) {
      return;
    }
    const accepted = files.filter((file) => images.acceptedTypes.includes(file.type) && file.size <= images.maxSizeBytes);
    const waiting = pendingImages?.items ?? [];
    const added = accepted.slice(0, Math.max(0, MAX_IMAGES_PER_INSERT - waiting.length));

    const problems: string[] = [];
    if (files.some((file) => !images.acceptedTypes.includes(file.type))) {
      problems.push(files.length === 1 ? 'Choose a JPEG, PNG, WebP or GIF image.' : 'Choose JPEG, PNG, WebP or GIF images.');
    }
    if (files.some((file) => images.acceptedTypes.includes(file.type) && file.size > images.maxSizeBytes)) {
      const megabytes = Math.round(images.maxSizeBytes / 1024 / 1024);
      problems.push(
        files.length === 1 ? `The image is too large (maximum ${megabytes} MB).` : `An image is too large (maximum ${megabytes} MB).`,
      );
    }
    if (added.length < accepted.length) {
      problems.push(`You can add ${MAX_IMAGES_PER_INSERT} images at a time: the others were left out.`);
    }
    setImageError(problems.length > 0 ? problems.join(' ') : null);
    if (added.length === 0) {
      return;
    }

    const items: PendingImageItem[] = added.map((file) => ({
      id: crypto.randomUUID(),
      file,
      previewUrl: URL.createObjectURL(file),
      alt: '',
      error: null,
      status: 'waiting',
      url: null,
    }));
    setPendingImages({
      items: [...waiting, ...items],
      position: pendingImages?.position ?? textareaRef.current?.selectionStart ?? value.length,
      busy: false,
      message: null,
    });
    const first = items[0];
    if (first) {
      setImageFocus({ id: first.id });
    }
  };

  const cancelImages = () => {
    pendingImages?.items.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    setPendingImages(null);
    setImageError(null);
    textareaRef.current?.focus();
  };

  /**
   * Upload the images that wait, one after the other, then write them in the text, one per line: images that follow
   * each other form a gallery. Nothing goes in the text before every upload worked; a second try uploads only the
   * images that failed.
   */
  const insertImages = async () => {
    if (!images || !pendingImages || pendingImages.busy) {
      return;
    }
    const { position } = pendingImages;
    let items = pendingImages.items.map((item) =>
      item.alt.trim() === '' ? { ...item, error: 'Describe the image for people who cannot see it.' } : item,
    );
    const withoutAlt = items.find((item) => item.alt.trim() === '');
    if (withoutAlt) {
      setPendingImages({ ...pendingImages, items });
      setImageFocus({ id: withoutAlt.id });
      return;
    }
    const room = MAX_IMAGES_PER_CONTENT - countMarkdownImages(value);
    if (items.length > room) {
      setPendingImages({
        ...pendingImages,
        message:
          room <= 0
            ? `The text already has ${MAX_IMAGES_PER_CONTENT} images, the maximum.`
            : `A text holds ${MAX_IMAGES_PER_CONTENT} images at most: there is room for ${room} more. Remove ${items.length - room}.`,
      });
      return;
    }

    const show = (busy: boolean) => setPendingImages({ items, position, busy, message: null });
    for (const item of items) {
      if (item.status === 'uploaded') {
        continue;
      }
      items = items.map((other) => (other.id === item.id ? { ...other, status: 'uploading', error: null } : other));
      show(true);
      const result = await images.upload(item.file, item.alt.trim());
      items = items.map((other) =>
        other.id !== item.id
          ? other
          : 'error' in result
            ? { ...other, status: 'failed', error: result.error }
            : { ...other, status: 'uploaded', url: result.url },
      );
    }
    if (items.some((item) => item.url === null)) {
      show(false);
      return;
    }

    const markdown = items.map((item) => markdownImage(item.alt.trim(), item.url ?? '')).join('\n');
    // The text can differ from the one at the start of the uploads: nothing that the member wrote meanwhile is lost.
    // The images then go where the caret is now, not in the middle of the new words
    const current = latestValue.current;
    const at = current === value ? position : Math.min(textareaRef.current?.selectionStart ?? current.length, current.length);
    const before = current.slice(0, at);
    const after = current.slice(at);
    const prefix = before === '' || before.endsWith('\n') ? '' : '\n';
    const suffix = after.startsWith('\n') ? '' : '\n';
    const next = `${before}${prefix}${markdown}${suffix}${after}`;
    const caret = before.length + prefix.length + markdown.length + suffix.length;
    items.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    setPendingImages(null);
    setImageError(null);
    pendingSelection.current = { value: next, selectionStart: caret, selectionEnd: caret };
    onChange(next);
  };

  const cancelVideo = () => {
    setPendingVideo(null);
    textareaRef.current?.focus();
  };

  const insertVideo = () => {
    if (!pendingVideo) {
      return;
    }
    const url = pendingVideo.url.trim();
    if (parseVideoUrl(url) === null) {
      setPendingVideo({ ...pendingVideo, error: 'Enter the address of a YouTube or Vimeo video.' });
      return;
    }
    // A blank line before and after: a link alone in its paragraph becomes the video
    const before = value.slice(0, pendingVideo.position);
    const after = value.slice(pendingVideo.position);
    const prefix = before === '' || before.endsWith('\n\n') ? '' : before.endsWith('\n') ? '\n' : '\n\n';
    const suffix = after === '' ? '\n' : after.startsWith('\n\n') ? '' : after.startsWith('\n') ? '\n' : '\n\n';
    const next = `${before}${prefix}${url}${suffix}${after}`;
    const caret = before.length + prefix.length + url.length + suffix.length;
    setPendingVideo(null);
    pendingSelection.current = { value: next, selectionStart: caret, selectionEnd: caret };
    onChange(next);
  };

  const updateMention = (text: string, caret: number) => {
    if (!mentions) {
      return;
    }
    const found = findMentionQuery(text, caret);
    setMention((current) => (current?.start === found?.start && current?.query === found?.query ? current : found));
  };

  const insertMention = (suggestion: MentionSuggestion) => {
    const textarea = textareaRef.current;
    if (!textarea || mention === null) {
      return;
    }
    const caret = textarea.selectionStart;
    const inserted = `@${suggestion.username} `;
    const next = `${value.slice(0, mention.start)}${inserted}${value.slice(caret)}`;
    const position = mention.start + inserted.length;
    pendingSelection.current = { value: next, selectionStart: position, selectionEnd: position };
    setMention(null);
    onChange(next);
  };

  const onTextareaKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (suggestionsOpen) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        setActiveSuggestion((current) => (current + step + visibleSuggestions.length) % visibleSuggestions.length);
        return;
      }
      if (event.key === 'Enter' || event.key === 'Tab') {
        const suggestion = visibleSuggestions[activeSuggestion];
        if (suggestion) {
          event.preventDefault();
          insertMention(suggestion);
        }
        return;
      }
      if (event.key === 'Escape') {
        // Close the suggestions, not the dialog around the editor
        event.preventDefault();
        event.stopPropagation();
        event.nativeEvent.stopImmediatePropagation();
        setMention(null);
        return;
      }
    }

    onKeyDown?.(event);
    if (event.defaultPrevented) {
      return;
    }

    if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) {
      return;
    }
    const command = MARKDOWN_SHORTCUTS[event.key.toLowerCase()];
    if (command) {
      event.preventDefault();
      format(command);
    }
  };

  const selectTab = (next: Tab) => {
    setTab(next);
    (next === 'write' ? writeTabRef : previewTabRef).current?.focus();
  };

  const onTabKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
      event.preventDefault();
      if (event.key === 'Home') {
        selectTab('write');
      } else if (event.key === 'End') {
        selectTab('preview');
      } else {
        selectTab(tab === 'write' ? 'preview' : 'write');
      }
    }
  };

  const tabClasses = (active: boolean) =>
    cn(
      'cursor-pointer rounded-t-md border-b-2 px-3 py-1.5 text-sm font-bold transition-colors',
      'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
      active ? 'border-secondary text-primary' : 'border-transparent text-muted hover:text-primary',
    );

  return (
    <div className={cn('rounded-lg border bg-well', invalid ? 'border-danger' : 'border-border')}>
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-2 pt-1">
        <div role="tablist" aria-label="Editor mode" className="flex gap-1">
          <button
            ref={writeTabRef}
            id={writeTabId}
            type="button"
            role="tab"
            aria-selected={tab === 'write'}
            aria-controls={writePanelId}
            tabIndex={tab === 'write' ? 0 : -1}
            onClick={() => setTab('write')}
            onKeyDown={onTabKeyDown}
            className={tabClasses(tab === 'write')}
          >
            Write
          </button>
          <button
            ref={previewTabRef}
            id={previewTabId}
            type="button"
            role="tab"
            aria-selected={tab === 'preview'}
            aria-controls={previewPanelId}
            tabIndex={tab === 'preview' ? 0 : -1}
            onClick={() => setTab('preview')}
            onKeyDown={onTabKeyDown}
            className={tabClasses(tab === 'preview')}
          >
            Preview
          </button>
        </div>
        {tab === 'write' && (
          <div role="toolbar" aria-label="Formatting" aria-controls={id} className="flex flex-wrap gap-0.5 pb-1">
            {TOOLBAR.map((item) => (
              <button
                key={item.format}
                type="button"
                aria-label={item.shortcut ? `${item.label} (${item.shortcut})` : item.label}
                title={item.shortcut ? `${item.label} (${item.shortcut})` : item.label}
                disabled={disabled}
                onClick={() => format(item.format)}
                className="flex size-8 cursor-pointer items-center justify-center rounded-md text-muted transition-colors hover:bg-overlay/10 hover:text-primary focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-50"
              >
                <span aria-hidden="true">{item.icon}</span>
              </button>
            ))}
            {images && (
              <>
                <button
                  type="button"
                  aria-label="Add images"
                  title={`Add images (or paste them, or drop them on the text), up to ${MAX_IMAGES_PER_INSERT} at a time`}
                  disabled={disabled}
                  onClick={() => fileInputRef.current?.click()}
                  className="flex size-8 cursor-pointer items-center justify-center rounded-md text-muted transition-colors hover:bg-overlay/10 hover:text-primary focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <FaImage aria-hidden="true" />
                </button>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept={images.acceptedTypes.join(',')}
                  multiple
                  className="sr-only"
                  tabIndex={-1}
                  aria-hidden="true"
                  onChange={(event) => {
                    addImages(Array.from(event.target.files ?? []));
                    event.target.value = '';
                  }}
                />
              </>
            )}
            <button
              type="button"
              aria-label="Add a video"
              title="Add a YouTube or Vimeo video"
              disabled={disabled}
              onClick={() => setPendingVideo({ url: '', error: null, position: textareaRef.current?.selectionStart ?? value.length })}
              className="flex size-8 cursor-pointer items-center justify-center rounded-md text-muted transition-colors hover:bg-overlay/10 hover:text-primary focus-visible:outline-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-50"
            >
              <FaVideo aria-hidden="true" />
            </button>
            {toolbarExtra}
          </div>
        )}
      </div>

      <div id={writePanelId} role="tabpanel" aria-labelledby={writeTabId} hidden={tab !== 'write'}>
        <textarea
          ref={mergeRefs(textareaRef, ref)}
          id={id}
          value={value}
          onChange={(event) => {
            onChange(event.target.value);
            updateMention(event.target.value, event.target.selectionStart);
          }}
          onSelect={(event) => updateMention(event.currentTarget.value, event.currentTarget.selectionStart)}
          onBlur={() => {
            // Let a click on a suggestion land before closing the list
            window.setTimeout(() => setMention(null), 150);
            onBlur?.();
          }}
          onKeyDown={onTextareaKeyDown}
          {...(autoFocus ? { 'data-autofocus': true } : {})}
          onPaste={(event) => {
            const files = Array.from(event.clipboardData.files).filter((item) => item.type.startsWith('image/'));
            // Text from a word processor or a spreadsheet comes with a picture of itself: the text is what the
            // member pastes
            if (images && files.length > 0 && event.clipboardData.getData('text/plain') === '') {
              event.preventDefault();
              addImages(files);
            }
          }}
          onDragOver={(event) => {
            if (images && event.dataTransfer.types.includes('Files')) {
              event.preventDefault();
            }
          }}
          onDrop={(event) => {
            // Every dropped file stops here: the browser would open a file that is not an image in place of the
            // page, and the text would be lost. addImages says which files it refuses
            const files = Array.from(event.dataTransfer.files);
            if (images && files.length > 0) {
              event.preventDefault();
              addImages(files);
            }
          }}
          {...(mentions
            ? {
                role: 'combobox',
                'aria-autocomplete': 'list' as const,
                'aria-expanded': suggestionsOpen,
                'aria-controls': listboxId,
                ...(suggestionsOpen ? { 'aria-activedescendant': `${listboxId}-${activeSuggestion}` } : {}),
              }
            : {})}
          rows={rows}
          maxLength={maxLength}
          placeholder={placeholder}
          disabled={disabled}
          aria-invalid={invalid || undefined}
          aria-describedby={ariaDescribedBy}
          className={cn(formControlClasses, 'block w-full resize-y rounded-t-none border-0 bg-transparent px-3 py-2 font-mono text-sm')}
        />
        {imageError && (
          <p role="alert" className="px-3 pb-2 text-sm text-danger-text">
            {imageError}
          </p>
        )}
        {pendingImages && (
          <ImageInsertPanel
            baseId={baseId}
            items={pendingImages.items}
            busy={pendingImages.busy}
            message={pendingImages.message}
            onChange={(items) => setPendingImages({ ...pendingImages, items, message: null })}
            onInsert={() => void insertImages()}
            onCancel={cancelImages}
          />
        )}
        {pendingVideo && (
          <div
            role="group"
            aria-label="Add a video"
            className="mx-2 mb-2 flex flex-col gap-2 rounded-lg border border-border bg-surface p-3"
          >
            <label htmlFor={`${baseId}-video-url`} className="text-sm font-bold">
              Video address (YouTube or Vimeo)
            </label>
            <TextInput
              id={`${baseId}-video-url`}
              type="url"
              autoFocus
              value={pendingVideo.url}
              maxLength={500}
              placeholder="https://www.youtube.com/watch?v=..."
              aria-describedby={`${baseId}-video-url-hint`}
              invalid={pendingVideo.error !== null}
              onChange={(event) => setPendingVideo({ ...pendingVideo, url: event.target.value, error: null })}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault();
                  insertVideo();
                }
                if (event.key === 'Escape') {
                  // Cancel the video, not the dialog around the editor
                  event.preventDefault();
                  event.stopPropagation();
                  event.nativeEvent.stopImmediatePropagation();
                  cancelVideo();
                }
              }}
            />
            <p id={`${baseId}-video-url-hint`} className="text-xs text-muted">
              The video plays in the text. Nothing loads from YouTube or Vimeo before a reader presses Play.
            </p>
            {pendingVideo.error && (
              <p role="alert" className="text-sm text-danger-text">
                {pendingVideo.error}
              </p>
            )}
            <div className="flex justify-end gap-2">
              <Button size="sm" variant="secondary" onClick={cancelVideo}>
                Cancel
              </Button>
              <Button size="sm" onClick={insertVideo}>
                Insert the video
              </Button>
            </div>
          </div>
        )}
        {mentions && (
          <ul
            id={listboxId}
            role="listbox"
            aria-label="Members to mention"
            hidden={!suggestionsOpen}
            className="mx-2 mb-2 flex max-h-56 flex-col overflow-y-auto rounded-lg border border-border bg-surface-elevated p-1 shadow-xl"
          >
            {visibleSuggestions.map((suggestion, index) => (
              <li
                key={suggestion.username}
                id={`${listboxId}-${index}`}
                role="option"
                aria-selected={index === activeSuggestion}
                onMouseDown={(event) => {
                  // Keep the focus in the textarea
                  event.preventDefault();
                  insertMention(suggestion);
                }}
                className={cn(
                  'flex cursor-pointer items-baseline gap-2 rounded-md px-3 py-1.5 text-sm',
                  index === activeSuggestion ? 'bg-overlay/15 text-primary' : 'text-primary/90',
                )}
              >
                <span className="font-bold">@{suggestion.username}</span>{' '}
                <span className="truncate text-xs text-muted">{suggestion.name}</span>
              </li>
            ))}
          </ul>
        )}
        <p className="px-3 pb-2 text-xs text-muted">
          Markdown: **bold**, _italic_, [link](https://…), `code`, - list, &gt; quote{mentions ? ', @name to mention a member' : ''}. A
          YouTube or Vimeo link alone on its line becomes a video.
        </p>
      </div>

      <div id={previewPanelId} role="tabpanel" aria-labelledby={previewTabId} hidden={tab !== 'preview'} className="min-h-32 px-3 py-2">
        {tab === 'preview' &&
          (preview?.markdown !== value ? (
            <Spinner label="Loading the preview" />
          ) : preview.html === null ? (
            <p className="text-sm text-danger-text">The preview is not available. Try again in a moment.</p>
          ) : value.trim() === '' ? (
            <p className="text-sm text-muted">Nothing to preview yet.</p>
          ) : (
            <MarkdownContent html={preview.html} />
          ))}
      </div>
    </div>
  );
}
