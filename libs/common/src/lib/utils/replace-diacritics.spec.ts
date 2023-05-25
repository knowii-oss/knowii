import { replaceDiacritics } from './replace-diacritics';

describe('replaceDiacritics', () => {
  it('should replace diacritics', () => {
    expect(replaceDiacritics('áàâäãéèëêíìïîóòöôõúùüûñçăşţ')).toEqual('aaaaaeeeeiiiiooooouuuuncast');
  });

  it('should keep other characters intact', () => {
    expect(replaceDiacritics('a %$_[] b')).toEqual('a %$_[] b');
  });
});
