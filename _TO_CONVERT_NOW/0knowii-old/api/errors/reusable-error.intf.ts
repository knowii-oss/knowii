/**
 * An error with basic information
 */
import { ErrorType } from './error-type.intf';
import { ErrorCategory } from './error-category.intf';

export interface ReusableError {
  code: string;
  key: string;
  description: string;
  statusCode: number;
  type: ErrorType;
  category: ErrorCategory;
}
