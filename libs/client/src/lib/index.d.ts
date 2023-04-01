// Internationalization (i18n) strong typing
// Declaring this interface provides type safety for message keys
// FIXME avoid duplication if possible
type Messages = typeof import('../../../../apps/knowii/messages/en.json');
declare interface IntlMessages extends Messages {}
