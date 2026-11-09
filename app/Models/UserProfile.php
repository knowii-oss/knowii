<?php

namespace App\Models;

use App\Constants;
use App\Http\Resources\UserProfileResource;
use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Database\Factories\UserProfileFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasProfilePhoto;

/**
 * App\Models\UserProfile
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property ?int $user_id
 * @property ?string $username
 * @property string $name
 * @property ?string $email
 * @property ?string $profile_photo_path
 * @property ?string $bio
 * @property ?string $location
 * @property ?string $phone
 * @property ?string $social_link_website
 * @property ?string $social_link_newsletter
 * @property ?string $social_link_x
 * @property ?string $social_link_mastodon
 * @property ?string $social_link_bluesky
 * @property ?string $social_link_threads_dot_net
 * @property ?string $social_link_linkedin
 * @property ?string $social_link_facebook
 * @property ?string $social_link_instagram
 * @property ?string $social_link_reddit
 * @property ?string $social_link_medium
 * @property ?string $social_link_substack
 * @property ?string $social_link_hackernews
 * @property ?string $social_link_hashnode
 * @property ?string $social_link_dev_dot_to
 * @property ?string $social_link_youtube
 * @property ?string $social_link_tiktok
 * @property ?string $social_link_twitch
 * @property ?string $social_link_gumroad
 * @property ?string $social_link_buymeacoffee
 * @property ?string $social_link_patreon
 * @property ?string $social_link_producthunt
 * @property ?string $social_link_github
 * @property ?string $social_link_gitlab
 * @property ?string $social_link_wikipedia
 * @property ?string $social_link_google_scholar
 * @property ?string $social_link_orcid
 * @property ?string $social_link_kofi
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read ?int $notifications_count
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkGoogleScholar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkKofi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkOrcid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereSocialLinkWikipedia($value)
 * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\UserProfileResource>(class-string<TResource>|null $resourceClass = null)
 *
 * @mixin \Eloquent
 */
#[UseResource(UserProfileResource::class)]
class UserProfile extends Authenticatable implements MustVerifyEmail
{
    // Automatically generate cuid2 for the model
    use GeneratesCuid;

    /** @use HasFactory<UserProfileFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fillable = array_merge(
            $this->fillable,
            Constants::SOCIAL_MEDIA_LINK_PROPERTIES
        );

        parent::__construct($attributes);
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
