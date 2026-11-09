/**
 * The URL when it is an absolute http or https URL, else null. Use it before putting text written by a member in an
 * href: values such as "javascript:..." must never become links.
 */
export function safeHttpUrl(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }
  try {
    const url = new URL(value.trim());
    return url.protocol === 'http:' || url.protocol === 'https:' ? url.toString() : null;
  } catch {
    return null;
  }
}
