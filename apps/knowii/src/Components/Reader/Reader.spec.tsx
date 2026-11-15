import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import type { Comment, CourseMaterial, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { CommentSection } from '@/Components/Comments/CommentSection';
import { PostComposer } from '@/Components/Posts/PostComposer';
import { ReactionBar } from '@/Components/Reactions/ReactionBar';
import { CourseMaterials } from '@/Components/SpacePages/CourseMaterials';
import { emitSocketEvent } from '@/test-utils/fake-echo';
import { SignInPrompt, VisitorProvider } from './Reader';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { reload: vi.fn(), visit: vi.fn() },
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

const now = new Date();
const ada = { cuid: 'ada', name: 'Ada', username: 'ada', avatar_url: null };
const comments: Comment[] = [
  {
    cuid: 'c1',
    body: 'First',
    body_html: '<p>First</p>',
    edited_at: null,
    author: ada,
    parent_cuid: null,
    replies: [],
    created_at: now,
    updated_at: now,
  },
];
const subject = { communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' };
const publicSpace: Pick<Space, 'visibility' | 'type'> = { visibility: 'public', type: 'discussion' };

function asVisitor(children: React.ReactNode) {
  return render(
    <AppDataProvider>
      <VisitorProvider value={true}>{children}</VisitorProvider>
    </AppDataProvider>,
  );
}

describe('the pages of a visitor (#1387)', () => {
  it('shows a link to the sign-in page in place of the comment composer', () => {
    asVisitor(
      <CommentSection subject={subject} initialComments={comments} currentUserCuid={null} canComment={false} canModerate={false} />,
    );

    expect(screen.getByRole('link', { name: 'Sign in to reply' })).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('form', { name: 'Write a comment' })).toBeNull();
    expect(screen.queryByRole('button', { name: /^Reply to/ })).toBeNull();
  });

  it('keeps the composer, and no link, for a member', () => {
    render(
      <AppDataProvider>
        <CommentSection subject={subject} initialComments={comments} currentUserCuid="bob" canComment={true} canModerate={false} />
      </AppDataProvider>,
    );

    expect(screen.queryByRole('link', { name: 'Sign in to reply' })).toBeNull();
    expect(screen.getByRole('form', { name: 'Write a comment' })).toBeInTheDocument();
  });

  it('listens to no channel: a comment that arrives does not load anything', () => {
    const list = vi.spyOn(knowiiApiClient.communities.comments, 'list');
    asVisitor(
      <CommentSection subject={subject} initialComments={comments} currentUserCuid={null} canComment={false} canModerate={false} />,
    );

    emitSocketEvent('community.community.space.space', 'space.comment.created', { space_cuid: 'space', post_cuid: 'post' });

    expect(list).not.toHaveBeenCalled();
  });

  it('shows the reactions as counts: no button, no picker, no list of who reacted', () => {
    asVisitor(
      <ReactionBar
        target={{ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }}
        initialReactions={[{ emoji: '👍', count: 3, reacted: false }]}
        canReact={false}
        itemLabel="this post"
      />,
    );

    const reactions = screen.getByRole('list', { name: 'Reactions to this post' });
    expect(within(reactions).getByText('3')).toBeInTheDocument();
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('keeps who reacted for a signed-in reader', () => {
    render(
      <AppDataProvider>
        <ReactionBar
          target={{ communityCuid: 'community', spaceCuid: 'space', postCuid: 'post' }}
          initialReactions={[{ emoji: '👍', count: 3, reacted: false }]}
          canReact={false}
          itemLabel="this post"
        />
      </AppDataProvider>,
    );

    expect(screen.getByRole('button', { name: 'See who reacted to this post' })).toBeInTheDocument();
  });

  it('lists the materials of a course with "Sign in to download" in place of the download', async () => {
    const file: CourseMaterial = {
      cuid: 'm1',
      kind: 'file',
      title: 'Cheat sheet',
      page_cuid: null,
      position: 0,
      file_name: 'cheat.pdf',
      size_bytes: 2048,
      url: null,
    } as CourseMaterial;
    vi.spyOn(knowiiApiClient.communities.materials, 'list').mockResolvedValue({
      ok: true,
      status: 200,
      data: [file],
      message: '',
    } as never);

    asVisitor(<CourseMaterials communityCuid="community" spaceCuid="space" targets={[{ pageCuid: null }]} canManage={false} />);

    expect(await screen.findByText('Cheat sheet')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign in to download' })).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('link', { name: /\(download\)/ })).toBeNull();
  });
});

describe('SignInPrompt', () => {
  it('is a link to the sign-in page', () => {
    render(<SignInPrompt>Sign in to post</SignInPrompt>);

    expect(screen.getByRole('link', { name: 'Sign in to post' })).toHaveAttribute('href', '/login');
  });
});

describe('the notice of a public space (#1387)', () => {
  it('puts "publicly" on the button of the post composer of a public space', () => {
    render(
      <AppDataProvider>
        <PostComposer
          open={true}
          communityCuid="c"
          spaceCuid="s"
          spaceName="News"
          space={publicSpace}
          onClose={() => undefined}
          onSaved={() => undefined}
        />
      </AppDataProvider>,
    );

    expect(screen.getByRole('button', { name: 'Post publicly' })).toBeInTheDocument();
  });

  it('keeps "Publish" in a space that is not public, and in a public chat space', () => {
    const { rerender } = render(
      <AppDataProvider>
        <PostComposer
          open={true}
          communityCuid="c"
          spaceCuid="s"
          spaceName="News"
          space={{ visibility: 'members', type: 'discussion' }}
          onClose={() => undefined}
          onSaved={() => undefined}
        />
      </AppDataProvider>,
    );
    expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument();

    rerender(
      <AppDataProvider>
        <PostComposer
          open={true}
          communityCuid="c"
          spaceCuid="s"
          spaceName="News"
          space={{ visibility: 'public', type: 'chat' }}
          onClose={() => undefined}
          onSaved={() => undefined}
        />
      </AppDataProvider>,
    );
    expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument();
  });

  it('puts "publicly" on the button of the comment form of a public space', () => {
    render(
      <AppDataProvider>
        <CommentSection
          subject={subject}
          initialComments={comments}
          currentUserCuid="bob"
          canComment={true}
          canModerate={false}
          space={publicSpace}
        />
      </AppDataProvider>,
    );

    expect(screen.getByRole('button', { name: 'Reply publicly' })).toBeInTheDocument();
  });
});
