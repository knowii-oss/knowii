<?php

namespace App\Models;

use App\Events\Communities\CommunityCreated;
use App\Events\Communities\CommunityDeleted;
use App\Events\Communities\CommunityUpdated;
use App\Knowii;
use App\KnowiiCommunityVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Laravel\Jetstream\Jetstream;
use Parables\Cuid\GeneratesCuid;

class Community extends Model
{
  use HasFactory;

  // Automatically generate cuid2 for the model
  // Reference: https://github.com/Parables/laravel-cuid2
  use GeneratesCuid;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'name',
    'description',
    'visibility',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'id',
    'user_id',
  ];

  /**
   * The event map for the model.
   *
   * @var array<string, class-string>
   */
  protected $dispatchesEvents = [
    'created' => CommunityCreated::class,
    'updated' => CommunityUpdated::class,
    'deleted' => CommunityDeleted::class,
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'visibility' => KnowiiCommunityVisibility::class,
    ];
  }

  /**
   * Get the owner.
   *
   * @return BelongsTo
   */
  public function owner()
  {
    return $this->belongsTo(Jetstream::userModel(), 'user_id');
  }

  /**
   * Get all of the users including the owner.
   *
   * @return Collection
   */
  public function allUsers()
  {
    return $this->users->merge([$this->owner]);
  }

  /**
   * Get all of the users that belong to the community.
   *
   * @return BelongsToMany
   */
  public function users()
  {
    return $this->belongsToMany(Jetstream::userModel(), Knowii::communityMembershipModel())
      ->withPivot('role')
      ->withTimestamps()
      ->as('membership');
  }

  /**
   * Determine if the given user belongs to the community.
   *
   * @param User $user
   * @return bool
   */
  public function hasUser(User $user): bool
  {
    return $this->users->contains($user) || $user->ownsCommunity($this);
  }

  /**
   * Determine if the given email address belongs to a user in the community.
   *
   * @param  string  $email
   * @return bool
   */
  public function hasUserWithEmail(string $email): bool
  {
    return $this->allUsers()->contains(function ($user) use ($email) {
      return $user->email === $email;
    });
  }

  /**
   * Determine if the given user has the given permission in the community.
   *
   * @param User $user
   * @param  string  $permission
   * @return bool
   */
  public function userHasPermission(User $user, string $permission): bool
  {
    return $user->hasCommunityPermission($this, $permission);
  }

  /**
   * Get all of the pending user invitations for the community.
   *
   * @return HasMany
   */
  public function communityInvitations()
  {
    return $this->hasMany(Knowii::communityInvitationModel());
  }

  /**
   * Remove the given user from the community.
   *
   * @param User $user
   * @return void
   */
  public function removeUser(User $user): void
  {
    $this->users()->detach($user);
  }

  /**
   * Purge all of the community's resources.
   *
   * @return void
   */
  public function purge(): void
  {
    $this->users()->detach();
    $this->delete();
  }
}
