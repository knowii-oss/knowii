import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import {
  isReadableByVisitors,
  PUBLIC_CHAT_SPACE_FORM_NOTE,
  PUBLIC_SPACE_FORM_WARNING,
  publicSubmitLabel,
  PublicLabel,
} from './PublicSpaceNotice';

describe('the notice of a public space (#1387)', () => {
  it('a public space is readable by visitors, except a chat space', () => {
    expect(isReadableByVisitors({ visibility: 'public', type: 'discussion' })).toBe(true);
    expect(isReadableByVisitors({ visibility: 'public', type: 'course' })).toBe(true);
    expect(isReadableByVisitors({ visibility: 'public', type: 'chat' })).toBe(false);
    expect(isReadableByVisitors({ visibility: 'members', type: 'discussion' })).toBe(false);
    expect(isReadableByVisitors({ visibility: 'groups', type: 'pages' })).toBe(false);
  });

  it('shows a "Public" label for a space that anyone can read, and nothing else', () => {
    const { rerender } = render(<PublicLabel space={{ visibility: 'public', type: 'discussion' }} />);
    expect(screen.getByText('Public')).toBeInTheDocument();

    rerender(<PublicLabel space={{ visibility: 'public', type: 'chat' }} />);
    expect(screen.queryByText('Public')).toBeNull();
    rerender(<PublicLabel space={{ visibility: 'members', type: 'discussion' }} />);
    expect(screen.queryByText('Public')).toBeNull();
  });

  it('puts "publicly" on the submit buttons of a public space only', () => {
    const open = { visibility: 'public', type: 'discussion' } as const;
    const closed = { visibility: 'members', type: 'discussion' } as const;

    expect(publicSubmitLabel(open, 'post', 'Publish')).toBe('Post publicly');
    expect(publicSubmitLabel(open, 'reply', 'Comment')).toBe('Reply publicly');
    expect(publicSubmitLabel(closed, 'post', 'Publish')).toBe('Publish');
    expect(publicSubmitLabel(undefined, 'reply', 'Reply')).toBe('Reply');
  });

  it('has the texts of the space form', () => {
    expect(PUBLIC_SPACE_FORM_WARNING).toBe(
      'Anyone on the internet can read this space, without an account, with the names and pictures of the authors.',
    );
    expect(PUBLIC_CHAT_SPACE_FORM_NOTE).toBe('A chat space stays for signed-in people.');
  });
});
