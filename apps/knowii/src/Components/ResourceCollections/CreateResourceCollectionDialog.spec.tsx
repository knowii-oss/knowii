import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import CreateResourceCollectionDialog from './CreateResourceCollectionDialog';

function renderDialog() {
  const onHide = vi.fn();
  const onCreated = vi.fn();
  render(
    <AppDataProvider>
      <CreateResourceCollectionDialog
        settings={{ visible: true, communityCuid: 'c1' }}
        onHide={onHide}
        onResourceCollectionCreated={onCreated}
      />
    </AppDataProvider>,
  );
  return { onHide, onCreated };
}

describe('CreateResourceCollectionDialog', () => {
  afterEach(() => vi.restoreAllMocks());

  it('shows a taken name on the name field and keeps the dialog open (#925)', async () => {
    const user = userEvent.setup();
    const create = vi.spyOn(knowiiApiClient.communities.resourceCollections, 'create').mockResolvedValue({
      category: 'business',
      type: 'businessIssue',
      message: 'This community already has a resource collection with this name',
      metadata: null,
      data: null,
      errors: null,
    } as unknown as Awaited<ReturnType<typeof knowiiApiClient.communities.resourceCollections.create>>);
    const { onHide, onCreated } = renderDialog();

    await user.type(screen.getByRole('textbox', { name: /name/i }), 'Reading list');
    await user.keyboard('{Enter}');

    await waitFor(() => expect(screen.getByText('This community already has a resource collection with this name')).toBeInTheDocument(), {
      timeout: 3000,
    });
    expect(create).toHaveBeenCalledWith(expect.objectContaining({ communityCuid: 'c1', name: 'Reading list' }));
    expect(screen.getByRole('textbox', { name: /name/i })).toHaveFocus();
    expect(screen.getByRole('textbox', { name: /name/i })).toHaveAttribute('aria-invalid', 'true');
    expect(onHide).not.toHaveBeenCalled();
    expect(onCreated).not.toHaveBeenCalled();
  });
});
