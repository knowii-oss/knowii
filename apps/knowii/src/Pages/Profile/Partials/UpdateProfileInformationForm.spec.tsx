import { afterEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import axe from 'axe-core';
import type { ReactNode } from 'react';
import type * as Common from '@knowii/common';
import type { CurrentUser } from '@knowii/common';
import { AppDataProvider, MAX_LENGTH_USER_HEADLINE, useToasts } from '@knowii/common';
import UpdateProfileInformationForm from './UpdateProfileInformationForm';

const profile = {
  name: 'Ada Lovelace',
  username: 'ada',
  headline: null as string | null,
  bio: null,
  location: null,
  phone: null,
  field_visibility: null as Record<string, boolean> | null,
};

const page = vi.hoisted(() => ({
  props: {
    jetstream: { hasEmailVerification: true },
    userProfile: {} as Record<string, unknown>,
  },
}));
page.props.userProfile = { ...profile };

// What the form holds and sends: the mock of useForm keeps the data in the state of the component, as Inertia does
const form = vi.hoisted(() => ({
  errors: {} as Record<string, string>,
  put: vi.fn<(url: string, data: Record<string, unknown>, options: Record<string, unknown>) => void>(),
  reset: vi.fn<() => void>(),
}));

// The two buttons of a change of the email address (#1706): web routes of the profile, through the router of Inertia
const routerCalls = vi.hoisted(() => ({
  post: vi.fn<(url: string, data: Record<string, unknown>, options: Record<string, unknown>) => void>(),
  delete: vi.fn<(url: string, options: Record<string, unknown>) => void>(),
}));

vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => page,
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/route/${name}` }));
vi.mock('@inertiajs/react', async () => {
  const { useState } = await import('react');
  return {
    Link: (props: { href: string; children: ReactNode }) => <a href={props.href}>{props.children}</a>,
    router: routerCalls,
    useForm: (initial: Record<string, unknown>) => {
      const [data, setData] = useState(initial);
      return {
        data,
        setData: (key: string, value: unknown) => setData((current) => ({ ...current, [key]: value })),
        errors: form.errors,
        hasErrors: Object.keys(form.errors).length > 0,
        processing: false,
        // "Reset" of the form: the stored values come back, and the errors go
        resetAndClearErrors: () => {
          form.reset();
          setData(initial);
        },
        put: (url: string, options: Record<string, unknown>) => form.put(url, data, options),
        clearErrors: vi.fn(),
        setError: vi.fn(),
      };
    },
  };
});

const user: CurrentUser = {
  cuid: 'user-cuid',
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  username: 'ada',
  avatar_url: null,
  two_factor_enabled: false,
  two_factor_confirmed_at: null,
  email_verified_at: new Date('2026-11-01T10:00:00Z'),
  created_at: new Date('2026-11-01T10:00:00Z'),
  updated_at: new Date('2026-11-01T10:00:00Z'),
};

const PRIVACY_TEXT =
  'Your name, username and picture show next to what you write. In a public space, visitors see them too. Your email address is never shown to other members. Headline, bio, location and phone: you choose below.';

const HEADLINE_SWITCH = 'Visible to the members of my communities, Headline';

function renderForm(account: CurrentUser = user, hasPassword?: boolean) {
  return render(
    <AppDataProvider>
      <UpdateProfileInformationForm user={account} hasPassword={hasPassword} />
    </AppDataProvider>,
  );
}

const PENDING_LINE = 'Confirm your new email address. We sent a link to ada.new@example.com.';

afterEach(() => {
  page.props.userProfile = { ...profile };
  form.errors = {};
  form.put.mockClear();
  form.reset.mockClear();
  routerCalls.post.mockClear();
  routerCalls.delete.mockClear();
});

describe('UpdateProfileInformationForm', () => {
  it('says what the others see of the profile (#1566, #1688)', () => {
    renderForm();

    expect(screen.getByText(PRIVACY_TEXT)).toBeInTheDocument();
  });

  it('no longer says that almost all of the profile is public (#1566)', () => {
    renderForm();

    const text = document.body.textContent;
    expect(text).not.toContain("Update your account's profile information.");
    expect(text).not.toContain('Apart from your username');
    expect(text).not.toContain('all the information will be publicly visible to other Knowii users');
  });
});

describe('the headline of the profile form (#1688)', () => {
  it('has a field "Headline" of one line, between the username and the bio, with the limit and a hint', () => {
    renderForm();

    const field = screen.getByRole('textbox', { name: 'Headline' });
    expect(field).toHaveAttribute('id', 'headline');
    expect(field).toHaveAttribute('type', 'text');
    expect(field).toHaveAttribute('maxLength', String(MAX_LENGTH_USER_HEADLINE));
    expect(MAX_LENGTH_USER_HEADLINE).toBe(80);
    expect(field).toHaveAccessibleDescription('One line about you. It shows under your name.');
    expect(field).toHaveValue('');
    expect(field).toBeEnabled();

    const fields = screen.getAllByRole('textbox').map((textbox) => textbox.id);
    expect(fields.indexOf('headline')).toBe(fields.indexOf('username') + 1);
    expect(fields.indexOf('bio')).toBe(fields.indexOf('headline') + 1);
  });

  it('shows the stored headline', () => {
    page.props.userProfile = { ...profile, headline: 'Product designer, writes about note-taking' };
    renderForm();

    expect(screen.getByRole('textbox', { name: 'Headline' })).toHaveValue('Product designer, writes about note-taking');
  });

  it('has a switch that names the field for a screen reader, on before the member chooses', () => {
    renderForm();

    expect(screen.getByRole('checkbox', { name: HEADLINE_SWITCH })).toBeChecked();
    // The stored choices of a profile from before the headline hold no key for it: the default applies
    expect(screen.getByRole('checkbox', { name: 'Visible to the members of my communities, Phone' })).not.toBeChecked();
  });

  it('shows the switch off when the member hides the headline', () => {
    page.props.userProfile = { ...profile, headline: 'Product designer', field_visibility: { headline: false, bio: true } };
    renderForm();

    expect(screen.getByRole('checkbox', { name: HEADLINE_SWITCH })).not.toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Visible to the members of my communities, Bio' })).toBeChecked();
  });

  it('sends the headline and its choice with the form', async () => {
    renderForm();

    await userEvent.type(screen.getByRole('textbox', { name: 'Headline' }), 'Product designer');
    await userEvent.click(screen.getByRole('checkbox', { name: HEADLINE_SWITCH }));
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(form.put).toHaveBeenCalledTimes(1);
    const [url, sent] = form.put.mock.calls[0] ?? [];
    expect(url).toBe('/route/user-profile-information.update');
    expect(sent).toMatchObject({ headline: 'Product designer', field_visibility: { headline: false, bio: true, phone: false } });
  });

  it('asks for the errors of the profile form, so that the error of the server reaches the field', async () => {
    renderForm();

    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    // The action validates with this bag: without its name in the request, `form.errors.headline` stays empty
    expect(form.put.mock.calls[0]?.[2]).toMatchObject({ errorBag: 'updateProfileInformation' });
  });

  it('sends the headline of a member who changes nothing, with the choice "shown"', async () => {
    page.props.userProfile = { ...profile, headline: 'Product designer' };
    renderForm();

    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(form.put.mock.calls[0]?.[1]).toMatchObject({ headline: 'Product designer', field_visibility: { headline: true } });
  });

  it('puts the stored headline back with "Reset"', async () => {
    page.props.userProfile = { ...profile, headline: 'Product designer' };
    renderForm();
    const field = screen.getByRole('textbox', { name: 'Headline' });

    await userEvent.type(field, ' and more');
    expect(field).toHaveValue('Product designer and more');
    await userEvent.click(screen.getByRole('button', { name: 'Reset' }));

    expect(form.reset).toHaveBeenCalledTimes(1);
    expect(field).toHaveValue('Product designer');
  });

  it('shows the error of the server under the field', () => {
    form.errors = { headline: 'The headline field must not be greater than 80 characters.' };
    renderForm();

    const error = screen.getByRole('alert');
    expect(error).toHaveTextContent('The headline field must not be greater than 80 characters.');
    // Under the field: after the field, its hint and its switch, before the bio
    const field = screen.getByRole('textbox', { name: 'Headline' });
    const bio = screen.getByRole('textbox', { name: 'Bio' });
    expect(field.compareDocumentPosition(error) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(
      screen.getByRole('checkbox', { name: HEADLINE_SWITCH }).compareDocumentPosition(error) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
    expect(error.compareDocumentPosition(bio) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('is disabled, with its switch, while the address is not verified', () => {
    renderForm({ ...user, email_verified_at: null });

    expect(screen.getByRole('textbox', { name: 'Headline' })).toBeDisabled();
    expect(screen.getByRole('checkbox', { name: HEADLINE_SWITCH })).toBeDisabled();
  });

  it('has no automated violation (axe), empty and with an error', async () => {
    const empty = renderForm();
    expect((await axe.run(empty.container, { rules: { region: { enabled: false } } })).violations.map((violation) => violation.id)).toEqual(
      [],
    );
    empty.unmount();

    page.props.userProfile = { ...profile, headline: 'Product designer', field_visibility: { headline: false } };
    form.errors = { headline: 'The headline field must not be greater than 80 characters.' };
    const withError = renderForm();
    expect(
      (await axe.run(withError.container, { rules: { region: { enabled: false } } })).violations.map((violation) => violation.id),
    ).toEqual([]);
  });
});

describe('a change of the email address (#1706)', () => {
  it('shows no line and no password field while no change waits', () => {
    renderForm();

    expect(screen.queryByText(/Confirm your new email address/)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Send the link again' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Cancel the change' })).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Current password')).not.toBeInTheDocument();
  });

  it('shows the line of a change that waits, with the pending address, under the email field', () => {
    renderForm({ ...user, pending_email: 'ada.new@example.com', pending_email_requested_at: new Date('2026-11-21T10:00:00Z') });

    const line = screen.getByText((_, element) => element?.tagName === 'P' && element.textContent === PENDING_LINE);
    expect(line).toBeInTheDocument();
    // The field keeps the address of the account: the change applies after the link
    const email = screen.getByRole('textbox', { name: 'Email' });
    expect(email).toHaveValue('ada@example.com');
    expect(email.compareDocumentPosition(line) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(line.compareDocumentPosition(screen.getByRole('textbox', { name: 'Username' })) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('"Send the link again" posts to its route, without a password', async () => {
    renderForm({ ...user, pending_email: 'ada.new@example.com', pending_email_requested_at: new Date('2026-11-21T10:00:00Z') });

    await userEvent.click(screen.getByRole('button', { name: 'Send the link again' }));

    expect(routerCalls.post).toHaveBeenCalledTimes(1);
    expect(routerCalls.post.mock.calls[0]?.[0]).toBe('/route/email-change.resend');
    expect(routerCalls.post.mock.calls[0]?.[1]).toEqual({});
    expect(routerCalls.post.mock.calls[0]?.[2]).toMatchObject({ preserveScroll: true });
    expect(routerCalls.delete).not.toHaveBeenCalled();
    expect(form.put).not.toHaveBeenCalled();
  });

  it('"Cancel the change" deletes the change, and gives the focus to the email field after it', async () => {
    renderForm({ ...user, pending_email: 'ada.new@example.com', pending_email_requested_at: new Date('2026-11-21T10:00:00Z') });

    await userEvent.click(screen.getByRole('button', { name: 'Cancel the change' }));

    expect(routerCalls.delete).toHaveBeenCalledTimes(1);
    expect(routerCalls.delete.mock.calls[0]?.[0]).toBe('/route/email-change.cancel');
    const options = routerCalls.delete.mock.calls[0]?.[1] as { preserveScroll: boolean; onSuccess: () => void };
    expect(options.preserveScroll).toBe(true);
    options.onSuccess();
    expect(screen.getByRole('textbox', { name: 'Email' })).toHaveFocus();
  });

  it('asks for the current password when the email field holds another address, and sends it', async () => {
    renderForm();
    const email = screen.getByRole('textbox', { name: 'Email' });

    await userEvent.clear(email);
    await userEvent.type(email, 'ada.new@example.com');
    const password = screen.getByLabelText('Current password');
    expect(password).toHaveAttribute('type', 'password');
    expect(password).toHaveAttribute('autocomplete', 'current-password');
    expect(password).toBeRequired();
    // After the email field
    expect(email.compareDocumentPosition(password) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();

    await userEvent.type(password, 'the-password');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(form.put.mock.calls[0]?.[1]).toMatchObject({ email: 'ada.new@example.com', current_password: 'the-password' });
  });

  it('says "We sent a link to the new address." after a save that changes the address, whatever the new address', async () => {
    function Toasts() {
      const { toasts } = useToasts();
      return (
        <ul>
          {toasts.map((toast) => (
            <li key={toast.id}>{toast.detail}</li>
          ))}
        </ul>
      );
    }
    render(
      <AppDataProvider>
        <UpdateProfileInformationForm user={user} hasPassword />
        <Toasts />
      </AppDataProvider>,
    );
    const email = screen.getByRole('textbox', { name: 'Email' });
    await userEvent.clear(email);
    await userEvent.type(email, 'ada.new@example.com');
    await userEvent.type(screen.getByLabelText('Current password'), 'the-password');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    const options = form.put.mock.calls[0]?.[2] as { onSuccess: () => void };
    act(() => options.onSuccess());

    expect(screen.getByText('We sent a link to the new address.')).toBeInTheDocument();
    expect(screen.queryByText('Profile updated successfully.')).toBeNull();
  });

  it('asks for no password for the same address in another case, and for an account without a password', async () => {
    const first = renderForm();
    const email = screen.getByRole('textbox', { name: 'Email' });
    await userEvent.clear(email);
    await userEvent.type(email, 'ADA@Example.com');
    expect(screen.queryByLabelText('Current password')).not.toBeInTheDocument();
    first.unmount();

    renderForm(user, false);
    const field = screen.getByRole('textbox', { name: 'Email' });
    await userEvent.clear(field);
    await userEvent.type(field, 'ada.new@example.com');
    expect(screen.queryByLabelText('Current password')).not.toBeInTheDocument();
  });

  it('shows the error of a wrong password under the password field', async () => {
    form.errors = { current_password: 'The provided password does not match your current password.' };
    renderForm();
    const email = screen.getByRole('textbox', { name: 'Email' });

    await userEvent.clear(email);
    await userEvent.type(email, 'ada.new@example.com');

    const password = screen.getByLabelText('Current password');
    expect(password).toHaveAttribute('aria-invalid', 'true');
    expect(password).toHaveAccessibleDescription('The provided password does not match your current password.');
  });

  it('lets an account whose address was never verified correct the address, and keeps the other fields closed', () => {
    renderForm({ ...user, email_verified_at: null });

    expect(screen.getByRole('textbox', { name: 'Email' })).toBeEnabled();
    expect(screen.getByRole('textbox', { name: 'Name' })).toBeDisabled();
  });

  it('has no automated violation (axe) with a change that waits and the password field', async () => {
    const view = renderForm({
      ...user,
      pending_email: 'ada.new@example.com',
      pending_email_requested_at: new Date('2026-11-21T10:00:00Z'),
    });
    const email = screen.getByRole('textbox', { name: 'Email' });
    await userEvent.clear(email);
    await userEvent.type(email, 'ada.other@example.com');

    expect(screen.getByLabelText('Current password')).toBeInTheDocument();
    expect((await axe.run(view.container, { rules: { region: { enabled: false } } })).violations.map((violation) => violation.id)).toEqual(
      [],
    );
  });
});
