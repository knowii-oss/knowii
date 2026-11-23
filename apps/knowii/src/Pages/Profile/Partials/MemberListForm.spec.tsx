import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { router } from '@inertiajs/react';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import MemberListForm, { MEMBER_LIST_WORDS } from './MemberListForm';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }));

afterEach(() => {
  vi.restoreAllMocks();
});

function renderForm(showInMemberList: boolean) {
  return render(
    <AppDataProvider>
      <MemberListForm showInMemberList={showInMemberList} />
    </AppDataProvider>,
  );
}

describe('MemberListForm (#1466)', () => {
  it('shows the title, the label and the text of the switch, which a screen reader hears with the label', () => {
    renderForm(true);

    expect(screen.getByText('Member list')).toBeInTheDocument();
    expect(screen.getByText('Who finds you in the member list of your communities.')).toBeInTheDocument();
    const toggle = screen.getByRole('switch', { name: 'Show me in the member list of my communities' });
    expect(toggle).toHaveAccessibleDescription(MEMBER_LIST_WORDS.help);
    expect(MEMBER_LIST_WORDS.help).toContain('This is not a privacy wall');
    expect(MEMBER_LIST_WORDS.help).toContain('Your online status has its own switch.');
  });

  it.each([true, false])('starts from the choice of the account (%s)', (shown) => {
    renderForm(shown);

    const toggle = screen.getByRole('switch', { name: MEMBER_LIST_WORDS.label });
    if (shown) {
      expect(toggle).toBeChecked();
    } else {
      expect(toggle).not.toBeChecked();
    }
  });

  it('hides the member at once and says "Saved", then puts the switch back and says the error when the server refuses', async () => {
    const update = vi
      .spyOn(knowiiApiClient.users, 'updateMemberListVisibility')
      .mockResolvedValueOnce({ ok: true, message: '', data: undefined })
      .mockResolvedValueOnce({ ok: false, status: 500, message: 'Failed to change your choice for the member list.', errors: {} });
    renderForm(true);

    const toggle = screen.getByRole('switch', { name: MEMBER_LIST_WORDS.label });
    await userEvent.click(toggle);
    expect(update).toHaveBeenCalledWith(false);
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Saved'));
    expect(toggle).not.toBeChecked();
    expect(toggle).toHaveAttribute('aria-busy', 'false');
    expect(router.reload).toHaveBeenCalledWith({ only: ['auth'] });

    await userEvent.click(toggle);
    expect(update).toHaveBeenLastCalledWith(true);
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Failed to change your choice for the member list.'));
    expect(toggle).not.toBeChecked();
    expect(toggle).toHaveFocus();
  });

  it('has no automated violation (axe)', async () => {
    renderForm(false);

    const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
