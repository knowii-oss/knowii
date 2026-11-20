<?php

/*
 * Atlas unseed: deletes exactly what tools/screen-atlas/atlas-seed.php made (knowii-oss/knowii#1523).
 *
 * Run (in the container):  php artisan tinker tools/screen-atlas/atlas-unseed.php
 *
 * NOT RUN by the job that made the screen atlas: the reviewers need the data. Tested with the seed in a scratch database
 * (#1515): the seed two times, then this file two times, and no account and no community of the seed is left. Read it
 * before you run it.
 *
 * What the seed made, and how it goes:
 * 1. The communities "Atlas Garden" and "Atlas Empty" of atlas-owner@example.com: the action DeleteCommunity (the
 *    spaces, posts, comments, chat, pages, quizzes, materials, resources, events with the answers of their members,
 *    access groups, invitations, notifications, conversations and uploads go with the community, by cascade and by the
 *    file clean-up of the action).
 * 2. The accounts atlas-*@example.com: the action DeleteUser (the personal community, the profile, the sessions, the
 *    tokens, the avatar).
 * 3. The shared resource rows with an address under https://example.com/atlas/ that no community uses any more.
 * 4. The file storage/app/atlas-manifest.json.
 *
 * It does not touch: the front-end build (public/build), the screenshots, any other account or community.
 */

use App\Actions\Communities\DeleteCommunity;
use App\Actions\DeleteUser;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\User;

require_once __DIR__.'/atlas-shared.php';

(function (): void {
    // Jobs run inline, nothing is broadcast, no email leaves
    config(['queue.default' => 'sync']);
    config(['broadcasting.default' => array_key_exists('null', (array) config('broadcasting.connections')) ? 'null' : 'log']);
    config(['mail.default' => array_key_exists('array', (array) config('mail.mailers')) ? 'array' : 'log']);

    $say = static function (string $message): void {
        echo '[atlas-unseed] '.$message."\n";
    };

    // 1. The two shared communities
    $owner = User::query()->where('email', 'atlas-owner@example.com')->first();
    if ($owner !== null) {
        foreach (['Atlas Garden', 'Atlas Empty'] as $name) {
            $community = Community::query()->where('owner_id', $owner->id)->where('name', $name)->first();
            if ($community !== null) {
                app(DeleteCommunity::class)->delete($owner, $community);
                $say('community deleted: '.$name);
            }
        }
    }

    // 2. The accounts of the seed, with what is left of their content
    $emails = ['owner', 'admin', 'moderator', 'member', 'tomas', 'lena', 'new', 'unverified', '2fa'];
    // The members who answer the events (#1515): as many as the seed makes (atlas-shared.php)
    for ($index = 1; $index <= ATLAS_GROWER_COUNT; $index++) {
        $emails[] = sprintf('grower-%02d', $index);
    }
    foreach ($emails as $key) {
        $user = User::query()->where('email', 'atlas-'.$key.'@example.com')->first();
        if ($user !== null) {
            app(DeleteUser::class)->delete($user, ['delete_content' => true]);
            $say('account deleted: atlas-'.$key.'@example.com');
        }
    }

    // 3. The shared resource rows of the seed that no community uses
    $resources = Resource::query()
        ->where('url', 'like', 'https://example.com/atlas/%')
        ->whereNotIn('id', CommunityResource::query()->select('resource_id'))
        ->delete();
    $say('resource rows deleted: '.$resources);

    // 4. The manifest of the seed
    $manifest = storage_path('app/atlas-manifest.json');
    if (is_file($manifest)) {
        unlink($manifest);
        $say('manifest deleted');
    }

    $say('done');
})();
