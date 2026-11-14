import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import CreateCommunityDialog from './CreateCommunityDialog';

vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

function renderDialog() {
  render(
    <AppDataProvider>
      <CreateCommunityDialog visible onHide={vi.fn()} onCommunityCreated={vi.fn()} />
    </AppDataProvider>,
  );
}

describe('CreateCommunityDialog', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('checks the name once after the typing pauses and says when it is available (#1136)', async () => {
    const check = vi
      .spyOn(knowiiApiClient.communities, 'isNameAvailable')
      .mockResolvedValue({ ok: true, message: '', data: { available: true } });
    renderDialog();

    await userEvent.type(screen.getByLabelText('Name'), 'Gardeners');

    expect(await screen.findByText('This name is available.')).toBeInTheDocument();
    expect(check).toHaveBeenCalledTimes(1);
    expect(check).toHaveBeenCalledWith({ name: 'Gardeners' }, expect.any(AbortSignal));
    expect(screen.getByRole('button', { name: 'Go ahead!' })).toBeEnabled();
  });

  it('blocks the creation while the name is taken (#1136)', async () => {
    vi.spyOn(knowiiApiClient.communities, 'isNameAvailable').mockResolvedValue({ ok: true, message: '', data: { available: false } });
    renderDialog();

    await userEvent.type(screen.getByLabelText('Name'), 'Knowledge Builders');

    expect(await screen.findByText('This name is already taken. Choose another one.')).toBeInTheDocument();
    expect(screen.getByLabelText('Name')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByLabelText('Name')).toHaveAttribute('aria-describedby', 'name-status');
    expect(screen.getByRole('button', { name: 'Go ahead!' })).toBeDisabled();
  });

  it('does not check a name that breaks the rules, or the name of a personal community (#1136)', async () => {
    const check = vi
      .spyOn(knowiiApiClient.communities, 'isNameAvailable')
      .mockResolvedValue({ ok: true, message: '', data: { available: false } });
    renderDialog();

    await userEvent.type(screen.getByLabelText('Name'), 'ab');
    await userEvent.click(screen.getByRole('radio', { name: /Personal/ }));
    await userEvent.type(screen.getByLabelText('Name'), 'c');
    await new Promise((resolve) => setTimeout(resolve, 400));

    expect(check).not.toHaveBeenCalled();
    expect(screen.queryByText(/already taken/)).not.toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Go ahead!' })).toBeEnabled());
  });

  it('lets the user create the community when the check fails: the server checks again (#1136)', async () => {
    vi.spyOn(knowiiApiClient.communities, 'isNameAvailable').mockResolvedValue({ ok: false, status: 429, message: 'Too many', errors: {} });
    renderDialog();

    await userEvent.type(screen.getByLabelText('Name'), 'Gardeners');

    await waitFor(() => expect(screen.getByRole('button', { name: 'Go ahead!' })).toBeEnabled());
    expect(screen.queryByText('Checking the name…')).not.toBeInTheDocument();
  });

  it('tells a shared community that the owner accepts the DPA, and a personal one nothing (#1353)', async () => {
    renderDialog();

    expect(screen.getByText(/By creating a community, you accept the/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'data processing agreement' })).toHaveAttribute('href', '/dpa.show');

    await userEvent.click(screen.getByRole('radio', { name: /Personal/ }));

    expect(screen.queryByText(/you accept the/)).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'data processing agreement' })).not.toBeInTheDocument();
  });
});
