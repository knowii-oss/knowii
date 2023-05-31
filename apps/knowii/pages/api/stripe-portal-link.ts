import { createPagesServerClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import { ACCOUNT_URL, errorClientNotAuthenticated, getBaseURL, hasErrorMessage } from '@knowii/common';
import { getOrCreateStripeCustomer, stripe } from '@knowii/server';

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  const supabaseClient = createPagesServerClient({ req, res });

  const {
    data: { session },
  } = await supabaseClient.auth.getSession();

  if (!session) {
    return res.status(401).json({
      error: errorClientNotAuthenticated.code,
      errorDescription: errorClientNotAuthenticated.description,
    });
  }

  try {
    const {
      data: { user },
    } = await supabaseClient.auth.getUser();

    if (!user) throw Error('Could not get user');

    const customer = await getOrCreateStripeCustomer({
      userId: user.id || '',
      email: user.email || '',
    });

    if (!customer) {
      throw Error('Could not get customer');
    }

    const { url } = await stripe.billingPortal.sessions.create({
      customer,
      return_url: new URL(ACCOUNT_URL, getBaseURL()).href,
    });

    return res.status(200).json({ url });
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      res.status(500).json({ error: { statusCode: 500, message: err.message } });
      return;
    }
    res.status(500).json({ error: { statusCode: 500, message: err } });
  }
}
