import { describe, expect, it } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { useState } from 'react';
import type { InvitationAccessGroup, MemberTag } from '@knowii/common';
import type { InvitationGrants, InvitationGrantsPickerProps } from './InvitationGrantsPicker';
import { InvitationGrantsPicker, NO_INVITATION_GRANTS } from './InvitationGrantsPicker';
import { INVITATION_WORDS } from './invitation-words';

const tag = (cuid: string, name: string, visibility: MemberTag['visibility'] = 'members'): MemberTag => ({
  cuid,
  name,
  color: 'teal',
  visibility,
  members_count: null,
  created_at: '2026-11-22T10:00:00.000000Z',
});

const groups: InvitationAccessGroup[] = [
  { cuid: 'g2', name: 'paid' },
  { cuid: 'g1', name: 'Alumni' },
];
const tags: MemberTag[] = [tag('t2', 'Onboarded', 'managers'), tag('t1', 'Founder')];

/**
 * The picker with its state, as a form holds it
 */
function Harness(
  props: Partial<InvitationGrantsPickerProps> & { initial?: InvitationGrants; onValue?: (value: InvitationGrants) => void },
) {
  const { initial = NO_INVITATION_GRANTS, onValue, ...rest } = props;
  const [value, setValue] = useState<InvitationGrants>(initial);
  return (
    <InvitationGrantsPicker
      accessGroups={groups}
      memberTags={tags}
      canGiveAccessGroups
      canGiveMemberTags
      value={value}
      onChange={(next) => {
        setValue(next);
        onValue?.(next);
      }}
      {...rest}
    />
  );
}

describe('InvitationGrantsPicker (#1390)', () => {
  it('opens a dialog with both fieldsets, by name; a tag of the managers says so to screen readers', async () => {
    render(<Harness />);

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.pickerButton }));
    const dialog = await screen.findByRole('dialog', { name: INVITATION_WORDS.pickerTitle });

    const groupSet = within(dialog).getByRole('group', { name: INVITATION_WORDS.pickerAccessGroups });
    expect(
      within(groupSet)
        .getAllByRole('checkbox')
        .map((box) => box.closest('label')?.textContent),
    ).toEqual(['Alumni', 'paid']);
    const tagSet = within(dialog).getByRole('group', { name: INVITATION_WORDS.pickerMemberTags });
    expect(within(tagSet).getByRole('checkbox', { name: 'Founder' })).toBeInTheDocument();
    expect(within(tagSet).getByRole('checkbox', { name: /^Onboarded ?\(managers only\)$/ })).toBeInTheDocument();

    const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations).toEqual([]);
  });

  it('counts the choice on its button, and Done puts the focus back on the button', async () => {
    let last: InvitationGrants = NO_INVITATION_GRANTS;
    render(<Harness onValue={(value) => (last = value)} />);

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.pickerButton }));
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Alumni' }));
    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Founder' }));
    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Alumni' }));
    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'paid' }));
    await userEvent.click(within(dialog).getByRole('button', { name: INVITATION_WORDS.pickerDone }));

    expect(last).toEqual({ accessGroups: ['g2'], memberTags: ['t1'] });
    const button = await screen.findByRole('button', { name: INVITATION_WORDS.pickerButtonWithCount(2) });
    await waitFor(() => expect(button).toHaveFocus());
  });

  it('shows one fieldset alone when the reader may give only that', async () => {
    render(<Harness canGiveAccessGroups={false} />);

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.pickerButton }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).queryByRole('group', { name: INVITATION_WORDS.pickerAccessGroups })).not.toBeInTheDocument();
    expect(within(dialog).getByRole('group', { name: INVITATION_WORDS.pickerMemberTags })).toBeInTheDocument();
  });

  it('shows nothing when there is nothing that the reader may give', () => {
    const { container } = render(<Harness canGiveMemberTags={false} accessGroups={[]} />);

    expect(container).toBeEmptyDOMElement();
  });

  it('holds at most 20 tags: the 21st cannot be checked, and a polite status says why', async () => {
    const many = Array.from({ length: 21 }, (_, index) => tag(`m${index}`, `Tag ${String(index).padStart(2, '0')}`));
    render(<Harness memberTags={many} initial={{ accessGroups: [], memberTags: many.slice(0, 19).map((item) => item.cuid) }} />);

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.pickerButtonWithCount(19) }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByRole('status')).toHaveTextContent('');

    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Tag 19' }));
    expect(within(dialog).getByRole('status')).toHaveTextContent(INVITATION_WORDS.tagLimit(20));

    const last = within(dialog).getByRole('checkbox', { name: 'Tag 20' });
    expect(last).toHaveAttribute('aria-disabled', 'true');
    await userEvent.click(last);
    expect(last).not.toBeChecked();

    // A box that is checked can still be cleared, and the 21st can then be checked
    await userEvent.click(within(dialog).getByRole('checkbox', { name: 'Tag 00' }));
    await userEvent.click(last);
    expect(last).toBeChecked();
  });

  it('gives the reason next to a group that cannot be chosen (#1391), and the box is off', async () => {
    render(<Harness accessGroupRefusal={(group) => (group.cuid === 'g2' ? 'A rule gives this group' : null)} />);

    await userEvent.click(screen.getByRole('button', { name: INVITATION_WORDS.pickerButton }));
    const dialog = await screen.findByRole('dialog');
    const box = within(dialog).getByRole('checkbox', { name: 'paid' });
    expect(box).toBeDisabled();
    expect(box).toHaveAccessibleDescription('A rule gives this group');
  });
});
