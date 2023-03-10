import { NextApiRequest, NextApiResponse } from 'next';
import { Readable } from 'node:stream';
import Stripe from 'stripe';
import { deletePrice, deleteProduct, stripe, updateUserSubscription, upsertPrice, upsertProduct } from '@knowii/server';
import { hasErrorMessage } from '@knowii/common';

export const config = {
  api: {
    bodyParser: false,
  },
};

async function buffer(readable: Readable) {
  const chunks = [];
  for await (const chunk of readable) {
    chunks.push(typeof chunk === 'string' ? Buffer.from(chunk) : chunk);
  }
  return Buffer.concat(chunks);
}

const relevantEvents = new Set([
  'price.created',
  'price.updated',
  'price.deleted',
  'product.created',
  'product.updated',
  'product.deleted',
  'checkout.session.completed',
  'customer.subscription.created',
  'customer.subscription.updated',
  'customer.subscription.deleted',
]);

export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  const buf = await buffer(req);
  const sig = req.headers['stripe-signature'];
  const webhookSecret = process.env.STRIPE_WEBHOOK_SECRET;
  let event: Stripe.Event;

  try {
    if (!sig || !webhookSecret) {
      return;
    }
    event = stripe.webhooks.constructEvent(buf, sig, webhookSecret);
  } catch (err: unknown) {
    if (hasErrorMessage(err)) {
      return res.status(400).send(`Webhook Error: ${err.message}`);
    }
    return res.status(400).send(`Webhook Error: ${err}`);
  }

  if (relevantEvents.has(event.type)) {
    try {
      switch (event.type) {
        case 'product.created': {
          await upsertProduct(event.data.object as Stripe.Product);
          break;
        }
        case 'product.updated': {
          await upsertProduct(event.data.object as Stripe.Product);
          break;
        }
        case 'product.deleted': {
          await deleteProduct((event.data.object as Stripe.Product).id);
          break;
        }
        case 'price.created': {
          await upsertPrice(event.data.object as Stripe.Price);
          break;
        }
        case 'price.updated': {
          await upsertPrice(event.data.object as Stripe.Price);
          break;
        }
        case 'price.deleted': {
          await deletePrice((event.data.object as Stripe.Price).id);
          break;
        }
        case 'customer.subscription.created': {
          const subscription = event.data.object as Stripe.Subscription;
          await updateUserSubscription(subscription);
          break;
        }
        case 'customer.subscription.updated': {
          const subscription = event.data.object as Stripe.Subscription;
          await updateUserSubscription(subscription);
          break;
        }
        case 'customer.subscription.deleted': {
          const subscription = event.data.object as Stripe.Subscription;
          await updateUserSubscription(subscription);
          break;
        }
        case 'checkout.session.completed': {
          const checkoutSession = event.data.object as Stripe.Checkout.Session;
          if (checkoutSession.mode === 'subscription') {
            let subscription: Stripe.Subscription;

            if (!checkoutSession.subscription) {
              throw new Error('No subscription ID found in checkout session');
            }

            if (typeof checkoutSession.subscription === 'string') {
              subscription = await stripe.subscriptions.retrieve(checkoutSession.subscription);
            } else {
              subscription = checkoutSession.subscription;
            }

            await updateUserSubscription(subscription);
          }
          break;
        }
        default: {
          throw new Error('Unhandled relevant event!');
        }
      }
    } catch (error) {
      console.error(error);
      return res.status(400).send('Webhook error: "Webhook handler failed. View logs."');
    }
  }

  res.json({ received: true });
}
