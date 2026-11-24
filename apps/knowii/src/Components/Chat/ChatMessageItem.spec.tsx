import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { ChatMessage } from '@knowii/common';
import { ChatMessageItem } from './ChatMessageItem';
import type { ChatMessageItemProps } from './ChatMessageItem';

vi.mock('@/Components/Reactions/ReactionBar', () => ({ ReactionBar: () => null }));
vi.mock('./ChatComposer', () => ({ ChatComposer: () => null }));

const message: ChatMessage = {
  cuid: 'm1',
  body: 'Hello',
  body_html: '<p>Hello</p>',
  author: { cuid: 'me', name: 'Ada Lovelace', username: 'ada', avatar_url: null },
  parent_cuid: null,
  reply_count: 0,
  last_reply_at: null,
  edited_at: null,
  deleted_at: null,
  created_at: new Date('2026-11-15T10:00:00Z'),
};

function renderItem(props: Partial<ChatMessageItemProps> = {}) {
  return render(
    <ChatMessageItem
      message={message}
      communityCuid="c1"
      reactionTargetFor={() => ({}) as ReturnType<ChatMessageItemProps['reactionTargetFor']>}
      currentUserCuid="me"
      canReact
      canModerate={false}
      editing={false}
      onEdit={vi.fn()}
      onCancelEdit={vi.fn()}
      onSave={() => Promise.resolve(true)}
      onDelete={vi.fn()}
      {...props}
    />,
  );
}

describe('the actions of a member on their own message', () => {
  it('offers the edit and the delete in a chat space', () => {
    renderItem();

    expect(screen.getByRole('button', { name: 'Edit the message by Ada Lovelace' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete the message by Ada Lovelace' })).toBeInTheDocument();
  });

  it('offers the delete only in a conversation where the member may no longer write', () => {
    renderItem({ canEditOwn: false });

    expect(screen.queryByRole('button', { name: 'Edit the message by Ada Lovelace' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete the message by Ada Lovelace' })).toBeInTheDocument();
  });

  it('offers nothing on the message of another member', () => {
    renderItem({ currentUserCuid: 'someone-else' });

    expect(screen.queryByRole('button', { name: /^Edit/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Delete/ })).not.toBeInTheDocument();
  });
});
