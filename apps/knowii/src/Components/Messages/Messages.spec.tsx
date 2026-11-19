import { useState } from 'react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Author, Conversation } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { ConversationList } from './ConversationList';
import { MemberPicker } from './MemberPicker';
import { NewConversationDialog } from './NewConversationDialog';

vi.mock('@inertiajs/react', () => ({
  Link: ({
    children,
    href,
    preserveScroll: _preserveScroll,
    ...props
  }: {
    children: React.ReactNode;
    href: string;
    preserveScroll?: boolean;
  }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params: { conversationCuid?: string }) => `/messages/${params?.conversationCuid ?? ''}`,
    writable: true,
  });
  // jsdom has no scrollIntoView: the field scrolls into view when it takes the focus after a removal
  Element.prototype.scrollIntoView = vi.fn();
});

const me: Author = { cuid: 'me', name: 'Me', username: 'me', avatar_url: null };
const ada: Author = { cuid: 'ada', name: 'Ada Lovelace', username: 'ada', avatar_url: null };
const bob: Author = { cuid: 'bob', name: 'Bob Marley', username: 'bob', avatar_url: null };

const conversation = (cuid: string, participants: Author[], extra: Partial<Conversation> = {}): Conversation => ({
  cuid,
  is_direct: participants.length === 2,
  participants,
  last_message: { cuid: `${cuid}-m`, text: 'See you soon', author_name: 'Ada Lovelace', deleted: false },
  last_message_at: new Date(),
  unread_count: 0,
  created_at: new Date(),
  ...extra,
});

describe('ConversationList (#1186)', () => {
  it('names the other members, shows the newest message and the unread count, and marks the open one', () => {
    render(
      <ConversationList
        communitySlug="tests"
        currentUserCuid="me"
        activeCuid="c2"
        conversations={[conversation('c1', [me, ada], { unread_count: 2 }), conversation('c2', [me, ada, bob], { unread_count: 5 })]}
      />,
    );

    const [first, open] = screen.getAllByRole('link');
    if (!first || !open) {
      throw new Error('Two conversations expected');
    }
    expect(first).toHaveAttribute('href', '/messages/c1');
    expect(first).toHaveTextContent('Ada Lovelace: See you soon');
    expect(within(first).getByText('unread')).toBeInTheDocument();
    expect(first).toHaveTextContent('2');

    expect(open).toHaveTextContent('Ada Lovelace, Bob Marley');
    expect(open).toHaveAttribute('aria-current', 'page');
    // The open conversation is being read: no badge
    expect(within(open).queryByText('unread')).toBeNull();
  });
});

describe('MemberPicker (#1186)', () => {
  it('finds members as you type, adds one with the keyboard, and removes the last one with Backspace', async () => {
    vi.spyOn(knowiiApiClient.communities.members, 'search').mockResolvedValue([me, ada, bob]);
    const onChange = vi.fn();
    const { rerender } = render(
      <MemberPicker id="to" label="To" communityCuid="c1" value={[]} onChange={onChange} excludeCuids={['me']} max={9} />,
    );

    const field = screen.getByRole('combobox', { name: 'To' });
    await userEvent.type(field, 'a');
    const options = await screen.findAllByRole('option');
    // The current member is never offered
    expect(options.map((option) => option.textContent)).toEqual([
      expect.stringContaining('Ada Lovelace'),
      expect.stringContaining('Bob Marley'),
    ]);
    await userEvent.keyboard('{Enter}');
    await waitFor(() => expect(onChange).toHaveBeenCalledWith([ada]));

    rerender(<MemberPicker id="to" label="To" communityCuid="c1" value={[ada]} onChange={onChange} excludeCuids={['me']} max={9} />);
    expect(screen.getByRole('button', { name: 'Remove Ada Lovelace' })).toBeInTheDocument();
    await userEvent.click(screen.getByRole('combobox', { name: 'To' }));
    await userEvent.keyboard('{Backspace}');
    expect(onChange).toHaveBeenLastCalledWith([]);
  });
});

describe('MemberPicker, the remove button of a member (#1514)', () => {
  function Picker({ start, max }: { start: Author[]; max: number }) {
    const [value, setValue] = useState(start);
    return <MemberPicker id="to" label="To" communityCuid="c1" value={value} onChange={setValue} excludeCuids={[]} max={max} />;
  }

  it('gives the focus to the field after a removal with the keyboard, when the full picker has room again', async () => {
    const user = userEvent.setup();
    render(<Picker start={[ada]} max={1} />);
    const field = screen.getByRole('combobox', { name: 'To' });
    // Full: the field is disabled, so the remove button is the only stop of the Tab key
    expect(field).toBeDisabled();

    await user.tab();
    expect(screen.getByRole('button', { name: 'Remove Ada Lovelace' })).toHaveFocus();
    await user.keyboard('{Enter}');

    expect(screen.queryByRole('button', { name: 'Remove Ada Lovelace' })).toBeNull();
    await waitFor(() => expect(field).toHaveFocus());
    expect(field).toBeEnabled();
  });

  it('gives the focus to the field after a removal in a picker that was not full', async () => {
    const user = userEvent.setup();
    render(<Picker start={[ada, bob]} max={9} />);

    await user.click(screen.getByRole('button', { name: 'Remove Ada Lovelace' }));

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'To' })).toHaveFocus());
    expect(screen.getByRole('button', { name: 'Remove Bob Marley' })).toBeInTheDocument();
  });

  it('has a remove button of 44 px on a touch screen, and of 24 px on a wider screen', () => {
    render(<Picker start={[ada]} max={1} />);

    const button = screen.getByRole('button', { name: 'Remove Ada Lovelace' });
    // `touch-target` is the 44 px of the height below the `md` width; the two other pairs are the width, and the 24 px
    expect(button).toHaveClass('touch-target', 'min-w-11', 'md:size-6', 'md:min-w-0');
  });
});

describe('NewConversationDialog (#1186)', () => {
  it('asks for a member and a message, then starts the conversation', async () => {
    const start = vi
      .spyOn(knowiiApiClient.communities.conversations, 'start')
      .mockResolvedValue({ ok: true, message: '', data: conversation('c9', [me, ada]) });
    const onStarted = vi.fn();
    render(
      <AppDataProvider>
        <NewConversationDialog open communityCuid="c1" currentUserCuid="me" initialMembers={[]} onClose={vi.fn()} onStarted={onStarted} />
      </AppDataProvider>,
    );

    const dialog = await screen.findByRole('dialog', { name: 'New message' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Send' }));
    expect(await within(dialog).findByText('Choose at least one member')).toBeInTheDocument();
    expect(start).not.toHaveBeenCalled();
  });

  it('sends to the member chosen on their page', async () => {
    const start = vi
      .spyOn(knowiiApiClient.communities.conversations, 'start')
      .mockResolvedValue({ ok: true, message: '', data: conversation('c9', [me, ada]) });
    const onStarted = vi.fn();
    render(
      <AppDataProvider>
        <NewConversationDialog
          open
          communityCuid="c1"
          currentUserCuid="me"
          initialMembers={[ada]}
          onClose={vi.fn()}
          onStarted={onStarted}
        />
      </AppDataProvider>,
    );

    const dialog = await screen.findByRole('dialog', { name: 'New message' });
    await userEvent.type(within(dialog).getByLabelText('Message'), 'Hello Ada');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Send' }));

    await waitFor(() => expect(start).toHaveBeenCalledWith('c1', { participant_cuids: ['ada'], body: 'Hello Ada' }));
    expect(onStarted).toHaveBeenCalled();
  });
});
