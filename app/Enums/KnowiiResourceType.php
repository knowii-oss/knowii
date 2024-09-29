<?php

namespace App\Enums;

enum KnowiiResourceType: string
{
  case Article = "article";

  public static function toCommaSeparatedString(): string
  {
    return implode(',', array_column(self::cases(), 'value'));
  }

  public static function toStringArray(): array
  {
    return array_column(self::cases(), 'value');
  }
}
