<?php

namespace App;

class Constants
{
  public static int $MIN_LENGTH_USER_USERNAME = 3;
  public static int $MAX_LENGTH_USER_USERNAME = 36;
  public static string $ALLOWED_USER_USERNAME_INPUT_CHARACTERS = 'a-zA-Z-@\s '; // Used to allow providing user name as input to derive the username. There, spaces are allowed
  public static string $ALLOWED_USER_USERNAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-@]+$/';
  public static int $MAX_LENGTH_USER_NAME = 255;
  public static int $MAX_LENGTH_USER_EMAIL = 255;
  public static int $MAX_LENGTH_SLUG = 128;
  public static int $MIN_LENGTH_COMMUNITY_NAME = 3;
  public static int $MAX_LENGTH_COMMUNITY_NAME = 64;
  public static int $MAX_LENGTH_COMMUNITY_DESCRIPTION = 255;
  public static string $ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-@\' ]+$/';
}
