import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import { useState } from 'react';
import type { CommunityMember, MemberTag } from '@knowii/common';
import { knowiiApiClient } from '@knowii/common';
import { MemberTagsDialog } from './MemberTagsDialog';

const tag = (cuid: string, name: string, changes: Partial<MemberTag> = {}): MemberTag => ({
  cuid,
  name,
  color: 'gray',
  visibility: 'managers',
  members_count: 1,
  created_at: '2026-11-22T10:00:00.000000Z',
  ...changes,
});

const founder = tag('t1', 'Founder', { color: 'purple', visibility: 'members' });
const onboarded = tag('t2', 'Onboarded');

const ursula: CommunityMember = {
  cuid: 'u',
  name: 'Ursula',
  username: 'ursula',
  headline: '',
  role: 'member',
  member_since: null,
  tags: [{ ...founder, members_count: null }],
  in_member_list: true,
};

const author = { cuid: 'u', name: 'Ursula', username: 'ursula', avatar_url: null };

/**
 * A button that opens the dialog, as the member list does
 */
function Harness({ onChange }: { onChange: (memberCuid: string, tags: MemberTag[]) => void }) {
  const [member, setMember] = useState<CommunityMember | null>(null);
  return (
    <>
      <button type="button" onClick={() => setMember(ursula)}>
        Tags of Ursula
      </button>
      <MemberTagsDialog communityCuid="c1" member={member} onClose={() => setMember(null)} onChange={onChange} />
    </>
  );
}

async function openDialog(onChange = vi.fn()) {
  render(<Harness onChange={onChange} />);
  await userEvent.click(screen.getByRole('button', { name: 'Tags of Ursula' }));
  const dialog = await screen.findByRole('dialog', { name: 'Tags of Ursula' });
  await within(dialog).findByRole('checkbox', { name: /Founder/ });
  return { dialog, onChange };
}

beforeEach(() => {
  vi.restoreAllMocks();
  vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({ ok: true, message: '', data: [founder, onboarded] });
});

describe('MemberTagsDialog (#1389)', () => {
  it('has one box per tag of the community, checked for the tags of the member', async () => {
    const { dialog } = await openDialog();

    expect(within(dialog).getByRole('checkbox', { name: /Founder/ })).toBeChecked();
    expect(within(dialog).getByRole('checkbox', { name: /Onboarded/ })).not.toBeChecked();
    expect(within(dialog).getByRole('checkbox', { name: /Onboarded/ })).toHaveAccessibleName(/^Onboarded,\s?Managers only$/);
  });

  it('saves each change at once and says "Saved"', async () => {
    const add = vi.spyOn(knowiiApiClient.communities.memberTags.members, 'add').mockResolvedValue({ ok: true, message: '', data: author });
    const remove = vi
      .spyOn(knowiiApiClient.communities.memberTags.members, 'remove')
      .mockResolvedValue({ ok: true, message: '', data: undefined });
    const { dialog, onChange } = await openDialog();

    await userEvent.click(within(dialog).getByRole('checkbox', { name: /Onboarded/ }));
    await waitFor(() => expect(add).toHaveBeenCalledWith('c1', 't2', { member: 'u' }));
    expect(await within(dialog).findByRole('status')).toHaveTextContent('Saved');
    expect(onChange).toHaveBeenLastCalledWith('u', [
      expect.objectContaining({ cuid: 't1' }),
      expect.objectContaining({ cuid: 't2', members_count: null }),
    ]);

    await userEvent.click(within(dialog).getByRole('checkbox', { name: /Founder/ }));
    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 't1', 'u'));
    await waitFor(() => expect(onChange).toHaveBeenLastCalledWith('u', [expect.objectContaining({ cuid: 't2' })]));
  });

  it('puts the box back and says why when the save fails', async () => {
    vi.spyOn(knowiiApiClient.communities.memberTags.members, 'add').mockResolvedValue({
      ok: false,
      status: 422,
      message: 'A member can have at most 20 tags',
      errors: {},
    });
    const { dialog, onChange } = await openDialog();

    const box = within(dialog).getByRole('checkbox', { name: /Onboarded/ });
    await userEvent.click(box);

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('A member can have at most 20 tags');
    expect(box).not.toBeChecked();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('gives the focus back to the button that opened it', async () => {
    const { dialog } = await openDialog();

    await userEvent.click(within(dialog).getByRole('button', { name: 'Done' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.getByRole('button', { name: 'Tags of Ursula' })).toHaveFocus();
  });

  it('says where the tags are created when the community has none', async () => {
    vi.spyOn(knowiiApiClient.communities.memberTags, 'list').mockResolvedValue({ ok: true, message: '', data: [] });
    render(<Harness onChange={vi.fn()} />);
    await userEvent.click(screen.getByRole('button', { name: 'Tags of Ursula' }));

    const dialog = await screen.findByRole('dialog', { name: 'Tags of Ursula' });
    expect(await within(dialog).findByText(/This community has no member tags yet/)).toHaveTextContent(
      'section "Member tags" of the settings',
    );
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument();
  });

  it('has no automated violation (axe)', async () => {
    await openDialog();

    const results = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });
    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
