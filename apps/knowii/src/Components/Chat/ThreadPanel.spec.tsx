import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ChatMessage } from '@knowii/common';
import { spyOnFollowApi } from '@/test-utils/notifications';
import { ThreadPanel } from './ThreadPanel';
import type { ThreadPanelProps } from './ThreadPanel';

vi.mock('./ChatMessageItem', () => ({
  ChatMessageItem: ({ message }: { message: ChatMessage }) => <p>{message.body}</p>,
}));
vi.mock('./ChatComposer', () => ({ ChatComposer: () => null }));
vi.mock('@/Components/Presence/TypingIndicator', () => ({ TypingIndicator: () => null }));

const parent = (changes: Partial<ChatMessage> = {}): ChatMessage => ({
  cuid: 'm1',
  body: 'First message',
  body_html: '<p>First message</p>',
  author: null,
  parent_cuid: null,
  reply_count: 0,
  last_reply_at: null,
  edited_at: null,
  deleted_at: null,
  created_at: new Date('2026-11-15T10:00:00Z'),
  ...changes,
});

function renderPanel(props: Partial<ThreadPanelProps> = {}) {
  return render(
    <ThreadPanel
      open
      onClose={vi.fn()}
      communityCuid="c1"
      parent={parent()}
      replies={[]}
      canWrite={false}
      onReply={() => Promise.resolve(true)}
      item={{} as ThreadPanelProps['item']}
      editingCuid={null}
      {...props}
    />,
  );
}

describe('the follow control of a chat thread (#1497)', () => {
  let api: ReturnType<typeof spyOnFollowApi>;

  beforeEach(() => {
    api = spyOnFollowApi();
  });
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows the state of the thread and its actions to a member, by the first message', async () => {
    api.getMessage.mockResolvedValue(api.follow('following', 'replied'));
    renderPanel({ follow: { spaceCuid: 's1' } });

    const group = await screen.findByRole('group', { name: 'Notifications for this thread' });
    expect(group).toHaveTextContent('Following (you replied)');
    expect(api.getMessage).toHaveBeenCalledWith('c1', 's1', 'm1');

    await userEvent.click(screen.getByRole('button', { name: 'Stop following' }));
    expect(api.stopMessage).toHaveBeenCalledWith('c1', 's1', 'm1');
  });

  it('shows no control to a person who cannot follow (a visitor, or someone outside the community)', async () => {
    renderPanel();

    await screen.findByText('First message');
    expect(screen.queryByRole('group', { name: 'Notifications for this thread' })).toBeNull();
    expect(api.getMessage).not.toHaveBeenCalled();
  });

  it('shows no control for a deleted message', async () => {
    renderPanel({ follow: { spaceCuid: 's1' }, parent: parent({ deleted_at: new Date() }) });

    await waitFor(() => expect(screen.getByText('First message')).toBeInTheDocument());
    expect(api.getMessage).not.toHaveBeenCalled();
  });
});
