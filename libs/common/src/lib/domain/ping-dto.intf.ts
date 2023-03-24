import { propertiesOf } from '../utils/type.utils';

export interface PingDto {
  message: string;
  timestamp: string;
}
export const pingDtoProperties = propertiesOf<PingDto>();
