<?php

namespace App;

class Constants
{
  public static int $MIN_LENGTH_COMMUNITY_NAME = 3;
  public static int $MAX_LENGTH_COMMUNITY_NAME = 64;
  public static int $MAX_LENGTH_COMMUNITY_DESCRIPTION = 255;
  public static string $ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-@\' ]+$/';
}
