export function getBaseURL() {
  const url = process?.env?.NEXT_PUBLIC_SITE_URL || process?.env?.VERCEL_URL || 'http://localhost:3000';
  return url.includes('http') ? url : `https://${url}`;
}

export function formatPrice({ locale, currency, amount }: { locale?: string; currency?: string; amount?: number }) {
  return new Intl.NumberFormat(locale ?? 'en-US', {
    style: 'currency',
    currency: currency ?? 'USD',
    minimumFractionDigits: 0,
  }).format((amount ?? 0) / 100);
}
