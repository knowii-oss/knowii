import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ChatMessage, Community, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { emitSocketEvent } from '@/test-utils/fake-echo';
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

function renderChat(messages: ChatMessage[], firstUnreadCuid: string | null = null) {
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
