<?php

namespace App\Models;

use App\Events\CommunityResources\CommunityResourceCreated;
use App\Events\CommunityResources\CommunityResourceDeleted;
use App\Events\CommunityResources\CommunityResourceUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Parables\Cuid\GeneratesCuid;

class CommunityResource extends Model
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
    // WARNING: When new fields are added, this list should be updated!
    'slug',
    'name',
    'description',
    'resource_id',
    'community_id',
    'collection_id',
    'resource_text_article_id',
    'curator_id',
    'is_featured',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'id',
    'resource_id',
    'community_id',
    'collection_id',
    'resource_text_article_id',
    'curator_id',
  ];

  /**
   * The event map for the model.
   *
   * @var array<string, class-string>
   */
  protected $dispatchesEvents = [
    'created' => CommunityResourceCreated::class,
    'updated' => CommunityResourceUpdated::class,
    'deleted' => CommunityResourceDeleted::class,
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  final public function casts(): array
  {
    return [
      'is_featured' => 'boolean',
    ];
  }

  /**
   *  Get the community that this belongs to
   */
  final public function community(): BelongsTo
  {
    return $this->belongsTo(Community::class);
  }

  /**
   *  Get the resource that this corresponds to
   */
  final public function resource(): BelongsTo
  {
    return $this->belongsTo(Resource::class);
  }

  /**
   *  Get the collection that this corresponds to
   */
  final public function collection(): BelongsTo
  {
    return $this->belongsTo(CommunityResourceCollection::class);
  }

  /**
   *  Get the text article that this corresponds to (if any)
   */
  final public function textArticle(): BelongsTo
  {
    return $this->belongsTo(ResourceTextArticle::class, 'resource_text_article_id');
  }

  // WARNING: do not delete. This is used via load(...) (e.g., TextResourceApiController and ResourceResource
  final public function curator(): BelongsTo
  {
    return $this->belongsTo(UserProfile::class, 'curator_id');
  }

  /**
   * Purge
   *
   * @return void
   */
  final public function purge(): void
  {
    $this->delete();
  }
}
