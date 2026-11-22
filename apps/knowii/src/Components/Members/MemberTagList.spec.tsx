import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import axe from 'axe-core';
import type { MemberTag } from '@knowii/common';
import { MemberTagList } from './MemberTagList';

const tag = (cuid: string, name: string, changes: Partial<MemberTag> = {}): MemberTag => ({
  cuid,
  name,
  color: 'gray',
  visibility: 'members',
  members_count: null,
  created_at: '2026-11-22T10:00:00.000000Z',
  ...changes,
});

describe('MemberTagList (#1389)', () => {
  it('renders no list without a tag', () => {
    const { container } = render(<MemberTagList tags={[]} />);

    expect(container).toBeEmptyDOMElement();
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('lists the tags in their order, in a list named "Tags", each in its color', () => {
    render(<MemberTagList tags={[tag('a', 'Founder', { color: 'purple' }), tag('b', 'Speaker', { color: 'orange' })]} />);

    const list = screen.getByRole('list', { name: 'Tags' });
    expect(
      within(list)
        .getAllByRole('listitem')
        .map((item) => item.textContent),
    ).toEqual(['Founder', 'Speaker']);
    expect(within(list).getByText('Founder').parentElement).toHaveClass('border-tag-purple');
  });

  it('marks a tag of the managers with a lock and the hidden words "managers only"', () => {
    render(<MemberTagList tags={[tag('a', 'Onboarded', { visibility: 'managers' }), tag('b', 'Founder')]} />);

    const [onboarded, founder] = screen.getAllByRole('listitem');
    expect(onboarded).toHaveTextContent('Onboarded (managers only)');
    expect(within(onboarded as HTMLElement).getByText('(managers only)')).toHaveClass('sr-only');
    expect(onboarded?.querySelector('svg')?.closest('[aria-hidden="true"]')).not.toBeNull();
    expect(onboarded).toHaveAttribute('title', 'Only the managers see this tag');
    expect(founder).toHaveTextContent(/^Founder$/);
    expect(founder?.querySelector('svg')).toBeNull();
  });

  it('has no automated violation (axe)', async () => {
    const { container } = render(<MemberTagList tags={[tag('a', 'Onboarded', { visibility: 'managers' }), tag('b', 'Founder')]} />);
    expect((await axe.run(container)).violations.map((violation) => violation.id)).toEqual([]);
  });
});
