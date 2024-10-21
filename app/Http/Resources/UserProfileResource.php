<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserProfileResource extends AbstractKnowiiJsonResource
{

  /**
   * Transform the resource into an array.
   *
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    return [
      'cuid' => $this->cuid,
      'name' => $this->name,
      'username' => $this->username ?? '',
      // FIXME: should the profile photo path be exposed to the client?
      // FIXME the profile photo url does not exist (?)
      'profile_photo_path' => $this->profile_photo_path,
      'profile_photo_url' => $this->profile_photo_url,
      'bio' => $this->bio ?? '', // Ensures the returned value is never null,
      'location' => $this->location ?? '', // Ensures the returned value is never null,
      'phone' => $this->phone ?? '', // Ensures the returned value is never null,
      'social_link_website' => $this->social_link_website ?? '', // Ensures the returned value is never null,
      'social_link_newsletter' => $this->social_link_newsletter ?? '', // Ensures the returned value is never null,
      'social_link_x' => $this->social_link_x ?? '', // Ensures the returned value is never null,
      'social_link_mastodon' => $this->social_link_mastodon ?? '', // Ensures the returned value is never null,
      'social_link_bluesky' => $this->social_link_bluesky ?? '', // Ensures the returned value is never null,
      'social_link_threads_dot_net' => $this->social_link_threads_dot_net ?? '', // Ensures the returned value is never null,
      'social_link_linkedin' => $this->social_link_linkedin ?? '', // Ensures the returned value is never null,
      'social_link_facebook' => $this->social_link_facebook ?? '', // Ensures the returned value is never null,
      'social_link_instagram' => $this->social_link_instagram ?? '', // Ensures the returned value is never null,
      'social_link_reddit' => $this->social_link_reddit ?? '', // Ensures the returned value is never null,
      'social_link_medium' => $this->social_link_medium ?? '', // Ensures the returned value is never null,
      'social_link_substack' => $this->social_link_substack ?? '', // Ensures the returned value is never null,
      'social_link_hackernews' => $this->social_link_hackernews ?? '', // Ensures the returned value is never null,
      'social_link_hashnode' => $this->social_link_hashnode ?? '', // Ensures the returned value is never null,
      'social_link_dev_dot_to' => $this->social_link_dev_dot_to ?? '', // Ensures the returned value is never null,
      'social_link_youtube' => $this->social_link_youtube ?? '', // Ensures the returned value is never null,
      'social_link_tiktok' => $this->social_link_tiktok ?? '', // Ensures the returned value is never null,
      'social_link_twitch' => $this->social_link_twitch ?? '', // Ensures the returned value is never null,
      'social_link_gumroad' => $this->social_link_gumroad ?? '', // Ensures the returned value is never null,
      'social_link_buymeacoffee' => $this->social_link_buymeacoffee ?? '', // Ensures the returned value is never null,
      'social_link_patreon' => $this->social_link_patreon ?? '', // Ensures the returned value is never null,
      'social_link_producthunt' => $this->social_link_producthunt ?? '', // Ensures the returned value is never null,
      'social_link_github' => $this->social_link_github ?? '', // Ensures the returned value is never null,
      'social_link_gitlab' => $this->social_link_gitlab ?? '', // Ensures the returned value is never null,
    ];
  }
}
