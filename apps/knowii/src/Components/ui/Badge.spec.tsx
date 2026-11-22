import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import axe from 'axe-core';
import { FaLock } from 'react-icons/fa6';
import { BADGE_COLORS, Badge } from './Badge';

describe('Badge', () => {
  it('colors the border with the variant, without a mark', () => {
    render(<Badge variant="danger">Cancelled</Badge>);

    const badge = screen.getByText('Cancelled').parentElement;
    expect(badge).toHaveClass('border-danger-text', 'text-primary');
    expect(badge?.querySelector('[data-badge-mark]')).toBeNull();
  });

  it.each(BADGE_COLORS)('takes the color %s for its border and its mark, and keeps the text in the color of all text (#1389)', (color) => {
    render(<Badge color={color}>Founder</Badge>);

    const badge = screen.getByText('Founder').parentElement;
    expect(badge).toHaveClass(`border-tag-${color}`, 'text-primary');
    expect(badge?.className).not.toMatch(/border-border-strong/);
    const mark = badge?.querySelector('[data-badge-mark]');
    expect(mark).toHaveClass(`bg-tag-${color}`);
    expect(mark).toHaveAttribute('aria-hidden', 'true');
    expect(badge).toHaveTextContent(/^Founder$/);
  });

  it('puts the mark of the color before an icon, and gives the icon the color of secondary text (#1389)', () => {
    render(
      <Badge color="teal" icon={<FaLock />}>
        Onboarded
      </Badge>,
    );

    const badge = screen.getByText('Onboarded').parentElement;
    const [mark, icon] = Array.from(badge?.querySelectorAll('[aria-hidden="true"]') ?? []);
    expect(mark).toHaveAttribute('data-badge-mark');
    expect(icon).toHaveClass('text-muted');
    expect(icon?.querySelector('svg')).not.toBeNull();
  });

  it('has no automated violation (axe), with every color', async () => {
    const { container } = render(
      <p>
        {BADGE_COLORS.map((color) => (
          <Badge key={color} color={color}>
            {color}
          </Badge>
        ))}
      </p>,
    );
    expect((await axe.run(container)).violations.map((violation) => violation.id)).toEqual([]);
  });
});
