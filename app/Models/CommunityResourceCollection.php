<?php

namespace App\Models;

use App\Events\CommunityResourceCollections\CommunityResourceCollectionCreated;
use App\Events\CommunityResourceCollections\CommunityResourceCollectionDeleted;
use App\Events\CommunityResourceCollections\CommunityResourceCollectionUpdated;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Parables\Cuid\GeneratesCuid;

class CommunityResourceCollection extends Model
{
  use HasFactory;

  // Automatically generate cuid2 for the model
  // Reference: https://github.com/Parables/laravel-cuid2
  use GeneratesCuid;

  // Automatically generate slugs
  // Reference: https://github.com/cviebrock/eloquent-sluggable
  use Sluggable;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    // WARNING: When new fields are added, this list should be updated!
    'name',
    'slug',
    'description',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'id',
    'community_id',
  ];

  /**
   * The event map for the model.
   *
   * @var array<string, class-string>
   */
  protected $dispatchesEvents = [
    'created' => CommunityResourceCollectionCreated::class,
    'updated' => CommunityResourceCollectionUpdated::class,
    'deleted' => CommunityResourceCollectionDeleted::class,
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

  /**
   * Get the community that the invitation belongs to.
   */
  final public function community(): BelongsTo
  {
    return $this->belongsTo(Community::class);
  }

//  /**
//   * Get all of the resources in the collection.
//   *
//   * @return Collection
//   */
//  public function allResources()
//  {
//    return $this->resources;
//  }

//  /**
//   * Determine if the given resource belongs to the community.
//   *
//   * @param Resource $resource
//   * @return bool
//   */
//  public function hasResource(Resource $resource): bool
//  {
//    return $this->resources->contains($resource);
//  }

//  /**
//   * Purge the collection.
//   *
//   * @return void
//   */
//  public function purge(): void
//  {
//    $this->resources()->delete();
//    $this->delete();
//  }

  /**
   * Return the sluggable configuration array for this model.
   * Reference: https://github.com/cviebrock/eloquent-sluggable
   * @return array
   */
  final public function sluggable(): array
  {
    // The slug is derived from the name (and uniqueness is ensured)
    return [
      'slug' => [
        'source' => 'name'
      ]
    ];
  }
}
