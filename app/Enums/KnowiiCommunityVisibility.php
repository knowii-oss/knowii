<?php

namespace App\Enums;

enum KnowiiCommunityVisibility: string
{
  case Personal = "personal";
  case Private = "private";
  case Public = "public";

  public static function toCommaSeparatedString(): string
  {
    return implode(',', array_column(self::cases(), 'value'));
  }

  public static function toStringArray(): array
  {
    return array_column(self::cases(), 'value');
  }
}
