<?php

namespace App\Models;

use App\Constants;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasProfilePhoto;
use Parables\Cuid\GeneratesCuid;

class UserProfile extends Authenticatable implements MustVerifyEmail
{
  use HasFactory;
  use HasProfilePhoto;
  use Notifiable;

  // Automatically generate cuid2 for the model
  // Reference: https://github.com/Parables/laravel-cuid2
  use GeneratesCuid;

  /**
   * Initialize the model by setting up fillable attributes for social media links.
   */
  final public static function boot(): void
  {
    parent::boot();

    static::creating(static function ($model) {
      // Add all social media link properties to the list of fillable fields
      foreach (Constants::$SOCIAL_MEDIA_LINK_PROPERTIES as $property) {
        $model->fillable[] = $property;
      }
    });
  }

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    // WARNING: When new fields are added, this list should be updated!
    'name',
    'username',
    'email',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'id',
    'email', // WARNING: the usernames MUST NOT be exposed
  ];

  /**
   * The accessors to append to the model's array form.
   *
   * @var array<int, string>
   */
  protected $appends = [
    'profile_photo_url',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  final public function casts(): array
  {
    return [
    ];
  }
}
