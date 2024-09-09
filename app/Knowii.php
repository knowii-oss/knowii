<?php

namespace App;

use App\Models\Community;

class Knowii
{
  /**
   * The community model.
   *
   * @var string
   */
  public static string $communityModel = 'App\\Models\\Community';

  /**
   * The community invitation model.
   *
   * @var string
   */
  public static string $communityInvitationModel = 'App\\Models\\CommunityInvitation';


  /**
   * Get a new instance of the community model.
   *
   * @return Community
   */
  public static function newCommunityModel(): Community
  {
      return new Community();
  }

  /**
   * Get the name of the community model used by the application.
   *
   * @return string
   */
  public static function communityModel(): string
  {
    return static::$communityModel;
  }

  /**
   * Get the name of the community invitation model used by the application.
   *
   * @return string
   */
  public static function communityInvitationModel(): string
  {
    return static::$communityInvitationModel;
  }
}
