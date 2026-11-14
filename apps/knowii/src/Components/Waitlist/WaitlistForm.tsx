import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { FaAt, FaCircleCheck } from 'react-icons/fa6';
import { useRoute } from 'ziggy-js';
import { z } from 'zod';
import { knowiiApiClient, PRIVACY_POLICY_URL, WAITLIST_HONEYPOT_FIELD, waitlistEmailSchema } from '@knowii/common';
import InputError from '@/Components/InputError';
import { Button, Card, TextInput, Turnstile } from '@/Components/ui';

const waitlistFormSchema = z.object({
  email: waitlistEmailSchema,
});

type WaitlistFormData = z.infer<typeof waitlistFormSchema>;

interface WaitlistFormProps {
  turnstileSiteKey: string;
}

/**
 * Join the waitlist (EmailOctopus list, through the Knowii API). Protected by Cloudflare Turnstile, a honeypot field
 * and a rate limit; the address must be confirmed by email (double opt-in).
 */
export default function WaitlistForm({ turnstileSiteKey }: WaitlistFormProps) {
  const route = useRoute();
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);
  // A Turnstile token works once: a new key mounts a new widget after each attempt
  const [turnstileKey, setTurnstileKey] = useState(0);
  const [honeypot, setHoneypot] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const form = useForm<WaitlistFormData>({
    resolver: zodResolver(waitlistFormSchema),
    defaultValues: { email: '' },
    mode: 'onSubmit',
    reValidateMode: 'onChange',
  });

  const submit = form.handleSubmit(async (data) => {
    if (!turnstileToken) {
      setErrorMessage('Please wait for the bot check to finish.');
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    const response = await knowiiApiClient.waitlist.join({
      email: data.email,
      turnstileToken,
      [WAITLIST_HONEYPOT_FIELD]: honeypot,
    });

    setSubmitting(false);
    setTurnstileToken(null);
    setTurnstileKey((current) => current + 1);

    if (response.type === 'success') {
      setSuccessMessage(response.message);
      form.reset();
      return;
    }

    if (response.type === 'tooManyRequests') {
      setErrorMessage('Too many attempts. Please wait a minute and try again.');
      return;
    }

    setErrorMessage(response.message || 'Failed to join the waitlist. Please try again later.');
  });

  return (
    <Card aria-labelledby="waitlist-title" className="w-full max-w-lg p-6 sm:p-8">
      <h2 id="waitlist-title" className="text-2xl font-extrabold">
        Join the waitlist
      </h2>
      <span aria-hidden="true" className="mt-2 block h-1 w-12 rounded-full bg-secondary" />
      <p className="mt-4 text-muted">Keep me up to date: one email when Knowii opens, no spam.</p>

      {successMessage ? (
        <p role="status" className="mt-6 flex items-start gap-3 rounded-lg border border-border bg-well p-4">
          <FaCircleCheck aria-hidden="true" className="mt-1 shrink-0 text-success" />
          {successMessage}
        </p>
      ) : (
        <form onSubmit={submit} noValidate className="mt-6">
          <label htmlFor="waitlist-email" className="block text-sm font-bold">
            Email address
          </label>
          <div className="mt-1 flex flex-col gap-3 sm:flex-row">
            <Controller
              name="email"
              control={form.control}
              render={({ field }) => (
                <TextInput
                  {...field}
                  id="waitlist-email"
                  type="email"
                  autoComplete="email"
                  icon={<FaAt />}
                  required
                  invalid={!!form.formState.errors.email}
                  aria-describedby={form.formState.errors.email ? 'waitlist-email-error waitlist-consent' : 'waitlist-consent'}
                  disabled={submitting}
                />
              )}
            />
            <Button type="submit" loading={submitting} disabled={!turnstileToken} className="h-11">
              Subscribe
            </Button>
          </div>
          <InputError id="waitlist-email-error" className="mt-2" message={form.formState.errors.email?.message} />
          {/* Consent is informed when the address is given (GDPR art. 7 and 13, #1346). Approved text */}
          <p id="waitlist-consent" className="mt-2 text-xs text-muted">
            By subscribing, you agree to receive one email when Knowii opens. You can unsubscribe in that email. See the{' '}
            <a href={route(PRIVACY_POLICY_URL)} className="simple-link">
              privacy policy
            </a>
            .
          </p>

          {/* Honeypot: hidden from people and from screen readers, bots fill it in */}
          <div aria-hidden="true" className="absolute -left-[10000px] h-px w-px overflow-hidden">
            <label htmlFor="waitlist-website">Website</label>
            <input
              id="waitlist-website"
              type="text"
              name={WAITLIST_HONEYPOT_FIELD}
              tabIndex={-1}
              autoComplete="off"
              value={honeypot}
              onChange={(e) => setHoneypot(e.target.value)}
            />
          </div>

          <Turnstile key={turnstileKey} siteKey={turnstileSiteKey} onTokenChange={setTurnstileToken} className="mt-4" />

          {errorMessage && <InputError className="mt-2" message={errorMessage} />}
        </form>
      )}
    </Card>
  );
}
