import {
  SOCIAL_MEDIA_LINK_ICONS,
  SOCIAL_MEDIA_LINK_NAMES,
  SOCIAL_MEDIA_LINK_PROPERTIES,
  socialMediaLinkPropertySchema,
  socialMediaLinksSchema,
} from './social-media-link.schema';

describe('social media links', () => {
  it('has a name and an icon for every property', () => {
    for (const property of SOCIAL_MEDIA_LINK_PROPERTIES) {
      expect(SOCIAL_MEDIA_LINK_NAMES[property]).toBeTruthy();
      expect(SOCIAL_MEDIA_LINK_ICONS[property]).toBeTypeOf('function');
    }
    expect(Object.keys(SOCIAL_MEDIA_LINK_NAMES)).toHaveLength(SOCIAL_MEDIA_LINK_PROPERTIES.length);
    expect(Object.keys(SOCIAL_MEDIA_LINK_ICONS)).toHaveLength(SOCIAL_MEDIA_LINK_PROPERTIES.length);
  });

  it('includes the Wikipedia, Google Scholar, ORCID and Ko-fi links', () => {
    expect(SOCIAL_MEDIA_LINK_PROPERTIES).toEqual(
      expect.arrayContaining(['social_link_wikipedia', 'social_link_google_scholar', 'social_link_orcid', 'social_link_kofi']),
    );
    expect(socialMediaLinkPropertySchema.safeParse('social_link_kofi').success).toBe(true);
    expect(socialMediaLinkPropertySchema.safeParse('social_link_unknown').success).toBe(false);
  });

  it('accepts null or a string for every link', () => {
    const links = Object.fromEntries(SOCIAL_MEDIA_LINK_PROPERTIES.map((property) => [property, null]));
    expect(socialMediaLinksSchema.safeParse(links).success).toBe(true);
    expect(socialMediaLinksSchema.safeParse({ ...links, social_link_orcid: 'https://orcid.org/0000-0002-1825-0097' }).success).toBe(true);
    expect(socialMediaLinksSchema.safeParse({ ...links, social_link_orcid: 42 }).success).toBe(false);
  });
});
