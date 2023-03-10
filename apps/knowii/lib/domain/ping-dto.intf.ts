import { propertiesOf } from '@knowii/common';

export interface PingDto {
  message: string;
  timestamp: string;
}
export const pingDtoProperties = propertiesOf<PingDto>();
