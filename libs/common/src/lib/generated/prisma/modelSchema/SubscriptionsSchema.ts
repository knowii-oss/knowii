import { z } from 'zod';
import { NullableJsonValue } from '../inputTypeSchemas/NullableJsonValue';
import { SubscriptionStatusSchema } from '../inputTypeSchemas/SubscriptionStatusSchema';
import type { NullableJsonInput } from '../inputTypeSchemas/transformJsonNull';
import type { PricesWithRelations } from './PricesSchema';
import type { PricesPartialWithRelations } from './PricesSchema';
import type { PricesOptionalDefaultsWithRelations } from './PricesSchema';
import type { ProductsWithRelations } from './ProductsSchema';
import type { ProductsPartialWithRelations } from './ProductsSchema';
import type { ProductsOptionalDefaultsWithRelations } from './ProductsSchema';
import { PricesWithRelationsSchema } from './PricesSchema';
import { PricesPartialWithRelationsSchema } from './PricesSchema';
import { PricesOptionalDefaultsWithRelationsSchema } from './PricesSchema';
import { ProductsWithRelationsSchema } from './ProductsSchema';
import { ProductsPartialWithRelationsSchema } from './ProductsSchema';
import { ProductsOptionalDefaultsWithRelationsSchema } from './ProductsSchema';

/////////////////////////////////////////
// SUBSCRIPTIONS SCHEMA
/////////////////////////////////////////

export const SubscriptionsSchema = z.object({
  status: SubscriptionStatusSchema.nullable(),
  id: z.string().uuid(),
  /**
   * Internal user id
   */
  user_id: z.string().uuid(),
  /**
   * Supabase user id
   */
  user_id_external: z.string().uuid().nullable(),
  metadata: NullableJsonValue.optional(),
  product_id: z.string().uuid().nullable(),
  price_id: z.string().uuid().nullable(),
  quantity: z.number().nullable(),
  cancel_at_period_end: z.boolean().nullable(),
  created_at: z.coerce.date(),
  current_period_start: z.coerce.date(),
  current_period_end: z.coerce.date(),
  ended_at: z.coerce.date(),
  cancel_at: z.coerce.date(),
  canceled_at: z.coerce.date(),
  trial_start: z.coerce.date(),
  trial_end: z.coerce.date(),
});

export type Subscriptions = z.infer<typeof SubscriptionsSchema>;

/////////////////////////////////////////
// SUBSCRIPTIONS PARTIAL SCHEMA
/////////////////////////////////////////

export const SubscriptionsPartialSchema = SubscriptionsSchema.partial();

export type SubscriptionsPartial = z.infer<typeof SubscriptionsPartialSchema>;

/////////////////////////////////////////
// SUBSCRIPTIONS OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const SubscriptionsOptionalDefaultsSchema = SubscriptionsSchema.merge(
  z.object({
    id: z.string().uuid().optional(),
    created_at: z.coerce.date().optional(),
    current_period_start: z.coerce.date().optional(),
    current_period_end: z.coerce.date().optional(),
    ended_at: z.coerce.date().optional(),
    cancel_at: z.coerce.date().optional(),
    canceled_at: z.coerce.date().optional(),
    trial_start: z.coerce.date().optional(),
    trial_end: z.coerce.date().optional(),
  }),
);

export type SubscriptionsOptionalDefaults = z.infer<typeof SubscriptionsOptionalDefaultsSchema>;

/////////////////////////////////////////
// SUBSCRIPTIONS RELATION SCHEMA
/////////////////////////////////////////

export type SubscriptionsRelations = {
  prices?: PricesWithRelations | null;
  products?: ProductsWithRelations | null;
};

export type SubscriptionsWithRelations = Omit<z.infer<typeof SubscriptionsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & SubscriptionsRelations;

export const SubscriptionsWithRelationsSchema: z.ZodType<SubscriptionsWithRelations> = SubscriptionsSchema.merge(
  z.object({
    prices: z.lazy(() => PricesWithRelationsSchema).nullable(),
    products: z.lazy(() => ProductsWithRelationsSchema).nullable(),
  }),
);

/////////////////////////////////////////
// SUBSCRIPTIONS OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type SubscriptionsOptionalDefaultsRelations = {
  prices?: PricesOptionalDefaultsWithRelations | null;
  products?: ProductsOptionalDefaultsWithRelations | null;
};

export type SubscriptionsOptionalDefaultsWithRelations = Omit<z.infer<typeof SubscriptionsOptionalDefaultsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & SubscriptionsOptionalDefaultsRelations;

export const SubscriptionsOptionalDefaultsWithRelationsSchema: z.ZodType<SubscriptionsOptionalDefaultsWithRelations> =
  SubscriptionsOptionalDefaultsSchema.merge(
    z.object({
      prices: z.lazy(() => PricesOptionalDefaultsWithRelationsSchema).nullable(),
      products: z.lazy(() => ProductsOptionalDefaultsWithRelationsSchema).nullable(),
    }),
  );

/////////////////////////////////////////
// SUBSCRIPTIONS PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type SubscriptionsPartialRelations = {
  prices?: PricesPartialWithRelations | null;
  products?: ProductsPartialWithRelations | null;
};

export type SubscriptionsPartialWithRelations = Omit<z.infer<typeof SubscriptionsPartialSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & SubscriptionsPartialRelations;

export const SubscriptionsPartialWithRelationsSchema: z.ZodType<SubscriptionsPartialWithRelations> = SubscriptionsPartialSchema.merge(
  z.object({
    prices: z.lazy(() => PricesPartialWithRelationsSchema).nullable(),
    products: z.lazy(() => ProductsPartialWithRelationsSchema).nullable(),
  }),
).partial();

export type SubscriptionsOptionalDefaultsWithPartialRelations = Omit<z.infer<typeof SubscriptionsOptionalDefaultsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & SubscriptionsPartialRelations;

export const SubscriptionsOptionalDefaultsWithPartialRelationsSchema: z.ZodType<SubscriptionsOptionalDefaultsWithPartialRelations> =
  SubscriptionsOptionalDefaultsSchema.merge(
    z
      .object({
        prices: z.lazy(() => PricesPartialWithRelationsSchema).nullable(),
        products: z.lazy(() => ProductsPartialWithRelationsSchema).nullable(),
      })
      .partial(),
  );

export type SubscriptionsWithPartialRelations = Omit<z.infer<typeof SubscriptionsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & SubscriptionsPartialRelations;

export const SubscriptionsWithPartialRelationsSchema: z.ZodType<SubscriptionsWithPartialRelations> = SubscriptionsSchema.merge(
  z
    .object({
      prices: z.lazy(() => PricesPartialWithRelationsSchema).nullable(),
      products: z.lazy(() => ProductsPartialWithRelationsSchema).nullable(),
    })
    .partial(),
);

export default SubscriptionsSchema;
