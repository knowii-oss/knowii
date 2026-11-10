import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ChatMessage, Community, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { emitPresenceHere, emitSocketEvent, emitWhisper, joinedPresenceChannels, sentWhispers } from '@/test-utils/fake-echo';
import { ChatSpace } from './ChatSpace';

const community = { cuid: 'c1', slug: 'tests', name: 'Tests' } as Community;
const space = { cuid: 's1', slug: 'general', name: 'General', type: 'chat' } as Space;
const channel = 'community.c1.space.s1';

function message(cuid: string, body: string, authorCuid = 'ada', extra: Partial<ChatMessage> = {}): ChatMessage {
  return {
    cuid,
    body,
    body_html: `<p>${body}</p>`,
    author: { cuid: authorCuid, name: authorCuid === 'me' ? 'Me' : 'Ada', username: authorCuid, avatar_url: null },
    parent_cuid: null,
    reply_count: 0,
    last_reply_at: null,
    edited_at: null,
    deleted_at: null,
    reactions: [],
    created_at: new Date(Date.now() - 60_000 + Number(cuid.replace(/\D/g, '') || 0) * 1000),
    ...extra,
  } as ChatMessage;
}

function renderChat(
  messages: ChatMessage[],
  firstUnreadCuid: string | null = null,
  presence: { enabled: boolean; visible: boolean } | null = null,
) {
  render(
    <AppDataProvider>
      <ChatSpace
        community={community}
        space={space}
        initialMessages={messages}
        hasMore={false}
        firstUnreadCuid={firstUnreadCuid}
        currentUserCuid="me"
        canWrite
        canReact
        canModerate={false}
        presenceEnabled={presence?.enabled ?? false}
        showsOnlineStatus={presence?.visible ?? true}
      />
    </AppDataProvider>,
  );
}

describe('ChatSpace', () => {
  beforeEach(() => {
    vi.spyOn(knowiiApiClient.communities.chat, 'markAsRead').mockResolvedValue({ ok: true, message: '', data: undefined });
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows the messages in order with the "new messages" line, and marks them as read (#1185)', () => {
    renderChat([message('m1', 'First'), message('m2', 'Second'), message('m3', 'Third')], 'm2');

    const log = screen.getByRole('log', { name: 'Messages' });
    expect(
      within(log)
        .getAllByRole('article')
        .map((item) => item.textContent),
    ).toEqual([expect.stringContaining('First'), expect.stringContaining('Second'), expect.stringContaining('Third')]);
    const line = within(log).getByRole('separator', { name: 'New messages' });
    expect(line.closest('li')).toHaveTextContent('Second');
    expect(knowiiApiClient.communities.chat.markAsRead).toHaveBeenCalledWith('c1', 's1', 'm3');
  });

  it('sends with Enter, adds a line with Shift+Enter', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.chat, 'create')
      .mockResolvedValue({ ok: true, message: '', data: message('m9', 'Hello', 'me') });
    renderChat([message('m1', 'First')]);

    const field = screen.getByLabelText('Message General');
    await userEvent.type(field, 'Hello{Shift>}{Enter}{/Shift}there');
    expect(field).toHaveValue('Hello\nthere');
    expect(create).not.toHaveBeenCalled();

    await userEvent.type(field, '{Enter}');

    expect(create).toHaveBeenCalledWith('c1', 's1', { body: 'Hello\nthere' });
    await waitFor(() => expect(field).toHaveValue(''));
    expect(screen.getAllByRole('article').at(-1)).toHaveTextContent('Hello');
  });

  it('edits the last own message with the Up arrow', async () => {
    renderChat([message('m1', 'Mine', 'me'), message('m2', 'From Ada')]);

    await userEvent.click(screen.getByLabelText('Message General'));
    await userEvent.keyboard('{ArrowUp}');

    expect(screen.getByLabelText('Edit the message')).toHaveValue('Mine');
  });

  it('shows a message of another member live, loaded through the API (identifiers only)', async () => {
    vi.spyOn(knowiiApiClient.communities.chat, 'get').mockResolvedValue({ ok: true, message: '', data: message('m5', 'Live hello') });
    renderChat([message('m1', 'First')]);

    act(() =>
      emitSocketEvent(channel, 'space.message.created', { space_cuid: 's1', message_cuid: 'm5', parent_cuid: null, author_cuid: 'ada' }),
    );

    expect(await screen.findByText('Live hello')).toBeInTheDocument();
  });

  it('opens the thread of a message in a side panel and replies in it', async () => {
    const parent = message('m1', 'Question?', 'ada', { reply_count: 1 });
    vi.spyOn(knowiiApiClient.communities.chat, 'thread').mockResolvedValue({
      ok: true,
      message: '',
      data: { message: parent, replies: [message('r1', 'An answer', 'ada', { parent_cuid: 'm1' })] },
    });
    const create = vi
      .spyOn(knowiiApiClient.communities.chat, 'create')
      .mockResolvedValue({ ok: true, message: '', data: message('r2', 'Thanks', 'me', { parent_cuid: 'm1' }) });
    vi.spyOn(knowiiApiClient.communities.chat, 'get').mockResolvedValue({ ok: true, message: '', data: { ...parent, reply_count: 2 } });
    renderChat([parent]);

    await userEvent.click(screen.getByRole('button', { name: /1 reply/ }));
    const panel = await screen.findByRole('dialog', { name: 'Thread' });
    expect(await within(panel).findByText('An answer')).toBeInTheDocument();

    await userEvent.type(within(panel).getByLabelText('Reply in the thread'), 'Thanks{Enter}');

    expect(create).toHaveBeenCalledWith('c1', 's1', { body: 'Thanks', parent_cuid: 'm1' });
    expect(await within(panel).findByText('Thanks')).toBeInTheDocument();
  });

  it('shows a deleted message as a marker', () => {
    renderChat([message('m1', '', 'ada', { deleted_at: new Date(), body: null, body_html: null, author: null })]);

    expect(screen.getByText('This message was deleted.')).toBeInTheDocument();
  });
});

