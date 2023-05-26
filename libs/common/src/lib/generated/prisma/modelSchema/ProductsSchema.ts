import { z } from 'zod';
import { NullableJsonValue } from '../inputTypeSchemas/NullableJsonValue';
import type { NullableJsonInput } from '../inputTypeSchemas/transformJsonNull';
import type { PricesWithRelations } from './PricesSchema';
import type { PricesPartialWithRelations } from './PricesSchema';
import type { PricesOptionalDefaultsWithRelations } from './PricesSchema';
import type { SubscriptionsWithRelations } from './SubscriptionsSchema';
import type { SubscriptionsPartialWithRelations } from './SubscriptionsSchema';
import type { SubscriptionsOptionalDefaultsWithRelations } from './SubscriptionsSchema';
import { PricesWithRelationsSchema } from './PricesSchema';
import { PricesPartialWithRelationsSchema } from './PricesSchema';
import { PricesOptionalDefaultsWithRelationsSchema } from './PricesSchema';
import { SubscriptionsWithRelationsSchema } from './SubscriptionsSchema';
import { SubscriptionsPartialWithRelationsSchema } from './SubscriptionsSchema';
import { SubscriptionsOptionalDefaultsWithRelationsSchema } from './SubscriptionsSchema';

/////////////////////////////////////////
// PRODUCTS SCHEMA
/////////////////////////////////////////

export const ProductsSchema = z.object({
  id: z.string().uuid(),
  active: z.boolean().nullable(),
  name: z.string().nullable(),
  description: z.string().nullable(),
  image: z.string().nullable(),
  metadata: NullableJsonValue.optional(),
});

export type Products = z.infer<typeof ProductsSchema>;

/////////////////////////////////////////
// PRODUCTS PARTIAL SCHEMA
/////////////////////////////////////////

export const ProductsPartialSchema = ProductsSchema.partial();

export type ProductsPartial = z.infer<typeof ProductsPartialSchema>;

/////////////////////////////////////////
// PRODUCTS OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const ProductsOptionalDefaultsSchema = ProductsSchema.merge(
  z.object({
    id: z.string().uuid().optional(),
  }),
);

export type ProductsOptionalDefaults = z.infer<typeof ProductsOptionalDefaultsSchema>;

/////////////////////////////////////////
// PRODUCTS RELATION SCHEMA
/////////////////////////////////////////

export type ProductsRelations = {
  prices: PricesWithRelations[];
  subscriptions: SubscriptionsWithRelations[];
};

export type ProductsWithRelations = Omit<z.infer<typeof ProductsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & ProductsRelations;

export const ProductsWithRelationsSchema: z.ZodType<ProductsWithRelations> = ProductsSchema.merge(
  z.object({
    prices: z.lazy(() => PricesWithRelationsSchema).array(),
    subscriptions: z.lazy(() => SubscriptionsWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// PRODUCTS OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type ProductsOptionalDefaultsRelations = {
  prices: PricesOptionalDefaultsWithRelations[];
  subscriptions: SubscriptionsOptionalDefaultsWithRelations[];
};

export type ProductsOptionalDefaultsWithRelations = Omit<z.infer<typeof ProductsOptionalDefaultsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & ProductsOptionalDefaultsRelations;

export const ProductsOptionalDefaultsWithRelationsSchema: z.ZodType<ProductsOptionalDefaultsWithRelations> =
  ProductsOptionalDefaultsSchema.merge(
    z.object({
      prices: z.lazy(() => PricesOptionalDefaultsWithRelationsSchema).array(),
      subscriptions: z.lazy(() => SubscriptionsOptionalDefaultsWithRelationsSchema).array(),
    }),
  );

/////////////////////////////////////////
// PRODUCTS PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type ProductsPartialRelations = {
  prices?: PricesPartialWithRelations[];
  subscriptions?: SubscriptionsPartialWithRelations[];
};

export type ProductsPartialWithRelations = Omit<z.infer<typeof ProductsPartialSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & ProductsPartialRelations;

export const ProductsPartialWithRelationsSchema: z.ZodType<ProductsPartialWithRelations> = ProductsPartialSchema.merge(
  z.object({
    prices: z.lazy(() => PricesPartialWithRelationsSchema).array(),
    subscriptions: z.lazy(() => SubscriptionsPartialWithRelationsSchema).array(),
  }),
).partial();

export type ProductsOptionalDefaultsWithPartialRelations = Omit<z.infer<typeof ProductsOptionalDefaultsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & ProductsPartialRelations;

export const ProductsOptionalDefaultsWithPartialRelationsSchema: z.ZodType<ProductsOptionalDefaultsWithPartialRelations> =
  ProductsOptionalDefaultsSchema.merge(
    z
      .object({
        prices: z.lazy(() => PricesPartialWithRelationsSchema).array(),
        subscriptions: z.lazy(() => SubscriptionsPartialWithRelationsSchema).array(),
      })
      .partial(),
  );

export type ProductsWithPartialRelations = Omit<z.infer<typeof ProductsSchema>, 'metadata'> & {
  metadata?: NullableJsonInput;
} & ProductsPartialRelations;

export const ProductsWithPartialRelationsSchema: z.ZodType<ProductsWithPartialRelations> = ProductsSchema.merge(
  z
    .object({
      prices: z.lazy(() => PricesPartialWithRelationsSchema).array(),
      subscriptions: z.lazy(() => SubscriptionsPartialWithRelationsSchema).array(),
    })
    .partial(),
);

export default ProductsSchema;
