import type { KeyboardEvent, ReactNode, Ref } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { FaBold, FaCode, FaImage, FaItalic, FaLink, FaListOl, FaListUl, FaQuoteRight } from 'react-icons/fa6';
import { cn } from './cn';
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

interface PendingImage {
  file: File;
  previewUrl: string;
  alt: string;
  error: string | null;
  uploading: boolean;
  // Where to insert the image in the text
  position: number;
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
  const [pendingImage, setPendingImage] = useState<PendingImage | null>(null);
  const [imageError, setImageError] = useState<string | null>(null);
  const [mention, setMention] = useState<{ start: number; query: string } | null>(null);
  const [suggestions, setSuggestions] = useState<{ query: string; items: MentionSuggestion[] }>({ query: '', items: [] });
  const [activeSuggestion, setActiveSuggestion] = useState(0);
  const listboxId = `${baseId}-mentions`;
  const visibleSuggestions = mention !== null && suggestions.query === mention.query ? suggestions.items : [];
  const suggestionsOpen = visibleSuggestions.length > 0;
  const previewTabRef = useRef<HTMLButtonElement>(null);

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

  // Move the focus to the description field when an image is chosen (the file picker had it)
  const pendingPreviewUrl = pendingImage?.previewUrl;
  useEffect(() => {
    if (!pendingPreviewUrl) {
      return;
    }
    // After the browser gives the focus back to the file input (when the picker closes)
    const timer = window.setTimeout(() => document.getElementById(`${baseId}-image-alt`)?.focus(), 50);
    return () => window.clearTimeout(timer);
  }, [pendingPreviewUrl, baseId]);

  const startImage = (file: File) => {
    if (!images) {
      return;
    }
    if (!images.acceptedTypes.includes(file.type)) {
      setImageError('Choose a JPEG, PNG, WebP or GIF image.');
      return;
    }
    if (file.size > images.maxSizeBytes) {
      setImageError(`The image is too large (maximum ${Math.round(images.maxSizeBytes / 1024 / 1024)} MB).`);
      return;
    }
    setImageError(null);
    if (pendingImage) {
      URL.revokeObjectURL(pendingImage.previewUrl);
    }
    setPendingImage({
      file,
      previewUrl: URL.createObjectURL(file),
      alt: '',
      error: null,
      uploading: false,
      position: textareaRef.current?.selectionStart ?? value.length,
    });
  };

  const cancelImage = () => {
    if (pendingImage) {
      URL.revokeObjectURL(pendingImage.previewUrl);
    }
    setPendingImage(null);
    textareaRef.current?.focus();
  };

  const insertImage = async () => {
    if (!images || !pendingImage) {
      return;
    }
    const alt = pendingImage.alt.trim();
    if (alt === '') {
      setPendingImage({ ...pendingImage, error: 'Describe the image for people who cannot see it.' });
      return;
    }
    setPendingImage({ ...pendingImage, uploading: true, error: null });
    const result = await images.upload(pendingImage.file, alt);
    if ('error' in result) {
      setPendingImage({ ...pendingImage, uploading: false, error: result.error });
      return;
    }
    // Brackets in the alt text would end the Markdown image syntax
    const markdown = `![${alt.replace(/[[\]]/g, '')}](${result.url})`;
    const before = value.slice(0, pendingImage.position);
    const after = value.slice(pendingImage.position);
    const prefix = before === '' || before.endsWith('\n') ? '' : '\n';
    const suffix = after.startsWith('\n') ? '' : '\n';
    const next = `${before}${prefix}${markdown}${suffix}${after}`;
    const caret = before.length + prefix.length + markdown.length + suffix.length;
    URL.revokeObjectURL(pendingImage.previewUrl);
    setPendingImage(null);
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
                  aria-label="Add an image"
                  title="Add an image (or paste it, or drop it on the text)"
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
                  className="sr-only"
                  tabIndex={-1}
                  aria-hidden="true"
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) {
                      startImage(file);
                    }
                    event.target.value = '';
                  }}
                />
              </>
            )}
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
          onPaste={(event) => {
            const file = Array.from(event.clipboardData.files).find((item) => item.type.startsWith('image/'));
            if (images && file) {
              event.preventDefault();
              startImage(file);
            }
          }}
          onDragOver={(event) => {
            if (images && event.dataTransfer.types.includes('Files')) {
              event.preventDefault();
            }
          }}
          onDrop={(event) => {
            const file = Array.from(event.dataTransfer.files).find((item) => item.type.startsWith('image/'));
            if (images && file) {
              event.preventDefault();
              startImage(file);
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
        {pendingImage && (
          <div
            role="group"
            aria-label="Add an image"
            className="mx-2 mb-2 flex flex-col gap-2 rounded-lg border border-border bg-surface p-3 sm:flex-row"
          >
            {/* Decorative preview: the description below is the alternative */}
            <img src={pendingImage.previewUrl} alt="" className="max-h-32 w-auto self-start rounded-md object-contain" />
            <div className="flex grow flex-col gap-2">
              <label htmlFor={`${baseId}-image-alt`} className="text-sm font-bold">
                Image description (alt text)
              </label>
              <TextInput
                id={`${baseId}-image-alt`}
                value={pendingImage.alt}
                maxLength={500}
                placeholder="What does the image show?"
                aria-describedby={`${baseId}-image-alt-hint`}
                invalid={pendingImage.error !== null}
                onChange={(event) => setPendingImage({ ...pendingImage, alt: event.target.value, error: null })}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    event.preventDefault();
                    void insertImage();
                  }
                  if (event.key === 'Escape') {
                    // Cancel the image, not the dialog around the editor
                    event.preventDefault();
                    event.stopPropagation();
                    event.nativeEvent.stopImmediatePropagation();
                    cancelImage();
                  }
                }}
              />
              <p id={`${baseId}-image-alt-hint`} className="text-xs text-muted">
                Screen readers read this text. Describe what matters in the image.
              </p>
              {pendingImage.error && (
                <p role="alert" className="text-sm text-danger-text">
                  {pendingImage.error}
                </p>
              )}
              <div className="flex justify-end gap-2">
                <Button size="sm" variant="secondary" onClick={cancelImage}>
                  Cancel
                </Button>
                <Button size="sm" onClick={() => void insertImage()} loading={pendingImage.uploading}>
                  Insert the image
                </Button>
              </div>
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
          Markdown: **bold**, _italic_, [link](https://…), `code`, - list, &gt; quote{mentions ? ', @name to mention a member' : ''}.
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
