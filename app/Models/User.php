<?php

namespace App\Models;

use App\Constants;
use App\Traits\HasCommunities;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Parables\Cuid\GeneratesCuid;
use TaylorNetwork\UsernameGenerator\FindSimilarUsernames;
use TaylorNetwork\UsernameGenerator\GeneratesUsernames;

class User extends Authenticatable implements MustVerifyEmail
{
  use HasApiTokens;
  use HasFactory;
  use HasProfilePhoto;
  use HasCommunities;
  use Notifiable;
  use TwoFactorAuthenticatable;
  use FindSimilarUsernames;
  use GeneratesUsernames;

  // Automatically generate cuid2 for the model
  // Reference: https://github.com/Parables/laravel-cuid2
  use GeneratesCuid;

  /**
   * Initialize the model by setting up fillable attributes for social media links.
   */
  protected static function boot():void
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
    'password',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'communities',
    'id',
    'password',
    'remember_token',
    'two_factor_recovery_codes',
    'two_factor_secret',
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
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password' => 'hashed',
    ];
  }

  public function ownedCommunities(): \Illuminate\Database\Eloquent\Relations\HasMany
  {
    return $this->hasMany(Community::class, 'owner_id');
  }
}
