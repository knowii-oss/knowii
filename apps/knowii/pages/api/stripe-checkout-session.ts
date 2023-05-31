import { createServerSupabaseClient } from '@supabase/auth-helpers-nextjs';
import { NextApiRequest, NextApiResponse } from 'next';
import { ACCOUNT_URL, errorClientNotAuthenticated, getBaseURL, hasErrorMessage } from '@knowii/common';
import { getOrCreateStripeCustomer, stripe } from '@knowii/server';

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  const supabaseClient = createServerSupabaseClient({ req, res });

  const {
    data: { session },
  } = await supabaseClient.auth.getSession();

  if (!session) {
    return res.status(401).json({
      error: errorClientNotAuthenticated.code,
      errorDescription: errorClientNotAuthenticated.description,
    });
  }

  const { priceId, metadata = {} } = req.body;

  try {
    const {
      data: { user },
    } = await supabaseClient.auth.getUser();

    const customer = await getOrCreateStripeCustomer({
      userId: user?.id || '',
      email: user?.email || '',
    });

    const session = await stripe.checkout.sessions.create({
      payment_method_types: ['card'],
      billing_address_collection: 'required',
      customer,
      line_items: [
        {
          price: priceId,
          quantity: 1,
        },
      ],
      mode: 'subscription',
      allow_promotion_codes: true,
      subscription_data: {
        metadata,
      },
      success_url: new URL(ACCOUNT_URL, getBaseURL()).href,
      cancel_url: getBaseURL(),
    });

    return res.status(200).json({ sessionId: session.id });
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      res.status(500).json({ error: { statusCode: 500, message: err.message } });
      return;
    }
    res.status(500).json({ error: { statusCode: 500, message: err } });
  }
}
