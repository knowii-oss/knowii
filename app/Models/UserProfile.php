<?php

namespace App\Models;

use App\Constants;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasProfilePhoto;
use Parables\Cuid\GeneratesCuid;

/**
 * App\Models\UserProfile
 *
 * @property int $id
 * @property string $cuid
 * @property int|null $user_id
 * @property string|null $username
 * @property string $name
 * @property string|null $email
 * @property string|null $profile_photo_path
 * @property string|null $bio
 * @property string|null $location
 * @property string|null $phone
 * @property string|null $social_link_website
 * @property string|null $social_link_newsletter
 * @property string|null $social_link_x
 * @property string|null $social_link_mastodon
 * @property string|null $social_link_bluesky
 * @property string|null $social_link_threads_dot_net
 * @property string|null $social_link_linkedin
 * @property string|null $social_link_facebook
 * @property string|null $social_link_instagram
 * @property string|null $social_link_reddit
 * @property string|null $social_link_medium
 * @property string|null $social_link_substack
 * @property string|null $social_link_hackernews
 * @property string|null $social_link_hashnode
 * @property string|null $social_link_dev_dot_to
 * @property string|null $social_link_youtube
 * @property string|null $social_link_tiktok
 * @property string|null $social_link_twitch
 * @property string|null $social_link_gumroad
 * @property string|null $social_link_buymeacoffee
 * @property string|null $social_link_patreon
 * @property string|null $social_link_producthunt
 * @property string|null $social_link_github
 * @property string|null $social_link_gitlab
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read string $profile_photo_url
 *
 * @method static \Database\Factories\UserProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereCuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereProfilePhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkBluesky($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkBuymeacoffee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkDevDotTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkFacebook($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkGithub($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkGitlab($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkGumroad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkHackernews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkHashnode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkInstagram($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkLinkedin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkMastodon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkMedium($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkNewsletter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkPatreon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkProducthunt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkReddit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkSubstack($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkThreadsDotNet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkTiktok($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkTwitch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkX($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkYoutube($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereUsername($value)
 *
 * @mixin \Eloquent
 */
class UserProfile extends Authenticatable implements MustVerifyEmail
{
    // Automatically generate cuid2 for the model
    // Reference: https://github.com/Parables/laravel-cuid2
    use GeneratesCuid;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;

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
     * @var list<string>
     */
    protected $fillable = [
        // WARNING: When new fields are added, this list should be updated!
        'user_id',
        'cuid',
        'name',
        'username',
        'email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'email', // WARNING: the usernames MUST NOT be exposed
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
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

    final public function getRouteKeyName(): string
    {
        return 'cuid';
    }
}
