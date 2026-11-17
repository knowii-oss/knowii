import { describe, expect, it } from 'vitest';
import { pageComponents } from './page-components';

describe('pageComponents', () => {
  const paths = Object.keys(pageComponents);

  it('holds the pages that the server renders', () => {
    expect(paths).toContain('./Pages/Dashboard.tsx');
    expect(paths).toContain('./Pages/API/Index.tsx');
    expect(paths).toContain('./Pages/Spaces/Show.tsx');
  });

  it('holds no spec file', () => {
    expect(paths.filter((path) => path.includes('.spec.'))).toEqual([]);
  });

  it('holds only files of the Pages folder', () => {
    expect(paths.filter((path) => !path.startsWith('./Pages/'))).toEqual([]);
  });
});
