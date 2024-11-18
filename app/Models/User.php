<?php

namespace App\Models;

use App\Traits\HasCommunities;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Parables\Cuid\GeneratesCuid;
use TaylorNetwork\UsernameGenerator\FindSimilarUsernames;
use TaylorNetwork\UsernameGenerator\GeneratesUsernames;

class User extends Authenticatable implements MustVerifyEmail
{
    use FindSimilarUsernames;

    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;
    use GeneratesUsernames;
    use HasApiTokens;
    use HasCommunities;
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'cuid',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    final public function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's profile.
     */
    final public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Detach the user profile from this account.
     */
    final public function detachProfile(): void
    {
        $profile = $this->profile;
        if ($profile) {
            $profile->user_id = null;
            // WARNING: the username and email fields should NOT be cleared when detaching the user profile
            // This will enable re-claiming past user profiles
            $profile->save();
        }
    }

    /**
     * Get the communities that the user owns.
     */
    final public function ownedCommunities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Community::class, 'owner_id');
    }

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
