<?php

namespace App\Models;

use App\CommunityInvitation as KnowiiCommunityInvitation;
use App\Enums\KnowiiCommunityMemberRole;
use Parables\Cuid\GeneratesCuid;

class CommunityInvitation extends KnowiiCommunityInvitation
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cuid',
        'email',
        'role',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'role' => KnowiiCommunityMemberRole::class,
        ];
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
