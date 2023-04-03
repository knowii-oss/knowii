/* eslint-disable @typescript-eslint/no-explicit-any */
declare module '*.svg' {
  const content: any;
  export const ReactComponent: any;
  export default content;
}

// Internationalization (i18n) strong typing
// Declaring this interface provides type safety for message keys
type Messages = typeof import('../../libs/common/src/lib/messages/en.json');
declare interface IntlMessages extends Messages {}
