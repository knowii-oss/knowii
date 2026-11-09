import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { CommunityResource, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { CreateResourceDialog } from './CreateResourceDialog';

const space = { cuid: 's1', name: 'Cool links', slug: 'cool-links', type: 'resources' } as Space;

function renderDialog(onResourceCreated = vi.fn(), onClose = vi.fn()) {
  render(
    <AppDataProvider>
      <CreateResourceDialog open communityCuid="c1" space={space} onClose={onClose} onResourceCreated={onResourceCreated} />
    </AppDataProvider>,
  );
  return { onResourceCreated, onClose };
}

async function fill(url: string, name: string) {
  await userEvent.type(screen.getByLabelText('Link'), url);
  if (name !== '') {
    await userEvent.type(screen.getByLabelText('Name in this community (optional)'), name);
  }
}

describe('CreateResourceDialog', () => {
  it('starts on the link field and names the space', () => {
    renderDialog();

    expect(screen.getByLabelText('Link')).toHaveFocus();
    expect(screen.getByRole('dialog')).toHaveTextContent('Share a link in Cool links.');
  });

  it('refuses an address that is not a web page', async () => {
    const create = vi.spyOn(knowiiApiClient.resources, 'create');
    renderDialog();

    await fill('ftp://example.com/file', 'Some article');
    await userEvent.click(screen.getByRole('button', { name: 'Add' }));

    expect(await screen.findByText('Enter the full address of a web page, starting with https://')).toBeInTheDocument();
    expect(create).not.toHaveBeenCalled();
  });

  it('adds the resource and closes', async () => {
    const created = { cuid: 'r1', name: 'Some article' } as CommunityResource;
    const create = vi.spyOn(knowiiApiClient.resources, 'create').mockResolvedValue({ ok: true, message: '', data: created });
    const { onResourceCreated, onClose } = renderDialog();

    await fill('https://example.com/article', 'Some article');
    // The submit button is in the dialog footer (form attribute): jsdom does not submit on Enter, browsers do
    await userEvent.click(screen.getByRole('button', { name: 'Add' }));

    await waitFor(() => expect(onResourceCreated).toHaveBeenCalledWith(created));
    expect(create).toHaveBeenCalledWith('c1', 's1', {
      url: 'https://example.com/article',
      name: 'Some article',
      description: '',
      level: 'unknown',
      type: '',
    });
    expect(onClose).toHaveBeenCalled();
  });

  it('shows the server errors next to their field and stays open', async () => {
    vi.spyOn(knowiiApiClient.resources, 'create').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'The input is not valid',
      errors: { name: ['The name is already used'] },
    });
    const { onClose } = renderDialog();

    await fill('https://example.com/article', 'Some article');
    await userEvent.click(screen.getByRole('button', { name: 'Add' }));

    expect(await screen.findByText('The name is already used')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('resets the form', async () => {
    renderDialog();

    await fill('https://example.com/article', 'Some article');
    await userEvent.click(screen.getByRole('button', { name: 'Reset' }));

    expect(screen.getByLabelText('Link')).toHaveValue('');
    expect(screen.getByLabelText('Name in this community (optional)')).toHaveValue('');
  });

  it('adds a resource without a name: the server uses the title of the page (#1062)', async () => {
    const create = vi
      .spyOn(knowiiApiClient.resources, 'create')
      .mockResolvedValue({ ok: true, message: '', data: { cuid: 'r2', name: 'Page title' } as CommunityResource });
    renderDialog();

    await fill('https://example.com/article', '');
    await userEvent.click(screen.getByRole('button', { name: 'Add' }));

    await waitFor(() => expect(create).toHaveBeenCalledWith('c1', 's1', expect.objectContaining({ name: '' })));
  });
});
