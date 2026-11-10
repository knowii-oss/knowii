// Generates the emoji data of the reaction picker (#1204, ADR 0032) from emojibase-data (MIT, dev dependency).
// Run after an update of emojibase-data: `node scripts/generate-emoji-data.mjs`, then commit both files:
// - apps/knowii/src/Components/Reactions/emoji-data.json: groups of [emoji, name, keywords, skin tone variants?],
//   loaded by the picker on demand (its own chunk, #1170)
// - resources/emoji/allowed.json: every emoji that a reaction accepts (App\Support\Emoji)
import { readFileSync, writeFileSync } from 'node:fs';

// Emoji versions that the current systems show (newer ones appear as empty boxes on many devices)
const MAX_VERSION = 15.1;
const GROUPS = [
  [0, 'smileys', 'Smileys and emotion'],
  [1, 'people', 'People and body'],
  [3, 'nature', 'Animals and nature'],
  [4, 'food', 'Food and drink'],
  [5, 'travel', 'Travel and places'],
  [6, 'activities', 'Activities'],
  [7, 'objects', 'Objects'],
  [8, 'symbols', 'Symbols'],
  [9, 'flags', 'Flags'],
];

const data = JSON.parse(readFileSync(new URL('../node_modules/emojibase-data/en/data.json', import.meta.url), 'utf8'));
const allowed = new Set();
const groups = GROUPS.map(([id, key, name]) => {
  const emoji = data
    .filter((entry) => entry.group === id && entry.version <= MAX_VERSION)
    .sort((a, b) => a.order - b.order)
    .map((entry) => {
      allowed.add(entry.emoji);
      // One skin tone per variant (tones 1 to 5); variants with two tones (couples) are left out
      const skins = (entry.skins ?? [])
        .filter((skin) => typeof skin.tone === 'number' && skin.version <= MAX_VERSION)
        .sort((a, b) => a.tone - b.tone);
      skins.forEach((skin) => allowed.add(skin.emoji));
      const keywords = (entry.tags ?? []).filter((tag) => !entry.label.includes(tag)).join(' ');
      return skins.length === 5
        ? [entry.emoji, entry.label, keywords, skins.map((skin) => skin.emoji)]
        : [entry.emoji, entry.label, keywords];
    });
  return { key, name, emoji };
});

writeFileSync(new URL('../apps/knowii/src/Components/Reactions/emoji-data.json', import.meta.url), JSON.stringify({ groups }));
writeFileSync(new URL('../resources/emoji/allowed.json', import.meta.url), JSON.stringify([...allowed].sort()));
console.log(`${groups.reduce((total, group) => total + group.emoji.length, 0)} emoji, ${allowed.size} accepted with the skin tones`);
