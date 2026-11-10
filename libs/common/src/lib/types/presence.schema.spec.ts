import { typingText } from './presence.schema';

describe('typingText (#1187)', () => {
  it('names one or two typists, and stays short for more', () => {
    expect(typingText([])).toBeNull();
    expect(typingText(['Ada'])).toBe('Ada is typing…');
    expect(typingText(['Ada', 'Bob'])).toBe('Ada and Bob are typing…');
    expect(typingText(['Ada', 'Bob', 'Cleo'])).toBe('Several people are typing…');
  });
});
