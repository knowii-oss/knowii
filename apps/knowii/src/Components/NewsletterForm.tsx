import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { FaAt, FaCircleCheck } from 'react-icons/fa6';
import { z } from 'zod';
import { knowiiApiClient, WAITLIST_HONEYPOT_FIELD, waitlistEmailSchema } from '@knowii/common';
import InputError from '@/Components/InputError';
import { Button, TextInput, Turnstile } from '@/Components/ui';

const newsletterFormSchema = z.object({
  email: waitlistEmailSchema,
});

type NewsletterFormData = z.infer<typeof newsletterFormSchema>;

interface NewsletterFormProps {
  turnstileSiteKey: string;
  /**
   * The address of the signed-in member, to fill the field
   */
  defaultEmail?: string | undefined;
}

/**
 * The newsletter form of the footer (#938): the waitlist pattern (Turnstile, honeypot, rate limit, double opt-in) on
 * another EmailOctopus list. The Turnstile widget loads only when the member starts to use the form, so pages do not
 * call Cloudflare for a form that nobody uses.
 */
export default function NewsletterForm({ turnstileSiteKey, defaultEmail }: NewsletterFormProps) {
  const [botCheckStarted, setBotCheckStarted] = useState(false);
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);
  // A Turnstile token works once: a new key mounts a new widget after each attempt
  const [turnstileKey, setTurnstileKey] = useState(0);
  const [honeypot, setHoneypot] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const form = useForm<NewsletterFormData>({
    resolver: zodResolver(newsletterFormSchema),
    defaultValues: { email: defaultEmail ?? '' },
    mode: 'onSubmit',
    reValidateMode: 'onChange',
  });

  const submit = form.handleSubmit(async (data) => {
    if (!turnstileToken) {
      setBotCheckStarted(true);
      setErrorMessage('Please wait for the bot check to finish.');
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);

    const response = await knowiiApiClient.newsletter.subscribe({
      email: data.email,
      turnstileToken,
      [WAITLIST_HONEYPOT_FIELD]: honeypot,
    });

    setSubmitting(false);
    setTurnstileToken(null);
    setTurnstileKey((current) => current + 1);

    if (response.type === 'success') {
      setSuccessMessage(response.message);
      form.reset({ email: '' });
      return;
    }

    if (response.type === 'tooManyRequests') {
      setErrorMessage('Too many attempts. Please wait a minute and try again.');
      return;
    }

    setErrorMessage(response.message || 'Failed to subscribe to the newsletter. Please try again later.');
  });

  if (successMessage) {
    return (
      <p role="status" className="flex items-start gap-3 rounded-lg border border-border bg-surface p-3 text-sm">
        <FaCircleCheck aria-hidden="true" className="mt-0.5 shrink-0 text-success" />
        {successMessage}
      </p>
    );
  }

  return (
    <form onSubmit={submit} noValidate aria-labelledby="newsletter-title" onFocus={() => setBotCheckStarted(true)}>
      <h2 id="newsletter-title" className="text-sm font-extrabold">
        Newsletter
      </h2>
      <p className="mt-1 text-sm text-muted">News about Knowii, a few times a year. No spam.</p>
      <label htmlFor="newsletter-email" className="sr-only">
        Email address
      </label>
      <div className="mt-2 flex flex-col gap-2 sm:flex-row">
        <Controller
          name="email"
          control={form.control}
          render={({ field }) => (
            <TextInput
              {...field}
              id="newsletter-email"
              type="email"
              autoComplete="email"
              placeholder="Email address"
              icon={<FaAt />}
              required
              invalid={!!form.formState.errors.email}
              aria-describedby={form.formState.errors.email ? 'newsletter-email-error' : undefined}
              disabled={submitting}
            />
          )}
        />
        <Button type="submit" loading={submitting} className="h-11 shrink-0">
          Subscribe
        </Button>
      </div>
      <InputError id="newsletter-email-error" className="mt-2" message={form.formState.errors.email?.message} />

      {/* Honeypot: hidden from people and from screen readers, bots fill it in */}
      <div aria-hidden="true" className="absolute -left-[10000px] h-px w-px overflow-hidden">
        <label htmlFor="newsletter-website">Website</label>
        <input
          id="newsletter-website"
          type="text"
          name={WAITLIST_HONEYPOT_FIELD}
          tabIndex={-1}
          autoComplete="off"
          value={honeypot}
          onChange={(e) => setHoneypot(e.target.value)}
        />
      </div>

      {botCheckStarted && <Turnstile key={turnstileKey} siteKey={turnstileSiteKey} onTokenChange={setTurnstileToken} className="mt-3" />}

      {errorMessage && <InputError className="mt-2" message={errorMessage} />}
    </form>
  );
}
