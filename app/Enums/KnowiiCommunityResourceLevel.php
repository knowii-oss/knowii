<?php

namespace App\Enums;

enum KnowiiCommunityResourceLevel: string
{
  case Beginner = "beginner";
  case Intermediate = "intermediate";
  case Advanced = "advanced";
  case Expert = "expert";
  case Unknown = "unknown";

  public static function toCommaSeparatedString(): string
  {
    return implode(',', array_column(self::cases(), 'value'));
  }

  public static function toStringArray(): array
  {
    return array_column(self::cases(), 'value');
  }
}
