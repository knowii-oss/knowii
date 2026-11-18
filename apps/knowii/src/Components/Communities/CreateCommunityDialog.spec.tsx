import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import type { Community } from '@knowii/common';
import { AppDataProvider, COMMUNITY_VISIBILITY_DESCRIPTIONS, knowiiApiClient } from '@knowii/common';
import { Toaster } from '@/Components/ui';
import CreateCommunityDialog from './CreateCommunityDialog';

vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, params?: Record<string, string>) => (params ? `/${name}/${Object.values(params).join('/')}` : `/${name}`),
}));

function renderDialog() {
  const onHide = vi.fn();
  const onCommunityCreated = vi.fn();
  render(
    <AppDataProvider>
      <Toaster />
      <CreateCommunityDialog visible onHide={onHide} onCommunityCreated={onCommunityCreated} />
    </AppDataProvider>,
  );
  return { onHide, onCommunityCreated };
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

  it('selects "Private" first and shows the approved text of the selected choice under the switch (#1576)', async () => {
    renderDialog();

    expect(screen.getByRole('radio', { name: /Private/ })).toBeChecked();
    const group = screen.getByRole('radiogroup', { name: 'Community visibility' });
    expect(group).toHaveAccessibleDescription(COMMUNITY_VISIBILITY_DESCRIPTIONS.private);

    await userEvent.click(screen.getByRole('radio', { name: /Public/ }));
    expect(group).toHaveAccessibleDescription(COMMUNITY_VISIBILITY_DESCRIPTIONS.public);
    expect(screen.queryByText(COMMUNITY_VISIBILITY_DESCRIPTIONS.private)).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('radio', { name: /Personal/ }));
    expect(group).toHaveAccessibleDescription(COMMUNITY_VISIBILITY_DESCRIPTIONS.personal);
  });

  it('says why the owner accepts the DPA, before the link (#1576)', () => {
    renderDialog();

    const line = screen.getByText('You will hold the data of your members; this agreement says how.');
    const link = screen.getByRole('link', { name: 'data processing agreement' });
    expect(line.compareDocumentPosition(link) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(line).not.toContainElement(link);
  });

  it('opens the new community after the create, and shows the toast there (#1576)', async () => {
    vi.spyOn(knowiiApiClient.communities, 'isNameAvailable').mockResolvedValue({ ok: true, message: '', data: { available: true } });
    const community = { cuid: 'c1', name: 'Gardeners', slug: 'gardeners', description: '', visibility: 'private' } as Community;
    const create = vi.spyOn(knowiiApiClient.communities, 'create').mockResolvedValue({
      type: 'success',
      category: 'business',
      message: 'Community created',
      data: community,
    } as Awaited<ReturnType<typeof knowiiApiClient.communities.create>>);
    let finishVisit: () => void = () => undefined;
    const visit = vi.spyOn(router, 'visit').mockImplementation((_url, options) => {
      finishVisit = () => options?.onFinish?.({} as never);
    });
    const { onHide, onCommunityCreated } = renderDialog();

    await userEvent.type(screen.getByLabelText('Name'), 'Gardeners');
    await waitFor(() => expect(screen.getByRole('button', { name: 'Go ahead!' })).toBeEnabled());
    // Enter in the name field (jsdom has no implicit submission with a submit button outside the form)
    const nameField = screen.getByLabelText('Name');
    expect(nameField).toHaveFocus();
    const form = nameField.closest('form');
    if (!form) {
      throw new Error('form not found');
    }
    fireEvent.submit(form);

    await waitFor(() => expect(visit).toHaveBeenCalledWith('/communities.show/gardeners', expect.anything()));
    expect(create).toHaveBeenCalledWith({ name: 'Gardeners', description: '', visibility: 'private' });
    expect(onCommunityCreated).toHaveBeenCalledWith(community);
    // Busy until the page is there: the focus is on the busy button, not lost with the form
    const submit = screen.getByRole('button', { name: /Go ahead!/ });
    expect(submit).toHaveAttribute('aria-busy', 'true');
    expect(submit).toHaveFocus();
    expect(onHide).not.toHaveBeenCalled();
    expect(screen.queryByText('Community created successfully')).not.toBeInTheDocument();

    finishVisit();

    expect(await screen.findByText('Community created successfully')).toBeInTheDocument();
    expect(onHide).toHaveBeenCalledOnce();
  });

  it('closes at once when only the visibility changed, and asks once there is text (#1563)', async () => {
    const { onHide } = renderDialog();

    await userEvent.click(screen.getByRole('radio', { name: /Public/ }));
    await userEvent.keyboard('{Escape}');
    expect(onHide).toHaveBeenCalledOnce();
    expect(screen.queryByText('Discard your text?')).not.toBeInTheDocument();
  });

  it('asks before Escape or "Cancel" lose the text (#1563)', async () => {
    const { onHide } = renderDialog();

    await userEvent.type(screen.getByLabelText('Description'), 'A place to grow');
    await userEvent.keyboard('{Escape}');
    expect(await screen.findByText('Discard your text?')).toBeInTheDocument();
    expect(onHide).not.toHaveBeenCalled();

    await userEvent.click(screen.getByRole('button', { name: 'Keep writing' }));
    await waitFor(() => expect(screen.queryByText('Discard your text?')).not.toBeInTheDocument());
    expect(screen.getByLabelText('Description')).toHaveValue('A place to grow');

    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Discard' }));
    expect(onHide).toHaveBeenCalledOnce();
  });
});
