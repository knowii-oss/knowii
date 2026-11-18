import { describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { ModeratorPermissionsForm } from './ModeratorPermissionsForm';

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: '',
  visibility: 'public',
  moderator_permissions: { manage_spaces: false, manage_members: false, pin_posts: true, delete_content: true },
  created_at: new Date(),
  updated_at: new Date(),
} as Community;

function renderForm() {
  render(
    <AppDataProvider>
      <ModeratorPermissionsForm community={community} />
    </AppDataProvider>,
  );
}

describe('ModeratorPermissionsForm', () => {
  it('shows the current rights, each with its description (#1240)', () => {
    renderForm();

    expect(screen.getByRole('checkbox', { name: /Manage spaces/ })).not.toBeChecked();
    expect(screen.getByRole('checkbox', { name: /Pin posts/ })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /Delete content/ })).toHaveAccessibleDescription(
      'Delete the posts and comments of other members',
    );
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
  });

  it('saves the switched rights with the keyboard (#1240)', async () => {
    const saved = { ...community, moderator_permissions: { ...community.moderator_permissions, manage_spaces: true } };
    const update = vi
      .spyOn(knowiiApiClient.communities, 'updateModeratorPermissions')
      .mockResolvedValue({ ok: true, message: '', data: saved });
    renderForm();

    screen.getByRole('checkbox', { name: /Manage spaces/ }).focus();
    await userEvent.keyboard(' ');
    const save = screen.getByRole('button', { name: 'Save' });
    expect(save).toBeEnabled();
    act(() => save.focus());
    await userEvent.keyboard('{Enter}');

    await waitFor(() =>
      expect(update).toHaveBeenCalledWith('c1', { manage_spaces: true, manage_members: false, pin_posts: true, delete_content: true }),
    );
    await waitFor(() => expect(save).not.toHaveAttribute('aria-busy'));
    // Nothing to save any more: Save keeps the focus with aria-disabled (#1618). jsdom does not always drop the focus of
    // a disabled button, Chrome does: the attributes are the proof.
    expect(save).toHaveFocus();
    expect(save).toHaveAttribute('aria-disabled', 'true');
    expect(save).not.toHaveAttribute('disabled');
    await userEvent.keyboard('{Enter}');
    expect(update).toHaveBeenCalledOnce();

    // The focus leaves: Save is really disabled
    await userEvent.tab({ shift: true });
    expect(screen.getByRole('checkbox', { name: /Delete content/ })).toHaveFocus();
    expect(save).toBeDisabled();
  });
});
