import { cleanCommunityName } from './clean-community-name';

describe('cleanCommunityName', () => {
  it('should remove whitespace', () => {
    expect(cleanCommunityName('  ')).toEqual('');
  });

  it('should replace diacritics', () => {
    expect(cleanCommunityName('áàâäãéèëêíìïîóòöôõúùüûñçăşţ')).toEqual('aaaaaeeeeiiiiooooouuuuncast');
  });

  it('should remove characters that are not ascii, space or -', () => {
    expect(cleanCommunityName('@_]{^%_- -')).toEqual('- -');
  });

  it('should remove trailing whitespace', () => {
    expect(cleanCommunityName('abc   ')).toEqual('abc');
    expect(cleanCommunityName('   abc')).toEqual('abc');
  });
});
