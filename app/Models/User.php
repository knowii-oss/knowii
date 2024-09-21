<?php

namespace App\Models;

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
        'social_link_x',
        'social_link_website',
        'social_link_newsletter',
        'social_link_mastodon',
        'social_link_bluesky',
        'social_link_threads_dot_net',
        'social_link_linkedin',
        'social_link_facebook',
        'social_link_instagram',
        'social_link_reddit',
        'social_link_medium',
        'social_link_substack',
        'social_link_hackernews',
        'social_link_hashnode',
        'social_link_dev_dot_to',
        'social_link_youtube',
        'social_link_tiktok',
        'social_link_twitch',
        'social_link_gumroad',
        'social_link_buymeacoffee',
        'social_link_patreon',
        'social_link_producthunt',
        'social_link_github',
        'social_link_gitlab',
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
}
