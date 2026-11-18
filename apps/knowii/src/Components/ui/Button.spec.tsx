import type { FormEvent } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Button, buttonClasses } from './Button';
import { cn } from './cn';

describe('Button', () => {
  it('is a non-submitting button by default', () => {
    render(<Button>Save</Button>);
    expect(screen.getByRole('button', { name: 'Save' })).toHaveAttribute('type', 'button');
  });

  it('submits the form when type is submit', async () => {
    const onSubmit = vi.fn((event: FormEvent) => event.preventDefault());
    render(
      <form onSubmit={onSubmit}>
        <Button type="submit">Send</Button>
      </form>,
    );
    await userEvent.click(screen.getByRole('button', { name: 'Send' }));
    expect(onSubmit).toHaveBeenCalledOnce();
  });

  it('can be activated with the keyboard', async () => {
    const onClick = vi.fn();
    render(<Button onClick={onClick}>Go</Button>);
    await userEvent.tab();
    expect(screen.getByRole('button', { name: 'Go' })).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await userEvent.keyboard(' ');
    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('is busy, not disabled, while loading, and ignores a click', async () => {
    const onClick = vi.fn();
    render(
      <Button loading onClick={onClick}>
        Save
      </Button>,
    );
    const button = screen.getByRole('button', { name: /Save/ });
    expect(button).toBeEnabled();
    expect(button).toHaveAttribute('aria-disabled', 'true');
    expect(button).toHaveAttribute('aria-busy', 'true');
    expect(screen.getByRole('status')).toHaveTextContent('Loading');
    await userEvent.tab();
    expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await userEvent.keyboard(' ');
    await userEvent.click(button);
    expect(onClick).not.toHaveBeenCalled();
    expect(button).toHaveFocus();
  });

  it('keeps the focus when it starts to load (#1564)', async () => {
    const { rerender } = render(<Button onClick={vi.fn()}>Save</Button>);
    await userEvent.tab();
    const button = screen.getByRole('button', { name: 'Save' });
    expect(button).toHaveFocus();

    rerender(
      <Button loading onClick={vi.fn()}>
        Save
      </Button>,
    );
    // jsdom keeps the focus on a disabled button, Chrome does not: the button must stay enabled
    expect(button).toBeEnabled();
    expect(button).toHaveFocus();
    expect(button).toHaveAttribute('aria-busy', 'true');

    rerender(<Button onClick={vi.fn()}>Save</Button>);
    expect(button).toHaveFocus();
    expect(button).not.toHaveAttribute('aria-busy');
    expect(button).not.toHaveAttribute('aria-disabled');
  });

  it('does not submit its form while loading, with a click or with Enter', async () => {
    const onSubmit = vi.fn((event: FormEvent) => event.preventDefault());
    render(
      <form onSubmit={onSubmit}>
        <label>
          Comment
          <input name="comment" />
        </label>
        <Button type="submit" loading>
          Send
        </Button>
      </form>,
    );
    const button = screen.getByRole('button', { name: /Send/ });
    await userEvent.click(button);
    expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await userEvent.keyboard(' ');
    expect(button).toHaveFocus();
    // Enter in a field of the form clicks the submit button: the click is ignored
    await userEvent.type(screen.getByRole('textbox', { name: 'Comment' }), 'Hello{Enter}');
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('keeps aria-busy and aria-disabled set by the caller', () => {
    render(
      <Button aria-busy aria-disabled>
        Save
      </Button>,
    );
    const button = screen.getByRole('button', { name: 'Save' });
    expect(button).toHaveAttribute('aria-busy', 'true');
    expect(button).toHaveAttribute('aria-disabled', 'true');
  });

  // A form that resets after a send: its submit button becomes disabled while it has the focus (#1618)
  function SendForm({ disabled, onClick, onSubmit }: { disabled: boolean; onClick: () => void; onSubmit: (event: FormEvent) => void }) {
    return (
      <form onSubmit={onSubmit}>
        <label>
          Comment
          <input name="comment" />
        </label>
        <Button type="submit" disabled={disabled} onClick={onClick}>
          Send
        </Button>
        <a href="#next">Next</a>
      </form>
    );
  }

  it('keeps the focus when it becomes disabled while it has the focus, and is disabled after the focus leaves (#1618)', async () => {
    const onClick = vi.fn();
    const onSubmit = vi.fn((event: FormEvent) => event.preventDefault());
    const { rerender } = render(<SendForm disabled={false} onClick={onClick} onSubmit={onSubmit} />);
    const button = screen.getByRole('button', { name: 'Send' });
    await userEvent.tab();
    await userEvent.tab();
    expect(button).toHaveFocus();

    rerender(<SendForm disabled onClick={onClick} onSubmit={onSubmit} />);
    // jsdom does not always drop the focus of a disabled button, Chrome does: the attributes are the proof
    expect(button).not.toHaveAttribute('disabled');
    expect(button).toHaveAttribute('aria-disabled', 'true');
    expect(button).toHaveFocus();

    await userEvent.keyboard('{Enter}');
    await userEvent.keyboard(' ');
    fireEvent.click(button);
    expect(onClick).not.toHaveBeenCalled();
    expect(onSubmit).not.toHaveBeenCalled();
    expect(button).toHaveFocus();
    expect(button).not.toHaveAttribute('disabled');

    // The focus leaves: the button is really disabled, out of the tab order
    await userEvent.tab();
    expect(screen.getByRole('link', { name: 'Next' })).toHaveFocus();
    expect(button).toBeDisabled();
    expect(button).not.toHaveAttribute('aria-disabled');
    await userEvent.tab({ shift: true });
    expect(screen.getByRole('textbox', { name: 'Comment' })).toHaveFocus();
    await userEvent.click(button);
    expect(button).not.toHaveFocus();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('stays focusable while the window does not have the focus', async () => {
    const { rerender } = render(<Button>Save</Button>);
    await userEvent.tab();
    const button = screen.getByRole('button', { name: 'Save' });
    rerender(<Button disabled>Save</Button>);
    // The window loses the focus: the button gets a blur event, but it stays the active element of the page
    fireEvent.blur(button);
    expect(button).not.toHaveAttribute('disabled');
    expect(button).toHaveAttribute('aria-disabled', 'true');
  });

  it('is enabled again when disabled turns false while it has the focus', async () => {
    const onClick = vi.fn();
    const { rerender } = render(<Button onClick={onClick}>Save</Button>);
    await userEvent.tab();
    const button = screen.getByRole('button', { name: 'Save' });
    rerender(
      <Button disabled onClick={onClick}>
        Save
      </Button>,
    );
    rerender(<Button onClick={onClick}>Save</Button>);
    expect(button).toHaveFocus();
    expect(button).not.toHaveAttribute('aria-disabled');
    await userEvent.keyboard('{Enter}');
    expect(onClick).toHaveBeenCalledOnce();
  });

  it('calls the focus and blur handlers of the caller', async () => {
    const onFocus = vi.fn();
    const onBlur = vi.fn();
    render(
      <>
        <Button onFocus={onFocus} onBlur={onBlur}>
          Save
        </Button>
        <Button>Cancel</Button>
      </>,
    );
    await userEvent.tab();
    expect(onFocus).toHaveBeenCalledOnce();
    expect(onBlur).not.toHaveBeenCalled();
    await userEvent.tab();
    expect(screen.getByRole('button', { name: 'Cancel' })).toHaveFocus();
    expect(onBlur).toHaveBeenCalledOnce();
  });

  it('stays a real disabled button with disabled', async () => {
    const onClick = vi.fn();
    const onSubmit = vi.fn((event: FormEvent) => event.preventDefault());
    render(
      <form onSubmit={onSubmit}>
        <Button type="submit" disabled onClick={onClick}>
          Send
        </Button>
      </form>,
    );
    const button = screen.getByRole('button', { name: 'Send' });
    expect(button).toBeDisabled();
    expect(button).not.toHaveAttribute('aria-disabled');
    await userEvent.click(button);
    await userEvent.tab();
    expect(button).not.toHaveFocus();
    expect(onClick).not.toHaveBeenCalled();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('applies the variant classes and lets className override them', () => {
    render(
      <Button variant="danger" className="px-8">
        Delete
      </Button>,
    );
    const button = screen.getByRole('button', { name: 'Delete' });
    expect(button).toHaveClass('bg-danger', 'px-8');
    expect(button).not.toHaveClass('px-4');
  });

  it('builds square classes for icon-only buttons', () => {
    expect(buttonClasses({ iconOnly: true, size: 'sm' })).toContain('w-8');
  });
});

describe('cn', () => {
  it('keeps the last conflicting Tailwind class', () => {
    expect(cn('px-4 text-muted', 'px-2 text-primary')).toBe('px-2 text-primary');
    expect(cn('shadow-glow', 'shadow-none')).toBe('shadow-none');
    expect(cn('text-sm text-muted', false, undefined)).toBe('text-sm text-muted');
  });
});
