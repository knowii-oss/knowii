/**
 * The texts of the error page (#1574). The status code stays the HTTP status of the answer: the page names the case in
 * words, without the number.
 */

export type ErrorStatus = 403 | 404 | 500 | 503;

export interface ErrorTexts {
  /**
   * The heading of the page and the title of the document
   */
  title: string;
  /**
   * What happened, in one line
   */
  reason: string;
}

export const ERROR_TEXTS: Record<ErrorStatus, ErrorTexts> = {
  403: { title: 'No access', reason: 'You do not have access to this page.' },
  404: { title: 'Page not found', reason: 'This page does not exist, or it moved.' },
  500: { title: 'Server error', reason: 'Something went wrong on our side. Try again in a minute.' },
  503: { title: 'Under maintenance', reason: 'Knowii is under maintenance. Come back in a few minutes.' },
};

/**
 * The texts of a status. The server renders the page only for the four statuses above: any other one gets the texts of 500
 */
export function errorTexts(status: number): ErrorTexts {
  return status === 403 || status === 404 || status === 503 ? ERROR_TEXTS[status] : ERROR_TEXTS[500];
}

/**
 * The button for a signed-in person: the dashboard, where they see their communities (the same target as the page without
 * access, #1561)
 */
export const ERROR_MEMBER_LINK = 'Go to my communities';

/**
 * The button for a visitor
 */
export const ERROR_VISITOR_LINK = 'Go to the home page';