describe('ChatSpace presence and typing (#1187)', () => {
  const presenceChannel = 'community.c1.space.s1.presence';
  const me = { cuid: 'me', name: 'Me', username: 'me', avatar_url: null };
  const ada = { cuid: 'ada', name: 'Ada', username: 'ada', avatar_url: null };

  beforeEach(() => {
    Object.defineProperty(globalThis, 'route', { value: () => '/member', writable: true, configurable: true });
    vi.spyOn(knowiiApiClient.communities.chat, 'markAsRead').mockResolvedValue({ ok: true, message: '', data: undefined });
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows who is here and who is typing; ignores a signal from someone who is not in the channel', async () => {
    renderChat([message('m1', 'First')], null, { enabled: true, visible: true });

    act(() => emitPresenceHere(presenceChannel, [me, ada]));
    // One compact button (#1299); the panel lists everybody
    await userEvent.click(screen.getByRole('button', { name: '2 members here, show the list' }));
    const here = await screen.findByRole('list', { name: 'Here now' });
    expect(within(here).getByRole('link', { name: /^Ada/ })).toBeInTheDocument();
    expect(within(here).getByRole('link', { name: /^Me/ })).toBeInTheDocument();
    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('list', { name: 'Here now' })).toBeNull());

    act(() => emitWhisper(presenceChannel, 'typing', { scope: '', typing: true }, 'mallory'));
    expect(screen.queryByText(/is typing/)).toBeNull();

    act(() => emitWhisper(presenceChannel, 'typing', { scope: '', typing: true }, 'ada'));
    expect(screen.getByText('Ada is typing…')).toBeInTheDocument();

    act(() => emitWhisper(presenceChannel, 'typing', { scope: '', typing: false }, 'ada'));
    expect(screen.queryByText('Ada is typing…')).toBeNull();
  });

  it('tells the others while the member types, and stops when the message is sent', async () => {
    vi.spyOn(knowiiApiClient.communities.chat, 'create').mockResolvedValue({ ok: true, message: '', data: message('m9', 'Hi', 'me') });
    renderChat([message('m1', 'First')], null, { enabled: true, visible: true });
    act(() => emitPresenceHere(presenceChannel, [me, ada]));

    await userEvent.type(screen.getByLabelText('Message General'), 'Hi');
    // Throttled: one signal for the two letters
    expect(sentWhispers(presenceChannel)).toEqual([{ event: 'typing', data: { scope: '', typing: true } }]);

    await userEvent.type(screen.getByLabelText('Message General'), '{Enter}');
    await waitFor(() => expect(sentWhispers(presenceChannel).at(-1)).toEqual({ event: 'typing', data: { scope: '', typing: false } }));
  });

  it('a hidden member never joins: the list comes from the API, and typing sends nothing', async () => {
    const online = vi.spyOn(knowiiApiClient.communities.spaces, 'onlineMembers').mockResolvedValue({ ok: true, message: '', data: [ada] });
    renderChat([message('m1', 'First')], null, { enabled: true, visible: false });

    expect(await screen.findByRole('button', { name: '1 member here, show the list' })).toBeInTheDocument();
    expect(online).toHaveBeenCalledWith('c1', 's1');
    expect(joinedPresenceChannels()).toEqual([]);

    await userEvent.type(screen.getByLabelText('Message General'), 'Hi');
    expect(sentWhispers(presenceChannel)).toEqual([]);
  });

  it('visitors who are not members get no presence at all', () => {
    const online = vi.spyOn(knowiiApiClient.communities.spaces, 'onlineMembers');
    renderChat([message('m1', 'First')], null, { enabled: false, visible: true });

    expect(joinedPresenceChannels()).toEqual([]);
    expect(online).not.toHaveBeenCalled();
    expect(screen.queryByRole('button', { name: /here, show the list/ })).toBeNull();
  });
});
