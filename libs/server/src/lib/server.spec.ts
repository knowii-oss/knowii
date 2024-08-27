import { server } from './server';

// We need to mock this package otherwise the tests fail because supabase-admin.ts tries to create a database connection automatically
jest.mock('@supabase/supabase-js', () => ({
  __esModule: true,
  createClient: jest.fn(),
}));

describe('server', () => {
  it('should work', () => {
    expect(server()).toEqual('server');
  });
});
