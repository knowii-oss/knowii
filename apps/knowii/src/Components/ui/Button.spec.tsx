import type { FormEvent } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
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
