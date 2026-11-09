import type { KeyboardEvent, ReactNode, Ref } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { FaBold, FaCode, FaItalic, FaLink, FaListOl, FaListUl, FaQuoteRight } from 'react-icons/fa6';
import { cn } from './cn';
import { MarkdownContent } from './MarkdownContent';
import type { MarkdownFormat, TextSelection } from './markdown-format';
import { applyMarkdownFormat, MARKDOWN_SHORTCUTS } from './markdown-format';
import { mergeRefs } from './merge-refs';
import { Spinner } from './Spinner';
import { formControlClasses } from './form-field';

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
  ref?: Ref<HTMLTextAreaElement>;
}

type Tab = 'write' | 'preview';

export interface MentionSuggestion {
  username: string;
  name: string;
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
  ref,
}: MarkdownEditorProps) {
  const baseId = useId();
  const [tab, setTab] = useState<Tab>('write');
  // The last rendered preview, and the Markdown it was rendered from: it is loading while they differ
  const [preview, setPreview] = useState<{ markdown: string; html: string | null } | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const pendingSelection = useRef<TextSelection | null>(null);
  const writeTabRef = useRef<HTMLButtonElement>(null);
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
