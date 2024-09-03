/**
 * Then argument of a PromiseLike
 */
export type ThenArg<T> = T extends PromiseLike<infer U> ? U : T;

/**
 * Get the type of the value, that the Promise holds.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type PromiseType<T extends PromiseLike<any>> = T extends PromiseLike<infer U> ? U : T;
/**
 * Get the return type of a function which returns a Promise.
 */
export type PromiseReturnType<
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  T extends (...args: any) => Promise<any>,
> = PromiseType<ReturnType<T>>;

/**
 * Modify an existing type.
 * Reference: https://stackoverflow.com/questions/41285211/overriding-interface-property-type-defined-in-typescript-d-ts-file
 */
export type Modify<T, R> = Omit<T, keyof R> & R;

/**
 * Make specific properties of the base type optional
 * Reference: https://stackoverflow.com/questions/43159887/make-a-single-property-optional-in-typescript
 */
export type PartialBy<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>;

/**
 * Check if the passed argument is a function
 * @param fn the thing to check
 * @returns true if the passed argument is a function
 */
export const isFunction = (fn: unknown): boolean => typeof fn === 'function';

/**
 * Ensure that something is not null
 * @param value the value to check
 */
export function isNotNull<T>(value: T | null): value is T {
  return value !== null;
}

/**
 * Ensure that something is not null or undefined
 * @param value the value to check
 */
export function isNotNullOrUndefined<TValue>(value: TValue | null | undefined): value is TValue {
  return value !== null && value !== undefined;
}

/**
 * A constructor function
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Constructor<T> = new (...args: any[]) => T;

/**
 * Property of the given type
 * @param name the name of the property
 */
export const propertyOf = <SomeType>(name: keyof SomeType) => name;

/**
 * Properties of the given type
 */
export const propertiesOf =
  <SomeType>() =>
  <T extends keyof SomeType>(name: T): T =>
    name;

/**
 * Alternative name
 */
export const nameOf = propertyOf;

/**
 * Extract the type of a property
 */
export type PropType<TObj, TProp extends keyof TObj> = TObj[TProp];

/**
 * Returns a given class type
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type ClassType<T = any> = new (...args: any[]) => T;

/**
 * Something that has an unsubscribe function.
 */
export interface SubscriptionLike {
  unsubscribe(): void;
}

/**
 * Remove never properties
 */
export type RemoveNeverProps<T> = {
  [K in Exclude<
    keyof T,
    {
      // eslint-disable-next-line @typescript-eslint/ban-types
      [P in keyof T]: T[P] extends Function ? P : never;
    }[keyof T]
  >]: T[K];
};

/**
 * Include a specific property
 */
// eslint-disable-next-line @typescript-eslint/ban-types
export type IncludeProp<T extends object, E> = RemoveNeverProps<{ [K in keyof T]: T[K] extends E ? T[K] : never }>;

export type OrNull<T> = T | null;

/**
 * Make sure that the passed element is not null, and return it. If null, then throw
 * @param input
 */
export const assertNotNull = <T>(input?: T | null): T => {
  if (input !== null && input !== undefined) {
    return input;
  }
  throw new Error('Object should not be null'); // This will result in a 500
};

/**
 * Pick fields from a type and make those required
 */
export type RequiredPick<T, K extends keyof T> = Required<Pick<T, K>>;
