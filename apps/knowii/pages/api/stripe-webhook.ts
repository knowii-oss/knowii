import { NextApiRequest, NextApiResponse } from 'next';
import { Readable } from 'node:stream';
import Stripe from 'stripe';
import { deletePrice, deleteProduct, stripe, updateUserSubscription, upsertPrice, upsertProduct } from '@knowii/server';
import { getLogger, hasErrorMessage } from '@knowii/common';

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

// FIXME improve type safety
export default async function handler(req: NextApiRequest, res: NextApiResponse) {
  const logger = getLogger('stripe', req.url!);

  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    res.status(405).end('Method Not Allowed');
    return;
  }

  logger.info('Handling request');

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
      logger.warn('Webhook Error: %s', err.message);
      return res.status(400).send(`Webhook Error: ${err.message}`);
    }

    logger.warn('Webhook Error: %o', err);
    return res.status(400).send(`Webhook Error: ${err}`);
  }

  // FIXME improve type safety (never clause)
  if (relevantEvents.has(event.type)) {
    try {
      switch (event.type) {
        case 'product.created': {
          logger.debug('Product created');
          await upsertProduct(event.data.object as Stripe.Product);
          break;
        }
        case 'product.updated': {
          logger.debug('Product updated');
          await upsertProduct(event.data.object as Stripe.Product);
          break;
        }
        case 'product.deleted': {
          logger.debug('Product deleted');
          await deleteProduct((event.data.object as Stripe.Product).id);
          break;
        }
        case 'price.created': {
          logger.debug('Price created');
          await upsertPrice(event.data.object as Stripe.Price);
          break;
        }
        case 'price.updated': {
          logger.debug('Price updated');
          await upsertPrice(event.data.object as Stripe.Price);
          break;
        }
        case 'price.deleted': {
          logger.debug('Price deleted');
          await deletePrice((event.data.object as Stripe.Price).id);
          break;
        }
        case 'customer.subscription.created': {
          logger.debug('Customer subscription created');
          const subscription = event.data.object as Stripe.Subscription;
          await updateUserSubscription(subscription);
          break;
        }
        case 'customer.subscription.updated': {
          logger.debug('Customer subscription updated');
          const subscription = event.data.object as Stripe.Subscription;
          await updateUserSubscription(subscription);
          break;
        }
        case 'customer.subscription.deleted': {
          logger.debug('Customer subscription deleted');
          const subscription = event.data.object as Stripe.Subscription;
          await updateUserSubscription(subscription);
          break;
        }
        case 'checkout.session.completed': {
          logger.debug('Checkout session completed');
          const checkoutSession = event.data.object as Stripe.Checkout.Session;
          if (checkoutSession.mode === 'subscription') {
            let subscription: Stripe.Subscription;

            if (!checkoutSession.subscription) {
              logger.warn('No subscription ID found in checkout session');
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
          logger.debug('Unhandled relevant event!');
          throw new Error('Unhandled relevant event!');
        }
      }
    } catch (error) {
      logger.warn('Webhook error: "Webhook handler failed. View logs."');
      return res.status(400).send('Webhook error: "Webhook handler failed. View logs."');
    }
  }

  res.json({ received: true });
}
