<?php

namespace App;

class Constants
{
  public static int $MIN_LENGTH_USER_USERNAME = 3;
  public static int $MAX_LENGTH_USER_USERNAME = 36;
  public static string $ALLOWED_USER_USERNAME_INPUT_CHARACTERS = 'a-zA-Z-\s '; // Used to allow providing user name as input to derive the username. There, spaces are allowed
  public static string $ALLOWED_USER_USERNAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-]+$/';
  public static int $MAX_LENGTH_USER_NAME = 255;
  public static int $MAX_LENGTH_USER_EMAIL = 255;
  public static int $MIN_LENGTH_SLUG = 3;
  public static int $MAX_LENGTH_SLUG = 128;
  public static int $MIN_LENGTH_COMMUNITY_NAME = 3;
  public static int $MAX_LENGTH_COMMUNITY_NAME = 64;
  public static int $MAX_LENGTH_COMMUNITY_DESCRIPTION = 255;
  public static string $ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-@\' ]+$/';

  public static $MIN_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME = 3;
  public static $MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME = 64;
  public static $MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION = 255;
  public static string $ALLOWED_COMMUNITY_RESOURCE_COLLECTION_NAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-@\' ]+$/';

  public static array $SOCIAL_MEDIA_LINK_PROPERTIES = [
    'social_link_x',
    'social_link_website',
    'social_link_newsletter',
    'social_link_mastodon',
    'social_link_bluesky',
    'social_link_threads_dot_net',
    'social_link_linkedin',
    'social_link_facebook',
    'social_link_instagram',
    'social_link_reddit',
    'social_link_medium',
    'social_link_substack',
    'social_link_hackernews',
    'social_link_hashnode',
    'social_link_dev_dot_to',
    'social_link_youtube',
    'social_link_tiktok',
    'social_link_twitch',
    'social_link_gumroad',
    'social_link_buymeacoffee',
    'social_link_patreon',
    'social_link_producthunt',
    'social_link_github',
    'social_link_gitlab',
  ];
}
