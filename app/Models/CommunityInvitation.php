<?php

namespace App\Models;

use App\CommunityInvitation as KnowiiCommunityInvitation;
use App\Enums\KnowiiCommunityMemberRole;
use Carbon\Carbon;
use Parables\Cuid\GeneratesCuid;

/**
 * Community invitations
 *
 * @property int $id
 * @property string $cuid
 * @property int $community_id
 * @property string $email
 * @property KnowiiCommunityMemberRole $role
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \App\Models\Community $community
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereCommunityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityInvitation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
