import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TextInput } from './TextInput';
import { Textarea } from './Textarea';
import { PasswordInput, getPasswordStrength } from './PasswordInput';
import { Checkbox } from './Checkbox';
import { SegmentedControl } from './SegmentedControl';

function ControlledInput(props: { allowedCharacters?: RegExp }) {
  const [value, setValue] = useState('');
  return (
    <>
      <label htmlFor="field">Phone</label>
      <TextInput id="field" value={value} onChange={(e) => setValue(e.target.value)} allowedCharacters={props.allowedCharacters} />
    </>
  );
}

describe('TextInput', () => {
  it('is named by its label and forwards the ref', () => {
    const ref = { current: null as HTMLInputElement | null };
    render(
      <>
        <label htmlFor="email">Email</label>
        <TextInput id="email" type="email" ref={ref} />
      </>,
    );
    const input = screen.getByRole('textbox', { name: 'Email' });
    expect(ref.current).toBe(input);
  });

  it('marks the field as invalid', () => {
    render(<TextInput aria-label="Name" invalid />);
    expect(screen.getByRole('textbox', { name: 'Name' })).toHaveAttribute('aria-invalid', 'true');
  });

  it('hides the decorative icon from assistive technology', () => {
    render(<TextInput aria-label="Name" icon={<svg data-testid="icon" />} />);
    expect(screen.getByTestId('icon').parentElement).toHaveAttribute('aria-hidden', 'true');
  });

  it('blocks the characters that do not match allowedCharacters', async () => {
    render(<ControlledInput allowedCharacters={/^[0-9+ ]+$/} />);
    const input = screen.getByRole('textbox', { name: 'Phone' });
    await userEvent.type(input, '+32 4a7b5');
    expect(input).toHaveValue('+32 475');
  });

  it('accepts any character without allowedCharacters', async () => {
    render(<ControlledInput />);
    const input = screen.getByRole('textbox', { name: 'Phone' });
    await userEvent.type(input, 'abc 1');
    expect(input).toHaveValue('abc 1');
  });
});

describe('Textarea', () => {
  it('renders a labelled multi-line field', async () => {
    const onChange = vi.fn();
    render(<Textarea aria-label="Bio" onChange={onChange} />);
    await userEvent.type(screen.getByRole('textbox', { name: 'Bio' }), 'Hi');
    expect(onChange).toHaveBeenCalledTimes(2);
  });
});

describe('PasswordInput', () => {
  function ControlledPassword(props: { showStrength?: boolean }) {
    const [value, setValue] = useState('');
    return (
      <>
        <label htmlFor="password">Password</label>
        <PasswordInput id="password" value={value} onChange={(e) => setValue(e.target.value)} showStrength={props.showStrength ?? false} />
      </>
    );
  }

  it('toggles the visibility of the password', async () => {
    render(<ControlledPassword />);
    const input = screen.getByLabelText('Password');
    expect(input).toHaveAttribute('type', 'password');

    const toggle = screen.getByRole('button', { name: 'Show password' });
    expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await userEvent.click(toggle);

    expect(input).toHaveAttribute('type', 'text');
    expect(screen.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true');
  });

  it('shows a strength hint linked to the field', async () => {
    render(<ControlledPassword showStrength />);
    const input = screen.getByLabelText('Password');
    expect(screen.queryByText('Too simple')).not.toBeInTheDocument();

    await userEvent.type(input, 'abc');
    expect(screen.getByText('Too simple')).toBeInTheDocument();
    expect(input).toHaveAttribute('aria-describedby', 'password-strength');

    await userEvent.type(input, 'DEF123');
    expect(screen.getByText('Complex password')).toBeInTheDocument();
  });

  it('estimates the strength', () => {
    expect(getPasswordStrength('abc')).toBe('weak');
    expect(getPasswordStrength('abcdef1')).toBe('medium');
    expect(getPasswordStrength('abcDEF12')).toBe('strong');
  });
});

describe('Checkbox', () => {
  it('toggles with the keyboard and keeps its label', async () => {
    const onChange = vi.fn();
    render(
      <label>
        <Checkbox onChange={onChange} />
        Remember me
      </label>,
    );
    const checkbox = screen.getByRole('checkbox', { name: 'Remember me' });
    await userEvent.tab();
    expect(checkbox).toHaveFocus();
    await userEvent.keyboard(' ');
    expect(checkbox).toBeChecked();
    expect(onChange).toHaveBeenCalledOnce();
  });
});

describe('SegmentedControl', () => {
  const options = [
    { value: 'public', label: 'Public' },
    { value: 'private', label: 'Private' },
    { value: 'personal', label: 'Personal' },
  ] as const;

  function ControlledSegmentedControl() {
    const [value, setValue] = useState<'public' | 'private' | 'personal'>('public');
    return <SegmentedControl aria-label="Visibility" options={options} value={value} onChange={setValue} />;
  }

  it('is a radio group with one checked option', () => {
    render(<ControlledSegmentedControl />);
    expect(screen.getByRole('radiogroup', { name: 'Visibility' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Public' })).toBeChecked();
    expect(screen.getByRole('radio', { name: 'Private' })).not.toBeChecked();
  });

  it('changes the value on click and with the arrow keys', async () => {
    render(<ControlledSegmentedControl />);
    await userEvent.click(screen.getByText('Private'));
    expect(screen.getByRole('radio', { name: 'Private' })).toBeChecked();

    await userEvent.keyboard('{ArrowRight}');
    expect(screen.getByRole('radio', { name: 'Personal' })).toBeChecked();
  });
});
