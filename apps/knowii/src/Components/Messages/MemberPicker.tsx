import { useEffect, useState } from 'react';
import { Combobox, ComboboxInput, ComboboxOption, ComboboxOptions, Label } from '@headlessui/react';
import type { Author } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { FaXmark } from 'react-icons/fa6';
import { Avatar, cn } from '@/Components/ui';

export interface MemberPickerProps {
  communityCuid: string;
  value: Author[];
  onChange: (members: Author[]) => void;
  // Never offered (the current member)
  excludeCuids: string[];
  max: number;
  label: string;
  id: string;
  autoFocus?: boolean;
}

const SUGGESTIONS = 8;

/**
 * Choose members of the community by name or username (#1186): a combobox. Type to search, Up and Down to move,
 * Enter to add, Backspace in the empty field removes the last member. The chosen members show as chips with a remove
 * button each.
 */
export function MemberPicker({ communityCuid, value, onChange, excludeCuids, max, label, id, autoFocus = false }: MemberPickerProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Author[]>([]);

  useEffect(() => {
    const trimmed = query.trim();
    if (trimmed === '') {
      return;
    }
    const controller = new AbortController();
    const timer = setTimeout(() => {
      void knowiiApiClient.communities.members
        .search(communityCuid, trimmed, SUGGESTIONS + excludeCuids.length, controller.signal)
        .then(setResults);
    }, 150);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [query, communityCuid, excludeCuids.length]);

  const full = value.length >= max;
  // An empty field shows no suggestions, whatever the last search found
  const options = (query.trim() === '' ? [] : results).filter(
    (member) => !excludeCuids.includes(member.cuid) && !value.some((chosen) => chosen.cuid === member.cuid),
  );

  return (
    <Combobox
      multiple
      value={value}
      by="cuid"
      onChange={(members: Author[]) => {
        onChange(members.slice(0, max));
        setQuery('');
      }}
    >
      <Label htmlFor={id} className="block text-sm font-bold text-primary">
        {label}
      </Label>
      <div className="mt-1 flex flex-wrap items-center gap-1 rounded-lg border border-border bg-well p-1 focus-within:outline-2 focus-within:outline-primary">
        {value.map((member) => (
          <span key={member.cuid} className="flex items-center gap-1 rounded-full bg-overlay/10 py-0.5 pr-1 pl-2 text-sm">
            {member.name}
            <button
              type="button"
              aria-label={`Remove ${member.name}`}
              onClick={() => onChange(value.filter((chosen) => chosen.cuid !== member.cuid))}
              className="rounded-full p-1 hover:bg-overlay/20 focus-visible:outline-2 focus-visible:outline-primary"
            >
              <FaXmark aria-hidden="true" className="size-3" />
            </button>
          </span>
        ))}
        <ComboboxInput
          id={id}
          autoFocus={autoFocus}
          {...(autoFocus ? { 'data-autofocus': true } : {})}
          className="min-w-32 grow bg-transparent px-2 py-1.5 text-base outline-none"
          placeholder={full ? 'The conversation is full' : 'Name or username'}
          disabled={full}
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Backspace' && query === '' && value.length > 0) {
              onChange(value.slice(0, -1));
            }
          }}
          aria-describedby={`${id}-hint`}
        />
      </div>
      <p id={`${id}-hint`} className="mt-1 text-xs text-muted">
        Up to {max} members. Type a name, then choose with the arrows and Enter.
      </p>
      <ComboboxOptions
        anchor="bottom start"
        className="z-50 w-(--input-width) rounded-lg border border-border bg-surface-elevated p-1 shadow-lg empty:invisible"
      >
        {options.map((member) => (
          <ComboboxOption
            key={member.cuid}
            value={member}
            className={({ focus }) => cn('flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm', focus && 'bg-overlay/15')}
          >
            <Avatar name={member.name} imageUrl={member.avatar_url} size="sm" />
            <span className="font-bold">{member.name}</span>
            <span className="text-muted">@{member.username}</span>
          </ComboboxOption>
        ))}
      </ComboboxOptions>
    </Combobox>
  );
}
