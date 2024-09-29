<?php

namespace App\Models;

use App\Events\Resources\TextArticles\ResourceTextArticleCreated;
use App\Events\Resources\TextArticles\ResourceTextArticleDeleted;
use App\Events\Resources\TextArticles\ResourceTextArticleUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Parables\Cuid\GeneratesCuid;

class ResourceTextArticle extends Model
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
    'resource_id',
    'content',
    'word_count',
    'reading_time',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    'id',
    'resource_id',
  ];

  /**
   * The event map for the model.
   *
   * @var array<string, class-string>
   */
  protected $dispatchesEvents = [
    'created' => ResourceTextArticleCreated::class,
    'updated' => ResourceTextArticleUpdated::class,
    'deleted' => ResourceTextArticleDeleted::class,
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
   * Get the global resource that this text article corresponds to.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  final public function resource(): BelongsTo
  {
    return $this->belongsTo(Resource::class, 'resource_id');
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
