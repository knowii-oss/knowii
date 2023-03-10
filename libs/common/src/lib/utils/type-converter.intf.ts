/**
 * A conversion function to go from one type to another
 */
export type TypeConverter<FromType, ToType> = (objectToConvert: FromType) => ToType;
