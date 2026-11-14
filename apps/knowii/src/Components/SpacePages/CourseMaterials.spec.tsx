import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { CourseMaterial } from '@knowii/common';
import { AppDataProvider, formatFileSize, isCourseMaterialFileName, knowiiApiClient } from '@knowii/common';
import { CourseMaterials } from './CourseMaterials';

const material = (cuid: string, title: string, overrides: Partial<CourseMaterial> = {}): CourseMaterial => ({
  cuid,
  kind: 'link',
  title,
  position: 0,
  page_cuid: null,
  url: `https://example.com/${cuid}`,
  file_name: null,
  mime_type: null,
  size_bytes: null,
  created_at: new Date('2026-11-14T10:00:00Z'),
  updated_at: new Date('2026-11-14T10:00:00Z'),
  ...overrides,
});

const pdf = material('f1', 'Cheat sheet', {
  kind: 'file',
  url: null,
  file_name: 'cheat-sheet.pdf',
  mime_type: 'application/pdf',
  size_bytes: 3 * 1024 * 1024,
  position: 0,
});
const template = material('l1', 'Template vault', { position: 1 });
const ofSection = material('s1', 'Section slides', { page_cuid: 'sec' });
const ofLesson = material('p1', 'Lesson notes', { page_cuid: 'lesson' });

const outline = [
  { pageCuid: null, label: 'Whole course' },
  { pageCuid: 'sec', label: 'Basics' },
];

function renderMaterials(canManage: boolean, targets = outline) {
  render(
    <AppDataProvider>
      <CourseMaterials communityCuid="c1" spaceCuid="sp1" targets={targets} canManage={canManage} />
    </AppDataProvider>,
  );
}

