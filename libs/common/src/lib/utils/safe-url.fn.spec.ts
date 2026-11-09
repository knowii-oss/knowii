import { safeHttpUrl } from './safe-url.fn';

describe('safeHttpUrl', () => {
  it('keeps absolute http and https URLs', () => {
    expect(safeHttpUrl('https://orcid.org/0000-0002-1825-0097')).toBe('https://orcid.org/0000-0002-1825-0097');
    expect(safeHttpUrl(' http://example.com ')).toBe('http://example.com/');
  });

  it('refuses other schemes, relative values and empty values', () => {
    expect(safeHttpUrl('javascript:alert(1)')).toBeNull();
    expect(safeHttpUrl('JaVaScRiPt:alert(1)')).toBeNull();
    expect(safeHttpUrl('data:text/html,hi')).toBeNull();
    expect(safeHttpUrl('example.com')).toBeNull();
    expect(safeHttpUrl('')).toBeNull();
    expect(safeHttpUrl(null)).toBeNull();
  });
});
