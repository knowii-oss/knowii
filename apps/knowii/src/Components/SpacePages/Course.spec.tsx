import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, Page, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { VideoPlayer } from '@/Components/Video/VideoPlayer';
import { PageEditor } from './PageEditor';
import { PageList } from './PageList';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { reload: vi.fn(), visit: vi.fn() },
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params: Record<string, string>) => `/lesson/${params['pageSlug']}`,
    writable: true,
    configurable: true,
  });
});

const now = new Date();
const community = { cuid: 'c1', slug: 'tests', name: 'Tests' } as Community;
const course = { cuid: 's1', slug: 'osc', name: 'Obsidian Starter Course', type: 'course' } as Space;
const youtube = {
  provider: 'youtube' as const,
  id: 'dQw4w9WgXcQ',
  url: 'https://youtu.be/dQw4w9WgXcQ',
  embed_url: 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
};
const row = (cuid: string, title: string, kind: 'page' | 'section', video = false): Page => ({
  cuid,
  kind,
  title,
  slug: cuid,
  position: 0,
  video: video ? youtube : null,
  created_at: now,
  updated_at: now,
});

describe('Course spaces (#1188)', () => {
  it('shows the outline: sections as headings, lessons numbered without them, the videos, and "Start the course"', () => {
    render(
      <AppDataProvider>
        <PageList
          community={community}
          space={course}
          canManagePages={false}
          pages={[
            row('s-a', 'Getting started', 'section'),
            row('install', 'Install Obsidian', 'page', true),
            row('s-b', 'Linking', 'section'),
            row('links', 'Links', 'page'),
          ]}
        />
      </AppDataProvider>,
    );

    expect(screen.getByText('2 lessons')).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent)).toEqual(['Getting started', 'Linking']);
    const links = screen.getByRole('link', { name: 'Links' }).closest('li');
    expect(links).toHaveTextContent('2.');
    expect(
      within(screen.getByRole('link', { name: 'Install Obsidian' }).closest('li') as HTMLElement).getByText('Video'),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Start the course/ })).toHaveAttribute('href', '/lesson/install');
    expect(screen.queryByRole('button', { name: /Add a lesson/ })).toBeNull();
  });

  it('loads the video player only when the member presses Play', async () => {
    render(<VideoPlayer video={youtube} title="Install Obsidian" />);

    expect(document.querySelector('iframe')).toBeNull();
    expect(screen.getByText('The YouTube player loads when you press Play.')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Play the video: Install Obsidian' }));

    const player = screen.getByTitle('Video: Install Obsidian');
    expect(player).toHaveAttribute('src', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1');
    expect(player).toHaveAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
  });

  it('does not ask the Vimeo player to start on its own (some browsers refuse it)', async () => {
    const vimeo = {
      provider: 'vimeo' as const,
      id: '76979871',
      url: 'https://vimeo.com/76979871',
      embed_url: 'https://player.vimeo.com/video/76979871?dnt=1',
    };
    render(<VideoPlayer video={vimeo} title="Links" />);

    await userEvent.click(screen.getByRole('button', { name: 'Play the video: Links' }));

    expect(screen.getByTitle('Video: Links')).toHaveAttribute('src', 'https://player.vimeo.com/video/76979871?dnt=1');
  });

  it('asks a lesson for a YouTube or Vimeo address, and a section for a title only', async () => {
    const create = vi
      .spyOn(knowiiApiClient.communities.pages, 'create')
      .mockResolvedValue({ ok: true, message: '', data: row('new', 'Part one', 'section') });
    const { rerender } = render(
      <AppDataProvider>
        <PageEditor open communityCuid="c1" spaceCuid="s1" spaceName="OSC" mode="lesson" onClose={vi.fn()} onSaved={vi.fn()} />
      </AppDataProvider>,
    );

    await userEvent.type(screen.getByLabelText('Video (optional)'), 'https://example.com/video.mp4');
    expect(await screen.findByText('Use the address of a YouTube or Vimeo video')).toBeInTheDocument();

    rerender(
      <AppDataProvider>
        <PageEditor open communityCuid="c1" spaceCuid="s1" spaceName="OSC" mode="section" onClose={vi.fn()} onSaved={vi.fn()} />
      </AppDataProvider>,
    );
    const dialog = screen.getByRole('dialog', { name: 'New section in OSC' });
    expect(within(dialog).queryByLabelText('Text')).toBeNull();
    await userEvent.clear(within(dialog).getByLabelText('Title'));
    await userEvent.type(within(dialog).getByLabelText('Title'), 'Part one');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Create' }));

    await waitFor(() => expect(create).toHaveBeenCalledWith('c1', 's1', { kind: 'section', title: 'Part one' }));
  });
});
