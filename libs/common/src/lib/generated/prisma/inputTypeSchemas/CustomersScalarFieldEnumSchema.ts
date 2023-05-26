import { z } from 'zod';

export const CustomersScalarFieldEnumSchema = z.enum(['user_id', 'stripe_customer_id']);

export default CustomersScalarFieldEnumSchema;