describe('CourseMaterials', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.spyOn(knowiiApiClient.communities.materials, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: [template, pdf, ofSection, ofLesson],
    });
  });

  it('shows the materials of each target in their order, with a download link for files (#1336)', async () => {
    renderMaterials(false);

    const course = await screen.findByRole('list', { name: 'Whole course' });
    const items = within(course).getAllByRole('listitem');
    expect(items).toHaveLength(2);
    const download = within(items[0] as HTMLElement).getByRole('link', { name: /Cheat sheet/ });
    expect(download).toHaveAttribute('href', '/api/v1/communities/c1/spaces/sp1/materials/f1/download');
    expect(download).toHaveAttribute('download', 'cheat-sheet.pdf');
    expect(within(items[0] as HTMLElement).getByText('cheat-sheet.pdf · 3.0 MB')).toBeInTheDocument();

    const link = within(items[1] as HTMLElement).getByRole('link', { name: /Template vault/ });
    expect(link).toHaveAttribute('href', 'https://example.com/l1');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');

    expect(within(screen.getByRole('list', { name: 'Basics' })).getByText('Section slides')).toBeInTheDocument();
    // The materials of a lesson are on its page, not here
    expect(screen.queryByText('Lesson notes')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Add a material/ })).not.toBeInTheDocument();
  });

  it('shows nothing to a member when there is no material, and the controls to a manager (#1336)', async () => {
    const list = vi.spyOn(knowiiApiClient.communities.materials, 'list').mockResolvedValue({ ok: true, message: '', data: [] });
    renderMaterials(false);
    await waitFor(() => expect(list).toHaveBeenCalled());
    expect(screen.queryByRole('region', { name: 'Materials' })).not.toBeInTheDocument();

    renderMaterials(true);
    expect(await screen.findByRole('region', { name: 'Materials' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add a material to Whole course' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add a material to Basics' })).toBeInTheDocument();
  });

  it('never makes a link of an address that is not http or https (#1336, front-end rule 5)', async () => {
    vi.spyOn(knowiiApiClient.communities.materials, 'list').mockResolvedValue({
      ok: true,
      message: '',
      data: [material('x', 'Trap', { url: 'javascript:alert(1)' })],
    });
    renderMaterials(false);

    expect(await screen.findByText('Trap')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Trap/ })).not.toBeInTheDocument();
  });

  it('adds a link to a section after it checks the address (#1336)', async () => {
    const created = material('n1', 'Reading list', { page_cuid: 'sec', position: 1 });
    const addLink = vi.spyOn(knowiiApiClient.communities.materials, 'addLink').mockResolvedValue({ ok: true, message: '', data: created });
    renderMaterials(true);

    await userEvent.click(await screen.findByRole('button', { name: 'Add a material to Basics' }));
    const dialog = await screen.findByRole('dialog', { name: 'Add a material to Basics' });
    await userEvent.click(within(dialog).getByRole('radio', { name: 'Link' }));
    await userEvent.type(within(dialog).getByLabelText('Title'), 'Reading list');
    await userEvent.type(within(dialog).getByLabelText('Address'), 'example.com/list');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));

    expect(await within(dialog).findByText('Enter an address that starts with http:// or https://')).toBeInTheDocument();
    expect(addLink).not.toHaveBeenCalled();

    await userEvent.clear(within(dialog).getByLabelText('Address'));
    await userEvent.type(within(dialog).getByLabelText('Address'), 'https://example.com/list');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));

    await waitFor(() =>
      expect(addLink).toHaveBeenCalledWith('c1', 'sp1', 'sec', { title: 'Reading list', url: 'https://example.com/list' }),
    );
    expect(await within(screen.getByRole('list', { name: 'Basics' })).findByText('Reading list')).toBeInTheDocument();
  });

  it('adds a file to a lesson: the name of the file is the first title, and a wrong type is refused (#1336)', async () => {
    vi.spyOn(knowiiApiClient.communities.materials, 'list').mockResolvedValue({ ok: true, message: '', data: [] });
    const created = material('n2', 'Workbook', { kind: 'file', url: null, file_name: 'Workbook.pdf', size_bytes: 10, page_cuid: 'lesson' });
    const addFile = vi.spyOn(knowiiApiClient.communities.materials, 'addFile').mockResolvedValue({ ok: true, message: '', data: created });
    renderMaterials(true, [{ pageCuid: 'lesson' }]);

    await userEvent.click(await screen.findByRole('button', { name: 'Add a material' }));
    const dialog = await screen.findByRole('dialog', { name: 'Add a material' });
    const input = within(dialog).getByLabelText('File', { selector: 'input[type="file"]' });

    await userEvent.upload(input, new File(['x'], 'movie.mp4', { type: 'video/mp4' }), { applyAccept: false });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));
    expect(await within(dialog).findByText('This type of file is not accepted')).toBeInTheDocument();
    expect(addFile).not.toHaveBeenCalled();

    const file = new File(['%PDF-1.4'], 'Workbook.pdf', { type: 'application/pdf' });
    await userEvent.clear(within(dialog).getByLabelText('Title'));
    await userEvent.upload(input, file);
    expect(within(dialog).getByLabelText('Title')).toHaveValue('Workbook');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Add' }));

    await waitFor(() => expect(addFile).toHaveBeenCalledWith('c1', 'sp1', 'lesson', { title: 'Workbook', file }));
    expect(await screen.findByRole('link', { name: /Workbook/ })).toBeInTheDocument();
  });

  it('moves, edits and deletes a material (#1336)', async () => {
    const reorder = vi.spyOn(knowiiApiClient.communities.materials, 'reorder').mockResolvedValue({
      ok: true,
      message: '',
      data: [
        { ...template, position: 0 },
        { ...pdf, position: 1 },
      ],
    });
    const update = vi
      .spyOn(knowiiApiClient.communities.materials, 'update')
      .mockResolvedValue({ ok: true, message: '', data: { ...template, position: 0, title: 'Starter vault' } });
    const remove = vi.spyOn(knowiiApiClient.communities.materials, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    renderMaterials(true, [{ pageCuid: null, label: 'Whole course' }]);

    // aria-disabled keeps the focus on the button when the material reaches the end of the list
    expect(await screen.findByRole('button', { name: 'Move "Cheat sheet" up' })).toHaveAttribute('aria-disabled', 'true');
    await userEvent.click(screen.getByRole('button', { name: 'Move "Cheat sheet" down' }));
    await waitFor(() => expect(reorder).toHaveBeenCalledWith('c1', 'sp1', null, ['l1', 'f1']));
    await waitFor(() =>
      expect(
        within(screen.getByRole('list', { name: 'Whole course' }))
          .getAllByRole('link')
          .map((item) => item.textContent),
      ).toEqual(['Template vault (opens in a new tab)', 'Cheat sheet (download)']),
    );

    await userEvent.click(screen.getByRole('button', { name: 'Edit "Template vault"' }));
    const edit = await screen.findByRole('dialog', { name: 'Edit the material' });
    const title = within(edit).getByLabelText('Title');
    expect(title).toHaveValue('Template vault');
    await userEvent.clear(title);
    await userEvent.type(title, 'Starter vault');
    await userEvent.click(within(edit).getByRole('button', { name: 'Save' }));
    await waitFor(() => expect(update).toHaveBeenCalledWith('c1', 'sp1', 'l1', { title: 'Starter vault', url: 'https://example.com/l1' }));
    expect(await screen.findByRole('link', { name: /Starter vault/ })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Delete "Cheat sheet"' }));
    const confirm = await screen.findByRole('dialog', { name: 'Delete "Cheat sheet"?' });
    await userEvent.click(within(confirm).getByRole('button', { name: 'Delete' }));
    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 'sp1', 'f1'));
    await waitFor(() => expect(screen.queryByRole('link', { name: /Cheat sheet/ })).not.toBeInTheDocument());
  });
});

describe('course material helpers', () => {
  it('formats sizes and checks file names (#1336)', () => {
    expect(formatFileSize(950)).toBe('950 B');
    expect(formatFileSize(12 * 1024)).toBe('12 KB');
    expect(formatFileSize(3.4 * 1024 * 1024)).toBe('3.4 MB');
    expect(isCourseMaterialFileName('Guide.PDF')).toBe(true);
    expect(isCourseMaterialFileName('archive.tar.zip')).toBe(true);
    expect(isCourseMaterialFileName('movie.mp4')).toBe(false);
    expect(isCourseMaterialFileName('noextension')).toBe(false);
  });
});
