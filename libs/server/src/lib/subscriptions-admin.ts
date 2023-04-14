import Stripe from 'stripe';
import { stripe } from './stripe-admin';
import { supabaseAdmin } from './supabase/supabase-admin';

export async function getOrCreateStripeCustomer({ email, userId }: { email: string; userId: string }): Promise<string> {
  const { data, error } = await supabaseAdmin.from('customers').select('stripe_customer_id').eq('user_id', userId).single();

  // Create a new customer in Stripe if one doesn't exist yet
  if (error || !data?.stripe_customer_id) {
    const customerData = {
      metadata: {
        userId,
      },
      email,
    };

    const customer = await stripe.customers.create(customerData);

    const { error: insertError } = await supabaseAdmin.from('customers').insert([{ user_id: userId, stripe_customer_id: customer.id }]);

    if (insertError) throw insertError;

    return customer.id;
  }

  // otherwise just return the customer id
  return data.stripe_customer_id;
}

// FIXME needed?
// function getCheapestPriceAmount(prices: PriceDetails[]): number {
//   return prices.sort((a, b) => (a.unit_amount ?? 0) - (b.unit_amount ?? 0))[0].unit_amount ?? 0;
// }

export async function getUserIdByCustomerId(customerId: string) {
  const { data, error } = await supabaseAdmin.from('customers').select('user_id').eq('stripe_customer_id', customerId).single();

  if (error) {
    throw error;
  }

  return data?.user_id;
}

export async function upsertProduct(product: Stripe.Product) {
  const { data, error } = await supabaseAdmin.from('products').upsert({
    id: product.id,
    active: product.active,
    name: product.name,
    description: product.description ?? undefined,
    image: product.images?.[0] ?? null,
    metadata: product.metadata,
  });

  if (error) {
    throw error;
  }

  return data;
}

export async function deleteProduct(productId: string) {
  const { error } = await supabaseAdmin.from('product').delete().eq('id', productId);

  if (error) {
    throw error;
  }
}

export async function upsertPrice(price: Stripe.Price) {
  const { data, error } = await supabaseAdmin.from('prices').upsert({
    id: price.id,
    product_id: typeof price.product === 'string' ? price.product : '',
    active: price.active,
    currency: price.currency,
    description: price.nickname ?? undefined,
    type: price.type,
    unit_amount: price.unit_amount ?? undefined,
    interval: price.recurring?.interval,
    interval_count: price.recurring?.interval_count,
    trial_period_days: price.recurring?.trial_period_days,
    metadata: price.metadata,
  });

  if (error) {
    throw error;
  }

  return data;
}

export async function deletePrice(priceId: string) {
  const { error } = await supabaseAdmin.from('prices').delete().eq('id', priceId);

  if (error) {
    throw error;
  }
}

export async function updateUserSubscription(subscription: Stripe.Subscription) {
  const userId = await getUserIdByCustomerId(subscription.customer as string);

  if (!userId) {
    throw new Error('Could not find user id');
  }

  const productId =
    typeof subscription.items.data[0].price.product === 'string'
      ? subscription.items.data[0].price.product
      : subscription.items.data[0].price.product?.id;

  const { error } = await supabaseAdmin.from('subscriptions').upsert({
    user_id: userId,
    id: subscription.id,
    metadata: subscription.metadata,
    status: subscription.status,
    product_id: productId,
    price_id: subscription.items.data[0].price.id,
    // FIXME check why/how quantity is there and fix this
    // eslint-disable-next-line
    // @ts-ignore
    quantity: subscription.quantity,
    cancel_at_period_end: subscription.cancel_at_period_end,
    cancel_at: subscription.cancel_at ? new Date(subscription.cancel_at * 1000).toISOString() : null,
    canceled_at: subscription.canceled_at ? new Date(subscription.canceled_at * 1000).toISOString() : null,
    current_period_start: new Date(subscription.current_period_start * 1000).toISOString(),
    current_period_end: new Date(subscription.current_period_end * 1000).toISOString(),
    created_at: new Date(subscription.created * 1000).toISOString(),
    ended_at: subscription.ended_at ? new Date(subscription.ended_at * 1000).toISOString() : null,
    trial_start: subscription.trial_start ? new Date(subscription.trial_start * 1000).toISOString() : null,
    trial_end: subscription.trial_end ? new Date(subscription.trial_end * 1000).toISOString() : null,
  });

  if (error) {
    throw error;
  }
}
