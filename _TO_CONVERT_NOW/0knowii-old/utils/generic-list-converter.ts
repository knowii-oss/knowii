/**
 * A generic function to convert a list of elements from one type to another
 * @param listToConvert the source list to convert
 * @param conversionFunction the function to use to convert from the source type to the target type
 * @return a new list of the target type containing the converted objects. Never returns null or undefined
 */
import { TypeConverter } from './type-converter.intf';

export function genericListConverter<FromType, ToType>(
  listToConvert: FromType[] | null,
  conversionFunction: TypeConverter<FromType | null, ToType | null>,
): ToType[] {
  const retVal: ToType[] = [];

  if (listToConvert) {
    listToConvert
      .filter((elem) => elem != null)
      .forEach((elem) => {
        const conversionResult = conversionFunction(elem);
        if (elem && conversionResult) {
          retVal.push(conversionResult);
        }
      });
  }

  return retVal;
}
