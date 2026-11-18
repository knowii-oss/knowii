import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { SwitchProps } from './Switch';
import { Switch } from './Switch';

function ControlledSwitch({ onChange, checked: checkedAtStart = false, ...props }: Partial<SwitchProps>) {
  const [checked, setChecked] = useState(checkedAtStart);
  return (
    <Switch
      label="Show pop-ups"
      description="A short message when something new needs you."
      {...props}
      checked={checked}
      onChange={(next) => {
        setChecked(next);
        onChange?.(next);
      }}
    />
  );
}

describe('Switch (#1569)', () => {
  it('is a switch named by its label and described by its description', () => {
    render(<ControlledSwitch />);

    const toggle = screen.getByRole('switch', { name: 'Show pop-ups' });
    expect(toggle).not.toBeChecked();
    expect(toggle).toHaveAccessibleDescription('A short message when something new needs you.');
    expect(toggle).toHaveAttribute('aria-describedby');
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  });

  it('turns on and off with Space, a click on it and a click on its label', async () => {
    const onChange = vi.fn();
    render(<ControlledSwitch onChange={onChange} />);
    const toggle = screen.getByRole('switch', { name: 'Show pop-ups' });

    await userEvent.tab();
    expect(toggle).toHaveFocus();
    await userEvent.keyboard(' ');
    expect(toggle).toBeChecked();

    await userEvent.click(toggle);
    expect(toggle).not.toBeChecked();

    await userEvent.click(screen.getByText('Show pop-ups'));
    expect(toggle).toBeChecked();
    expect(onChange.mock.calls).toEqual([[true], [false], [true]]);
  });

  it('keeps the focus while it is busy, and ignores a click and Space', async () => {
    const onChange = vi.fn();
    render(<ControlledSwitch busy onChange={onChange} />);
    const toggle = screen.getByRole('switch', { name: 'Show pop-ups' });

    toggle.focus();
    await userEvent.keyboard(' ');
    await userEvent.click(toggle);
    await userEvent.click(screen.getByText('Show pop-ups'));

    expect(onChange).not.toHaveBeenCalled();
    expect(toggle).not.toBeChecked();
    expect(toggle).toHaveFocus();
    expect(toggle).toBeEnabled();
    expect(toggle).toHaveAttribute('aria-busy', 'true');
    expect(toggle).toHaveAttribute('aria-disabled', 'true');
  });

  it('is out of the tab order when it is disabled', async () => {
    const onChange = vi.fn();
    render(<ControlledSwitch disabled onChange={onChange} />);
    const toggle = screen.getByRole('switch', { name: 'Show pop-ups' });

    await userEvent.tab();
    await userEvent.click(toggle);

    expect(toggle).toBeDisabled();
    expect(toggle).not.toHaveFocus();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('shows the status below the description', () => {
    render(<ControlledSwitch status={<p role="status">Saved</p>} />);

    expect(screen.getByRole('status')).toHaveTextContent('Saved');
  });

  it('has a target of 44 px on a phone, the size of the track from md', () => {
    render(<ControlledSwitch />);

    const toggle = screen.getByRole('switch', { name: 'Show pop-ups' });
    expect(toggle).toHaveClass('h-11', 'w-11', 'md:h-6');
  });

  it('forwards the ref to the button', () => {
    const ref = { current: null as HTMLButtonElement | null };
    render(<Switch label="Show pop-ups" checked={false} onChange={vi.fn()} ref={ref} />);

    expect(ref.current).toBe(screen.getByRole('switch', { name: 'Show pop-ups' }));
  });
});
