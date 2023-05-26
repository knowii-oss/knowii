import { z } from 'zod';
import { NullableJsonValue } from '../inputTypeSchemas/NullableJsonValue';
import { PricingTypeSchema } from '../inputTypeSchemas/PricingTypeSchema';
import { PricingPlanIntervalSchema } from '../inputTypeSchemas/PricingPlanIntervalSchema';
import type { NullableJsonInput } from '../inputTypeSchemas/transformJsonNull';
import type { ProductsWithRelations } from './ProductsSchema';
import type { ProductsPartialWithRelations } from './ProductsSchema';
import type { ProductsOptionalDefaultsWithRelations } from './ProductsSchema';
import type { SubscriptionsWithRelations } from './SubscriptionsSchema';
import type { SubscriptionsPartialWithRelations } from './SubscriptionsSchema';
import type { SubscriptionsOptionalDefaultsWithRelations } from './SubscriptionsSchema';
import { ProductsWithRelationsSchema } from './ProductsSchema';
import { ProductsPartialWithRelationsSchema } from './ProductsSchema';
import { ProductsOptionalDefaultsWithRelationsSchema } from './ProductsSchema';
import { SubscriptionsWithRelationsSchema } from './SubscriptionsSchema';
import { SubscriptionsPartialWithRelationsSchema } from './SubscriptionsSchema';
import { SubscriptionsOptionalDefaultsWithRelationsSchema } from './SubscriptionsSchema';

/////////////////////////////////////////
// PRICES SCHEMA
/////////////////////////////////////////

export const PricesSchema = z.object({
  type: PricingTypeSchema.nullable(),
  interval: PricingPlanIntervalSchema.nullable(),
  id: z.string().uuid(),
  product_id: z.string().uuid().nullable(),
  active: z.boolean().nullable(),
  description: z.string().nullable(),
  unit_amount: z.bigint().nullable(),
  currency: z.string().nullable(),
  interval_count: z.number().int().nullable(),
  trial_period_days: z.number().int().nullable(),
  metadata: NullableJsonValue.optional(),
});

export type Prices = z.infer<typeof PricesSchema>;

/////////////////////////////////////////
// PRICES PARTIAL SCHEMA
/////////////////////////////////////////

export const PricesPartialSchema = PricesSchema.partial();

export type PricesPartial = z.infer<typeof PricesPartialSchema>;

/////////////////////////////////////////
// PRICES OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const PricesOptionalDefaultsSchema = PricesSchema.merge(
  z.object({
    id: z.string().uuid().optional(),
  }),
);

export type PricesOptionalDefaults = z.infer<typeof PricesOptionalDefaultsSchema>;

/////////////////////////////////////////
// PRICES RELATION SCHEMA
/////////////////////////////////////////

export type PricesRelations = {
  products?: ProductsWithRelations | null;
  subscriptions: SubscriptionsWithRelations[];
};

export type PricesWithRelations = Omit<z.infer<typeof PricesSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & PricesRelations;

export const PricesWithRelationsSchema: z.ZodType<PricesWithRelations> = PricesSchema.merge(
  z.object({
    products: z.lazy(() => ProductsWithRelationsSchema).nullable(),
    subscriptions: z.lazy(() => SubscriptionsWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// PRICES OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type PricesOptionalDefaultsRelations = {
  products?: ProductsOptionalDefaultsWithRelations | null;
  subscriptions: SubscriptionsOptionalDefaultsWithRelations[];
};

export type PricesOptionalDefaultsWithRelations = Omit<z.infer<typeof PricesOptionalDefaultsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & PricesOptionalDefaultsRelations;

export const PricesOptionalDefaultsWithRelationsSchema: z.ZodType<PricesOptionalDefaultsWithRelations> = PricesOptionalDefaultsSchema.merge(
  z.object({
    products: z.lazy(() => ProductsOptionalDefaultsWithRelationsSchema).nullable(),
    subscriptions: z.lazy(() => SubscriptionsOptionalDefaultsWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// PRICES PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type PricesPartialRelations = {
  products?: ProductsPartialWithRelations | null;
  subscriptions?: SubscriptionsPartialWithRelations[];
};

export type PricesPartialWithRelations = Omit<z.infer<typeof PricesPartialSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & PricesPartialRelations;

export const PricesPartialWithRelationsSchema: z.ZodType<PricesPartialWithRelations> = PricesPartialSchema.merge(
  z.object({
    products: z.lazy(() => ProductsPartialWithRelationsSchema).nullable(),
    subscriptions: z.lazy(() => SubscriptionsPartialWithRelationsSchema).array(),
  }),
).partial();

export type PricesOptionalDefaultsWithPartialRelations = Omit<z.infer<typeof PricesOptionalDefaultsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & PricesPartialRelations;

export const PricesOptionalDefaultsWithPartialRelationsSchema: z.ZodType<PricesOptionalDefaultsWithPartialRelations> =
  PricesOptionalDefaultsSchema.merge(
    z
      .object({
        products: z.lazy(() => ProductsPartialWithRelationsSchema).nullable(),
        subscriptions: z.lazy(() => SubscriptionsPartialWithRelationsSchema).array(),
      })
      .partial(),
  );

export type PricesWithPartialRelations = Omit<z.infer<typeof PricesSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & PricesPartialRelations;

export const PricesWithPartialRelationsSchema: z.ZodType<PricesWithPartialRelations> = PricesSchema.merge(
  z
    .object({
      products: z.lazy(() => ProductsPartialWithRelationsSchema).nullable(),
      subscriptions: z.lazy(() => SubscriptionsPartialWithRelationsSchema).array(),
    })
    .partial(),
);

export default PricesSchema;
