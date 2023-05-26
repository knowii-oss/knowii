import { z } from 'zod';

export const ProductsScalarFieldEnumSchema = z.enum(['id', 'active', 'name', 'description', 'image', 'metadata']);

export default ProductsScalarFieldEnumSchema;
