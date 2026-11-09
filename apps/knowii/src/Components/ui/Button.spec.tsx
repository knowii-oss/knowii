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

  it('is disabled and busy while loading', async () => {
    const onClick = vi.fn();
    render(
      <Button loading onClick={onClick}>
        Save
      </Button>,
    );
    const button = screen.getByRole('button', { name: /Save/ });
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-busy', 'true');
    expect(screen.getByRole('status')).toHaveTextContent('Loading');
    await userEvent.click(button);
    expect(onClick).not.toHaveBeenCalled();
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
