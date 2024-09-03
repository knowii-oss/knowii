import { generateSlug } from './generate-slug';

describe('generateSlug', () => {
  it('should return an empty string for an empty string', () => {
    expect(generateSlug('')).toEqual('');
  });

  it('should return a valid slug given a simple string', () => {
    expect(generateSlug('foo')).toEqual('foo');
  });

  it('should return a valid slug given a composed string', () => {
    expect(generateSlug('foo-bar')).toEqual('foo-bar');
    expect(generateSlug('foo bar')).toEqual('foo-bar');
  });

  it('should remove special characters', () => {
    expect(generateSlug('foo.bar')).toEqual('foobar');
    expect(generateSlug('foo*bar')).toEqual('foobar');
    expect(generateSlug('foo+bar')).toEqual('foobar');
    expect(generateSlug('foo~bar')).toEqual('foobar');
    expect(generateSlug('foo(bar')).toEqual('foobar');
    expect(generateSlug('foo)bar')).toEqual('foobar');
    expect(generateSlug('foo#bar')).toEqual('foobar');
    expect(generateSlug("foo'bar")).toEqual('foobar');
    expect(generateSlug('foo"bar')).toEqual('foobar');
    expect(generateSlug('foo:bar')).toEqual('foobar');
    expect(generateSlug('foo@bar')).toEqual('foobar');
  });

  it('should replace _ by -', () => {
    // for usernames!
    expect(generateSlug('foo_bar')).toEqual('foo-bar');
    expect(generateSlug('little_poney_1428')).toEqual('little-poney-1428');
  });
});
