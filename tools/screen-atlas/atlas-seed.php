<?php

/*
 * Atlas seed: the data of the screen atlas for the UI review (knowii-oss/knowii#1523).
 *
 * Run (in the container):  php artisan tinker tools/screen-atlas/atlas-seed.php
 * Remove:                  php artisan tinker tools/screen-atlas/atlas-unseed.php
 *
 * Rules of this file:
 * - It calls the actions of the application wherever one exists. The few direct writes have no action and are marked
 *   "DIRECT WRITE" with the reason.
 * - It is safe to run again: every item is looked up by its natural key first (email, name, title, body, address).
 * - The last part ("volatile state") runs every time: it puts back what a visit of the application changes (which
 *   notifications are read, the date of the last visit of the member), so that the pictures are the same on each run.
 * - Everything it makes is recognizable: the accounts are atlas-*@example.com, the two shared communities are owned by
 *   atlas-owner@example.com, the resources have an address under https://example.com/atlas/.
 * - It runs only in the local environment: the accounts that it makes have a known password.
 * - Nothing leaves this machine: queue jobs run inline, broadcasts and emails go nowhere, resources are not captured.
 */

use App\Actions\AccessGroups\AddAccessGroupMember;
use App\Actions\AccessGroups\CreateAccessGroup;
use App\Actions\Chat\CreateChatMessage;
use App\Actions\Comments\CreateComment;
use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\UpdateCommunityJoinPolicy;
use App\Actions\CommunityMembers\AcceptCommunityInvitation;
use App\Actions\CommunityMembers\InviteCommunityMember;
use App\Actions\Conversations\SendDirectMessage;
use App\Actions\Conversations\StartConversation;
use App\Actions\CourseMaterials\CreateCourseMaterial;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\SetEventState;
use App\Actions\Events\UpdateEvent;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Notifications\ChooseSpaceLevels;
use App\Actions\Notifications\FollowThreads;
use App\Actions\Notifications\MarkNotificationsRead;
use App\Actions\Notifications\Notify;
use App\Actions\Pages\CreatePage;
use App\Actions\Pages\TrackLessonProgress;
use App\Actions\Posts\CreatePost;
use App\Actions\Posts\PinPost;
use App\Actions\Quizzes\AttemptQuiz;
use App\Actions\Quizzes\SaveQuiz;
use App\Actions\Reactions\ToggleReaction;
use App\Actions\Resources\CreateCommunityResource;
use App\Actions\SpaceGroups\CreateSpaceGroup;
use App\Actions\SpaceGroups\SetSpaceGroupAccessGroups;
use App\Actions\Spaces\CreateSpace;
use App\Actions\Uploads\UploadImage;
use App\Actions\Users\UpdateAvatar;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Conversation;
use App\Models\Event as EventModel;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Post;
use App\Models\QuizResult;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

(function (): void {
    // Only for a development machine: the accounts have a known password
    if (! app()->environment('local')) {
        echo "[atlas-seed] refused: this seed runs only in the local environment\n";

        return;
    }

    // Jobs run inline (the notifications are written before the script ends), nothing is broadcast, no email leaves
    config(['queue.default' => 'sync']);
    config(['broadcasting.default' => array_key_exists('null', (array) config('broadcasting.connections')) ? 'null' : 'log']);
    config(['mail.default' => array_key_exists('array', (array) config('mail.mailers')) ? 'array' : 'log']);

    $password = 'atlas-review-2026';
    $say = static function (string $message): void {
        echo '[atlas-seed] '.$message."\n";
    };

    /** @var list<Model> $history The items made in the "history" part of this run: they get older dates at the end */
    $history = [];
    $made = 0;

    // ---------------------------------------------------------------------------------------------------------------
    // Helpers: find by the natural key, else call the action
    // ---------------------------------------------------------------------------------------------------------------

    $account = static function (string $key, string $name, ?string $username, bool $verified = true, array $profile = []) use ($password, &$made): User {
        $email = 'atlas-'.$key.'@example.com';
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            // The action of the sign-up form, through its method without the bot check (the check calls an outside service)
            $user = app(CreateNewUser::class)->createAccount($name, $email, $password, $verified);
            $made++;
            if ($verified && $username !== null) {
                app(UpdateUserProfileInformation::class)->update($user, ['name' => $name, 'username' => $username, 'email' => $email, ...$profile]);
            }
        }

        return User::query()->where('email', $email)->firstOrFail();
    };

    $community = static function (User $owner, string $name, string $description, string $visibility) use (&$made): Community {
        $found = Community::query()->where('owner_id', $owner->id)->where('name', $name)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        return app(CreateCommunity::class)->create($owner, ['name' => $name, 'description' => $description, 'visibility' => $visibility]);
    };

    $join = static function (Community $community, User $inviter, User $user, string $role) use (&$made): void {
        if ($community->users()->whereKey($user->id)->exists()) {
            return;
        }
        $invitation = app(InviteCommunityMember::class)->invite($inviter, $community, ['email' => $user->email, 'role' => $role]);
        app(AcceptCommunityInvitation::class)->accept($user, $invitation);
        $made++;
    };

    $spaceGroup = static function (Community $community, User $by, string $name) use (&$made): SpaceGroup {
        $found = $community->spaceGroups()->where('name', $name)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        return app(CreateSpaceGroup::class)->create($by, $community, ['name' => $name]);
    };

    $space = static function (Community $community, User $by, SpaceGroup $group, array $input) use (&$made): Space {
        $found = $community->spaces()->where('name', $input['name'])->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        return app(CreateSpace::class)->create($by, $community, ['posting_policy' => 'members', 'visibility' => 'members', ...$input, 'space_group_cuid' => $group->cuid]);
    };

    $post = static function (User $by, Space $space, string $title, string $body, array $extra = []) use (&$made, &$history): Post {
        $found = $space->posts()->where('title', $title)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;
        $created = app(CreatePost::class)->create($by, $space, ['title' => $title, 'body' => $body, ...$extra]);
        if (Notify::isSilenced()) {
            $history[] = $created;
        }

        return $created;
    };

    $comment = static function (User $by, Post|CommunityResource $on, string $body, ?Comment $parent = null) use (&$made, &$history): Comment {
        $found = $on->comments()->where('body', $body)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;
        $created = app(CreateComment::class)->create($by, $on, ['body' => $body, ...($parent !== null ? ['parent_cuid' => $parent->cuid] : [])]);
        if (Notify::isSilenced()) {
            $history[] = $created;
        }

        return $created;
    };

    $chat = static function (User $by, Space $space, string $body, ?ChatMessage $parent = null) use (&$made, &$history): ChatMessage {
        $found = $space->chatMessages()->where('body', $body)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;
        $created = app(CreateChatMessage::class)->create($by, $space, ['body' => $body, ...($parent !== null ? ['parent_cuid' => $parent->cuid] : [])]);
        if (Notify::isSilenced()) {
            $history[] = $created;
        }

        return $created;
    };

    $react = static function (User $by, Post|Comment|ChatMessage $item, string $emoji) use (&$made): void {
        // The action is a toggle: only call it when the reaction is not there
        if ($item->reactions()->where('user_id', $by->id)->where('emoji', $emoji)->exists()) {
            return;
        }
        app(ToggleReaction::class)->toggle($by, $item, ['emoji' => $emoji]);
        $made++;
    };

    $page = static function (User $by, Space $space, string $title, string $body = '', array $extra = []) use (&$made): Page {
        $found = $space->pages()->where('title', $title)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        return app(CreatePage::class)->create($by, $space, ['title' => $title, ...($body !== '' ? ['body' => $body] : []), ...$extra]);
    };

    $material = static function (User $by, Space $space, array $input) use (&$made): void {
        if ($space->materials()->where('title', $input['title'])->exists()) {
            return;
        }
        app(CreateCourseMaterial::class)->create($by, $space, $input);
        $made++;
    };

    $resource = static function (User $by, Space $space, string $path, string $name, string $description, string $type, string $level) use (&$made): CommunityResource {
        $url = 'https://example.com/atlas/'.$path;
        $found = $space->resources()->where('name', $name)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        // capture: false, so that the application does not read the address (nothing leaves this machine)
        return app(CreateCommunityResource::class)->create($by, $space, ['url' => $url, 'name' => $name, 'description' => $description, 'type' => $type, 'level' => $level, 'capture' => false]);
    };

    // A start or an end as the API takes it: a number of days from today, at an hour of Brussels, with the offset of that day
    $eventTime = static fn (int $days, int $hour): string => CarbonImmutable::now('Europe/Brussels')->addDays($days)->setTime($hour, 0)->format('Y-m-d\TH:i:sP');

    // An event of an events space (#1514): found by its title. The owner creates every event (a start in the past is for
    // the owner and the admins) and names the host
    $event = static function (User $by, Space $space, User $host, string $title, int $days, int $hour, array $extra = []) use (&$made, $eventTime): EventModel {
        $found = $space->events()->where('title', $title)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        return app(CreateEvent::class)->create($by, $space, [
            'title' => $title,
            'starts_at' => $eventTime($days, $hour),
            'ends_at' => $eventTime($days, $hour + 1),
            'time_zone' => 'Europe/Brussels',
            'host_cuid' => $host->cuid,
            'state' => 'published',
            ...$extra,
        ]);
    };

    $accessGroup = static function (Community $community, User $by, string $name, string $description) use (&$made): AccessGroup {
        $found = $community->accessGroups()->where('name', $name)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        return app(CreateAccessGroup::class)->create($by, $community, ['name' => $name, 'description' => $description]);
    };

    $accessGroupMember = static function (User $by, AccessGroup $group, User $member) use (&$made): void {
        if ($group->members()->whereKey($member->id)->exists()) {
            return;
        }
        app(AddAccessGroupMember::class)->add($by, $group, ['member' => $member->cuid]);
        $made++;
    };

    /** A PNG picture drawn here (no file comes from outside): a gradient, a sun and a few "plants" */
    $picture = static function (string $name, int $width, int $height, array $from, array $to): UploadedFile {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $image,
                (int) ($from[0] + ($to[0] - $from[0]) * $ratio),
                (int) ($from[1] + ($to[1] - $from[1]) * $ratio),
                (int) ($from[2] + ($to[2] - $from[2]) * $ratio),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }
        $sun = imagecolorallocate($image, 255, 214, 102);
        imagefilledellipse($image, (int) ($width * 0.8), (int) ($height * 0.25), (int) ($height * 0.25), (int) ($height * 0.25), $sun);
        $leaf = imagecolorallocate($image, 34, 102, 51);
        $stem = imagecolorallocate($image, 20, 70, 35);
        for ($i = 1; $i <= 6; $i++) {
            $x = (int) ($width * $i / 7);
            $top = (int) ($height * (0.45 + 0.05 * ($i % 3)));
            imagesetthickness($image, max(2, (int) ($width / 200)));
            imageline($image, $x, $height, $x, $top, $stem);
            imagefilledellipse($image, $x, $top, (int) ($width / 12), (int) ($height / 6), $leaf);
        }
        $path = tempnam(sys_get_temp_dir(), 'atlas').'.png';
        imagepng($image, $path);

        return new UploadedFile($path, $name.'.png', 'image/png', null, true);
    };

    $upload = static function (User $by, Community $community, string $alt, int $width, int $height, array $from, array $to) use ($picture, &$made): Upload {
        $found = Upload::query()->where('community_id', $community->id)->where('alt', $alt)->first();
        if ($found !== null) {
            return $found;
        }
        $made++;

        return app(UploadImage::class)->upload($by, $community, ['file' => $picture('atlas', $width, $height, $from, $to), 'alt' => $alt]);
    };

    $image = static fn (Upload $upload): string => '!['.$upload->alt.']('.$upload->url().')';

    // ---------------------------------------------------------------------------------------------------------------
    // 1. Accounts
    // ---------------------------------------------------------------------------------------------------------------

    $zone = ['time_zone' => 'Europe/Brussels'];
    $owner = $account('owner', 'Olivia Owner', 'atlas-owner', true, [...$zone, 'bio' => 'I started Atlas Garden to share what grows on small balconies.', 'location' => 'Brussels']);
    $admin = $account('admin', 'Adam Admin', 'atlas-admin', true, $zone);
    $moderator = $account('moderator', 'Maya Moderator', 'atlas-moderator', true, [...$zone, 'bio' => 'Moderator. Ask me about compost.']);
    $member = $account('member', 'Marc Member', 'atlas-member', true, [...$zone, 'bio' => 'Tomatoes, herbs and too many seed packets.', 'location' => 'Namur']);
    $tomas = $account('tomas', 'Tomas Silva', 'atlas-tomas', true, $zone);
    $lena = $account('lena', 'Lena Fischer', 'atlas-lena', true, $zone);
    // The new member: only the sign-up and the verified address. No user name chosen, no time zone, no community joined.
    // (An account without a zone gets the zone of the browser at its first page load: the last part puts it back.)
    $newcomer = $account('new', 'Nina Newcomer', null);
    // Two accounts for two screens: the "verify your email address" page and the two-factor challenge
    $unverified = $account('unverified', 'Uma Unverified', null, false);
    $twoFactor = $account('2fa', 'Tess Twofactor', 'atlas-2fa', true, $zone);
    if ($twoFactor->two_factor_secret === null) {
        app(EnableTwoFactorAuthentication::class)($twoFactor);
        // DIRECT WRITE: the confirmation needs a code of an authenticator app
        $twoFactor->forceFill(['two_factor_confirmed_at' => now()])->save();
    }
    if (($member->getAttributes()['avatar_path'] ?? null) === null) {
        app(UpdateAvatar::class)->update($member, ['file' => $picture('avatar', 400, 400, [255, 183, 77], [216, 67, 21])]);
        $made++;
    }
    // One API token of the member, for the page "API tokens". No action: the page calls createToken() of the account,
    // like here (the same permissions as the default of the form, 90 days)
    if ($member->tokens()->where('name', 'Atlas reading script')->doesntExist()) {
        $member->createToken('Atlas reading script', ['user:read', 'communities:read', 'posts:read'], now()->addDays(90));
        $made++;
    }
    $say('accounts ready');

    // ---------------------------------------------------------------------------------------------------------------
    // 2. Communities, members, access groups, spaces
    // ---------------------------------------------------------------------------------------------------------------

    $garden = $community($owner, 'Atlas Garden', 'A community for people who grow food and flowers in small places. Seed data of the screen atlas.', 'public');
    $empty = $community($owner, 'Atlas Empty', 'One space of each type and no content: the empty states of the screen atlas.', 'private');

    foreach ([$garden, $empty] as $shared) {
        $join($shared, $owner, $admin, 'admin');
        $join($shared, $owner, $moderator, 'moderator');
        $join($shared, $owner, $member, 'member');
    }
    $join($garden, $owner, $tomas, 'member');
    $join($garden, $owner, $lena, 'member');

    // Anybody with a verified address may join Atlas Garden: the public page shows "Join" to the new member
    if ($garden->joinPolicy()->value !== 'open') {
        app(UpdateCommunityJoinPolicy::class)->update($owner, $garden, ['join_policy' => 'open']);
    }
    // One invitation that waits (the "Invitations" list of the settings)
    if (! $garden->communityInvitations()->where('email', 'atlas-invited@example.com')->exists()) {
        app(InviteCommunityMember::class)->invite($owner, $garden, ['email' => 'atlas-invited@example.com', 'role' => 'member']);
        $made++;
    }

    // Read the accounts again: their memberships changed
    [$owner, $admin, $moderator, $member, $tomas, $lena] = array_map(
        static fn (User $user): User => User::query()->findOrFail($user->id),
        [$owner, $admin, $moderator, $member, $tomas, $lena],
    );
    $garden = Community::query()->findOrFail($garden->id);
    $empty = Community::query()->findOrFail($empty->id);

    $patrons = $accessGroup($garden, $owner, 'Patrons', 'Members who support the garden. They read the Patrons spaces.');
    $alumni = $accessGroup($garden, $owner, 'Course alumni', 'Members who finished the balcony course.');
    $accessGroupMember($owner, $patrons, $moderator);
    $accessGroupMember($owner, $patrons, $tomas);
    $accessGroupMember($owner, $alumni, $member);
    $accessGroupMember($owner, $alumni, $lena);

    $startHere = $spaceGroup($garden, $owner, 'Start here');
    $together = $spaceGroup($garden, $owner, 'Together');
    $learn = $spaceGroup($garden, $owner, 'Learn');
    $patronsGroup = $spaceGroup($garden, $owner, 'Patrons');
    if (! $patronsGroup->accessGroups()->whereKey($patrons->id)->exists()) {
        // The whole group of spaces belongs to the access group "Patrons"
        app(SetSpaceGroupAccessGroups::class)->set($owner, $patronsGroup, ['access_groups' => [$patrons->cuid]]);
    }

    $welcome = $space($garden, $owner, $startHere, ['name' => 'Welcome', 'emoji' => '👋', 'type' => 'pages', 'visibility' => 'public', 'posting_policy' => 'moderators', 'description' => 'How the garden works. Read this first.']);
    $announcements = $space($garden, $owner, $startHere, ['name' => 'Announcements', 'emoji' => '📣', 'type' => 'discussion', 'visibility' => 'public', 'posting_policy' => 'moderators', 'description' => 'News from the team. Everybody can read, the team writes.']);
    $website = $space($garden, $owner, $startHere, ['name' => 'Seed catalogue', 'emoji' => '🔗', 'type' => 'link', 'url' => 'https://example.com/atlas/seed-catalogue', 'description' => 'Opens the seed catalogue in a new tab.']);
    $discussion = $space($garden, $owner, $together, ['name' => 'Garden talk', 'emoji' => '🌱', 'type' => 'discussion', 'description' => 'Questions, pictures and stories from your garden.']);
    $offTopic = $space($garden, $owner, $together, ['name' => 'Off topic', 'emoji' => '☕', 'type' => 'discussion', 'description' => 'Everything that is not about plants.']);
    $lounge = $space($garden, $owner, $together, ['name' => 'Lounge', 'emoji' => '💬', 'type' => 'chat', 'description' => 'Chat with the members who are here now.']);
    $course = $space($garden, $owner, $learn, ['name' => 'Balcony course', 'emoji' => '🎓', 'type' => 'course', 'posting_policy' => 'moderators', 'description' => 'From an empty balcony to the first harvest, step by step.']);
    $reading = $space($garden, $owner, $learn, ['name' => 'Reading list', 'emoji' => '📚', 'type' => 'resources', 'description' => 'Articles, books and videos that the members recommend.']);
    $tools = $space($garden, $owner, $learn, ['name' => 'Tools', 'emoji' => '🧰', 'type' => 'resources', 'posting_policy' => 'moderators', 'description' => 'Calculators and planners. The team curates this collection.']);
    // A public events space (#1514): a visitor reads its list and its events, without the meeting address
    $gardenEvents = $space($garden, $owner, $together, ['name' => 'Garden events', 'emoji' => '📅', 'type' => 'events', 'visibility' => 'public', 'posting_policy' => 'moderators', 'description' => 'Workshops, seed swaps and question hours. The team plans them.']);
    $patronsSpace = $space($garden, $owner, $patronsGroup, ['name' => 'Patrons lounge', 'emoji' => '🌟', 'type' => 'discussion', 'visibility' => 'groups', 'access_groups' => [$patrons->cuid], 'description' => 'Only the access group "Patrons" reads this space.']);

    $emptyGroup = $spaceGroup($empty, $owner, 'Spaces');
    $space($empty, $owner, $emptyGroup, ['name' => 'Empty discussion', 'emoji' => '🗒️', 'type' => 'discussion', 'description' => 'A discussion space without posts.']);
    $space($empty, $owner, $emptyGroup, ['name' => 'Empty chat', 'emoji' => '💬', 'type' => 'chat', 'description' => 'A chat space without messages.']);
    $space($empty, $owner, $emptyGroup, ['name' => 'Empty pages', 'emoji' => '📄', 'type' => 'pages', 'posting_policy' => 'moderators', 'description' => 'A pages space without pages.']);
    $space($empty, $owner, $emptyGroup, ['name' => 'Empty course', 'emoji' => '🎓', 'type' => 'course', 'posting_policy' => 'moderators', 'description' => 'A course space without lessons.']);
    $space($empty, $owner, $emptyGroup, ['name' => 'Empty resources', 'emoji' => '📚', 'type' => 'resources', 'description' => 'A resources space without resources.']);
    $space($empty, $owner, $emptyGroup, ['name' => 'Empty events', 'emoji' => '📅', 'type' => 'events', 'posting_policy' => 'moderators', 'description' => 'An events space without events.']);
    $space($empty, $owner, $emptyGroup, ['name' => 'Empty link', 'emoji' => '🔗', 'type' => 'link', 'url' => 'https://example.com/atlas/empty-link', 'description' => 'A link space.']);
    $say('communities, members, access groups and spaces ready');

    // ---------------------------------------------------------------------------------------------------------------
    // 3. History: the content that was there before today. No notification (Notify::silenced), older dates at the end
    // ---------------------------------------------------------------------------------------------------------------

    $at = static fn (User $user): string => '@'.$user->username;
    // DIRECT WRITE: older dates for the history of this run (no action sets a date). From 12 days ago to yesterday, in the
    // order of creation. The replies of a chat thread keep the thread consistent (last_reply_at)
    $backdate = static function () use (&$history, $garden, $say): void {
        if ($history === []) {
            return;
        }
        $count = count($history);
        $start = now()->subDays(12);
        $span = 11 * 24 * 60; // minutes, so that the last item is about one day old
        foreach ($history as $index => $item) {
            $date = $start->copy()->addMinutes((int) ($span * $index / max(1, $count - 1)));
            DB::table($item->getTable())->where('id', $item->id)->update(['created_at' => $date, 'updated_at' => $date]);
        }
        foreach (ChatMessage::query()->where('community_id', $garden->id)->whereNotNull('space_id')->whereNull('parent_id')->where('reply_count', '>', 0)->get() as $thread) {
            DB::table('chat_messages')->where('id', $thread->id)->update(['last_reply_at' => DB::table('chat_messages')->where('parent_id', $thread->id)->max('created_at')]);
        }
        $say('history: '.$count.' items got older dates');
        $history = [];
    };

    try {
        $refs = Notify::silenced(function () use ($garden, $owner, $admin, $moderator, $member, $tomas, $lena, $welcome, $announcements, $discussion, $offTopic, $lounge, $course, $reading, $tools, $patronsSpace, $post, $comment, $chat, $react, $page, $material, $resource, $upload, $image, $at): array {
            // --- Pictures
            $tomatoes = $upload($member, $garden, 'Tomato plants in pots on a balcony at sunrise', 1600, 1000, [255, 224, 178], [129, 199, 132]);
            $bedOne = $upload($lena, $garden, 'A raised bed with young lettuce', 1200, 900, [179, 229, 252], [104, 159, 56]);
            $bedTwo = $upload($lena, $garden, 'The same raised bed four weeks later', 1200, 900, [255, 236, 179], [51, 105, 30]);
            $bedThree = $upload($lena, $garden, 'A basket with the first harvest', 900, 1200, [248, 187, 208], [85, 139, 47]);
            $bedFour = $upload($lena, $garden, 'Watering can next to the raised bed', 1200, 800, [209, 196, 233], [76, 175, 80]);
            $plan = $upload($owner, $garden, 'Drawing of the planting plan for spring', 1400, 700, [255, 249, 196], [174, 213, 129]);

            // --- Pages space "Welcome": ordered pages
            $page($owner, $welcome, 'Start here', "Welcome to **Atlas Garden**.\n\nThis community is for people who grow food and flowers in small places: a balcony, a window sill, a shared yard.\n\n## What to do first\n\n1. Read the community guidelines.\n2. Say hello in *Garden talk*.\n3. Start the *Balcony course* when you have ten minutes.\n\n> A garden is a place where you learn patience.\n\nIf you are lost, ask in the *Lounge*: somebody is usually there.");
            $page($owner, $welcome, 'Community guidelines', "## Be kind\n\nEverybody was a beginner once. Answer the question that was asked.\n\n## Share what you tried\n\nA picture and two sentences help more than a long theory.\n\n## Keep it about gardens\n\nOther subjects go to *Off topic*.\n\n## What the moderators do\n\n- They move posts to the right space.\n- They remove spam and insults.\n- They answer when you ask for help.\n\n| You can | You cannot |\n| --- | --- |\n| Share links to your own blog | Sell products in the posts |\n| Ask the same question again after a week | Post the same question in three spaces |");
            $page($moderator, $welcome, 'How the spaces work', "Each space has one purpose.\n\n- **Announcements**: the team writes, everybody reads.\n- **Garden talk**: posts with comments.\n- **Lounge**: a chat, with threads.\n- **Balcony course**: lessons in order, with quizzes.\n- **Reading list** and **Tools**: links that the members collect.\n\n```text\nStart here > Welcome > this page\n```\n\nYou choose what each space tells you about: open the menu of the space and choose a level.");
            $page($moderator, $welcome, 'Frequently asked questions', "### Do I need a garden?\n\nNo. A window sill is enough to start.\n\n### Is the course free?\n\nYes. Every member can follow it.\n\n### How do I get the role of moderator?\n\nThe owner chooses the moderators. Help other members for a few months and ask.\n\n### Where do I report a problem?\n\nWrite to a moderator with a direct message.\n\n### Can I invite a friend?\n\nYes. The community is open: send them the address of the public page.\n\n".str_repeat("### Another question that members ask often\n\nThe answer is a paragraph of a normal length, so that this page is long enough to scroll on a small screen and the reader sees how a long page looks.\n\n", 6));

            // --- Discussion space "Announcements" (public)
            $rules = $post($owner, $announcements, 'Welcome to Atlas Garden', "The garden is open.\n\nRead the *Welcome* pages, then tell us what you grow.\n\n".$image($plan)."\n\nThe plan for spring is in the picture above.");
            $post($moderator, $announcements, 'The balcony course has two new lessons', "We added **Watering** and **Compost in a bucket** to the course.\n\n- Each lesson takes about ten minutes.\n- The quiz at the end of the first section tells you if you are ready for the next one.\n\nTell us what is missing.");
            $post($owner, $announcements, 'Seed swap on the first Sunday of the month', "Bring the seeds that you do not use. Take the ones that you want.\n\n**Where:** the community hall.\n**When:** 10:00 to 12:00.\n\nNo money, no registration.");

            // --- Discussion space "Garden talk": 12 posts and more
            $pinned = $post($moderator, $discussion, 'Read this before you post', "Three things make a good post:\n\n1. A title that says what you ask.\n2. A picture, if you have one.\n3. What you tried already.\n\nThank you.");
            $mine = $post($member, $discussion, 'My tomatoes have yellow leaves', "The lower leaves of my tomato plants turned yellow this week. The plants are in 20 litre pots, on a balcony that faces south.\n\n".$image($tomatoes)."\n\nI water every evening. Is that too much?");
            $long = $post($owner, $discussion, 'A full guide to the first season on a balcony', "This is the guide that I wanted when I started. It is long: read the part that you need.\n\n## 1. Light\n\nCount the hours of direct sun. Six hours and more: tomatoes, peppers, beans. Three to six hours: lettuce, herbs, radishes. Less than three: mint, parsley, patience.\n\n## 2. Pots\n\nBigger is easier. A small pot dries in one afternoon.\n\n| Plant | Smallest pot | Good pot |\n| --- | --- | --- |\n| Lettuce | 3 litres | 5 litres |\n| Pepper | 10 litres | 15 litres |\n| Tomato | 15 litres | 25 litres |\n\n## 3. Soil\n\nBuy soil without peat. Mix one part of compost with three parts of soil.\n\n> Feed the soil, not the plant.\n\n## 4. Water\n\nWater in the morning, at the foot of the plant, until the water comes out below.\n\n```text\nfinger test: push a finger 3 cm into the soil\ndry  -> water now\nmoist -> wait until tomorrow\n```\n\n## 5. Food\n\nFrom the first flowers, add liquid food every two weeks.\n\n## 6. What goes wrong\n\n- **Yellow leaves below**: too much water, or not enough food.\n- **Flowers that fall**: too hot, or too dry.\n- **Holes in the leaves**: look under the leaves in the evening.\n\n## 7. The harvest\n\nPick often. A plant that keeps its fruit stops making new ones.\n\n".str_repeat("One more paragraph of the guide, so that the post is long enough to show how a long text reads on a wide screen and on a phone. The lines must not be too long and the headings must stay readable.\n\n", 5).'Questions are welcome in the comments.');
            $gallery = $post($lena, $discussion, 'Four weeks of my raised bed in pictures', "I took a picture every week.\n\n".$image($bedOne)."\n".$image($bedTwo)."\n".$image($bedThree)."\n".$image($bedFour)."\n\nThe lettuce grew faster than I thought.");
            $video = $post($moderator, $discussion, 'Video: how to prune a tomato plant', "This short video shows where to cut.\n\nhttps://www.youtube.com/watch?v=aqz-KE-bpKQ\n\nCut the side shoots when they are smaller than a finger.");
            $post($tomas, $discussion, 'Which herbs survive the winter outside?', 'I have thyme, rosemary and basil. Which ones can stay on the balcony?');
            $mention = $post($lena, $discussion, 'Thank you for the seed swap', 'I came home with six new kinds of beans. Thank you '.$at($owner).' and '.$at($moderator).' for the work.');
            $post($tomas, $discussion, 'Short one: is rain water better?', 'Yes or no?');
            $post($admin, $discussion, 'Slugs ate my lettuce in one night', "Everything is gone. What do you use against slugs that is safe for a cat?\n\n- Copper tape?\n- Beer traps?\n- A fence?");
            $followed = $post($lena, $discussion, 'Plan for a shared compost on our street', "Four neighbours want to share one compost bin. Who did this before?\n\nWe have a place of two square metres.");
            $muted = $post($tomas, $discussion, 'Daily picture of my sunflower', 'Day 1. It is two centimetres high.');
            $post($member, $discussion, 'What I learned from my first year', "Three lessons:\n\n1. I planted too early.\n2. I planted too much.\n3. I will do it again.");
            $post($owner, $discussion, 'Poll in words: what do you want to learn next?', 'Answer with a comment: **seeds**, **compost** or **winter**.');
            $post($moderator, $discussion, 'A title that is long on purpose, to see how the list and the page show a post title of more than one line on a small screen', 'The body is short.');

            // Comments, nested replies and reactions on the post of the member
            $first = $comment($tomas, $mine, 'Yellow leaves below are normal when the plant grows. Do the new leaves look healthy?');
            $second = $comment($member, $mine, 'Yes, the top is dark green and it has flowers.', $first);
            $third = $comment($tomas, $mine, 'Then do not worry. Remove the yellow leaves and water a little less.', $second);
            $comment($member, $mine, 'I will try that this week. Thank you!', $third);
            $fourth = $comment($moderator, $mine, "Every evening is often too much in a 20 litre pot. Try the finger test:\n\n1. Push a finger 3 cm into the soil.\n2. Water only when it is dry.\n\nAlso, a south balcony is hot: a layer of straw on the soil helps.");
            $comment($lena, $mine, 'I had the same last year. It was the water.', $fourth);
            $comment($admin, $mine, 'Which kind of tomato is it?');
            $react($tomas, $mine, '👍');
            $react($lena, $mine, '👍');
            $react($moderator, $mine, '❤️');
            $react($owner, $mine, '💡');
            $react($member, $first, '👍');
            $react($member, $fourth, '💡');
            $react($lena, $fourth, '💡');
            $react($tomas, $fourth, '🎉');
            $react($member, $long, '❤️');
            $react($lena, $long, '❤️');
            $react($tomas, $long, '🎉');
            $react($admin, $gallery, '😄');
            $react($member, $gallery, '❤️');
            $comment($member, $long, 'The table of pots is what I needed. Saved.');
            $comment($tomas, $long, 'Section 4 changed how I water. Thank you.');
            $comment($owner, $gallery, 'Beautiful. Which kind of lettuce is it?');
            $comment($lena, $gallery, 'It is a mix of oak leaf and romaine.');
            $comment($member, $followed, 'We share a bin with two neighbours. The hard part is the rule about what goes in.');
            $comment($tomas, $muted, 'Day 2. Three centimetres.');
            $comment($tomas, $muted, 'Day 3. Still three centimetres.');

            // The space that the member mutes later
            $post($tomas, $offTopic, 'What do you cook with too many courgettes?', 'I have nine. Help.');
            $post($lena, $offTopic, 'A good film for a rainy Sunday', 'No gardens in it, please.');

            // The space of the access group "Patrons"
            $post($owner, $patronsSpace, 'Thank you, patrons', 'Your support pays for the seeds of the seed swap. The next order is in March.');
            $post($moderator, $patronsSpace, 'Early look: the lesson about seeds', 'The draft is ready. Tell me what is not clear.');

            // --- Chat space "Lounge": messages, reactions, mentions, two threads
            $hello = $chat($owner, $lounge, 'Good morning, garden!');
            $chat($tomas, $lounge, 'Morning. It rained all night here.');
            $chat($lena, $lounge, 'Same here. No watering today 🎉');
            $threadOne = $chat($member, $lounge, 'Does anybody have a spare tomato cage? Mine fell over in the wind.');
            $chat($tomas, $lounge, 'I have two that I do not use.', $threadOne);
            $chat($member, $lounge, 'Great! Can I pick one up on Saturday?', $threadOne);
            $chat($tomas, $lounge, 'Saturday morning works.', $threadOne);
            $chat($moderator, $lounge, "Reminder for the chat:\n\n- Use a thread for a long answer.\n- Pictures are welcome.");
            $code = $chat($admin, $lounge, "My watering plan, for those who asked:\n\n```text\nMon  tomatoes, peppers\nWed  everything\nFri  tomatoes, herbs\n```");
            $threadTwo = $chat($moderator, $lounge, 'Who comes to the seed swap on Sunday? Answer in the thread, so that I can count.');
            $chat($lena, $lounge, 'I come, with beans.', $threadTwo);
            $chat($owner, $lounge, 'I bring the tables.', $threadTwo);
            $chat($member, $lounge, 'I come too. I have too many chilli seeds.', $threadTwo);
            $chat($admin, $lounge, 'Not this time, sorry.', $threadTwo);
            $chat($lena, $lounge, $at($member).' I kept a few bean seeds for you.');
            $chat($member, $lounge, 'Thank you '.$at($lena).'!');
            $link = $chat($tomas, $lounge, 'The list of what to sow this month: https://example.com/atlas/sowing-calendar');
            $chat($owner, $lounge, 'A long message, to see how the chat wraps text. '.str_repeat('The beans go next to the fence, the lettuce goes in the shade of the beans, and the herbs go where I can reach them from the kitchen door. ', 4));
            $react($tomas, $hello, '👍');
            $react($lena, $hello, '👍');
            $react($member, $hello, '😄');
            $react($member, $code, '💡');
            $react($owner, $code, '💡');
            $react($moderator, $threadOne, '👍');
            $react($member, $link, '❤️');
            $react($lena, $threadTwo, '🎉');
            $react($member, $threadTwo, '🎉');

            // --- Course space "Balcony course": sections, lessons, a video lesson, quizzes, materials
            $page($owner, $course, 'Getting started', '', ['kind' => 'section']);
            $lessonOne = $page($owner, $course, 'Welcome to the course', "In this course you go from an empty balcony to the first harvest.\n\n## How it works\n\n- Read one lesson.\n- Do what it says, on your balcony.\n- Mark the lesson as done.\n\nYou need: three pots, a bag of soil, and seeds of lettuce and radish.");
            $lessonTwo = $page($owner, $course, 'Light and place', "Look at your balcony three times today: in the morning, at noon and in the evening.\n\nWrite down where the sun is.\n\n| Hours of sun | What grows |\n| --- | --- |\n| 6 and more | Tomatoes, peppers |\n| 3 to 6 | Lettuce, herbs |\n| Less than 3 | Mint, parsley |");
            $videoLesson = $page($moderator, $course, 'Video: fill a pot the right way', "Watch the video, then fill your first pot.\n\n1. A layer of clay balls.\n2. Soil up to 3 cm below the edge.\n3. Water until it comes out below.", ['video_url' => 'https://vimeo.com/76979871']);
            $quizOne = $page($moderator, $course, 'Quiz: the basics', 'Three questions about the first section. You need 60% to pass.');
            $page($owner, $course, 'Going further', '', ['kind' => 'section']);
            $watering = $page($owner, $course, 'Watering', "Water in the morning.\n\nWater the soil, not the leaves.\n\nWater less often, and more each time.");
            $compost = $page($moderator, $course, 'Compost in a bucket', "You can make compost on a balcony.\n\n## You need\n\n- A bucket with a lid.\n- Dry leaves or paper.\n- The peels of your kitchen.\n\n## Steps\n\n1. Make holes in the lid.\n2. One layer of dry, one layer of peels.\n3. Stir once a week.");
            $quizTwo = $page($moderator, $course, 'Quiz: water and compost', 'Two questions. This quiz is a self-check: there is no pass mark.');

            if ($quizOne->quiz()->doesntExist()) {
                app(SaveQuiz::class)->save($moderator, $quizOne, ['pass_mark' => 60, 'questions' => [
                    ['kind' => 'single', 'prompt' => 'How many hours of direct sun does a tomato plant need?', 'explanation' => 'Tomatoes need **six hours** and more.', 'choices' => [
                        ['label' => 'One hour', 'correct' => false], ['label' => 'Three hours', 'correct' => false], ['label' => 'Six hours and more', 'correct' => true],
                    ]],
                    ['kind' => 'multiple', 'prompt' => 'Which plants grow with three to six hours of sun? Choose all the right answers.', 'explanation' => 'Lettuce and herbs. Peppers need more.', 'choices' => [
                        ['label' => 'Lettuce', 'correct' => true], ['label' => 'Herbs', 'correct' => true], ['label' => 'Peppers', 'correct' => false], ['label' => 'Tomatoes', 'correct' => false],
                    ]],
                    ['kind' => 'single', 'prompt' => 'Where does the water stop when you fill a pot?', 'explanation' => 'Leave 3 cm, so that the water does not run over the edge.', 'choices' => [
                        ['label' => 'At the edge of the pot', 'correct' => false], ['label' => '3 cm below the edge', 'correct' => true],
                    ]],
                ]]);
            }
            if ($quizTwo->quiz()->doesntExist()) {
                app(SaveQuiz::class)->save($moderator, $quizTwo, ['pass_mark' => 0, 'questions' => [
                    ['kind' => 'single', 'prompt' => 'When do you water?', 'explanation' => 'In the morning: the plant drinks during the day.', 'choices' => [
                        ['label' => 'In the morning', 'correct' => true], ['label' => 'At noon', 'correct' => false], ['label' => 'At night', 'correct' => false],
                    ]],
                    ['kind' => 'multiple', 'prompt' => 'What goes in the compost bucket?', 'choices' => [
                        ['label' => 'Vegetable peels', 'correct' => true], ['label' => 'Dry leaves', 'correct' => true], ['label' => 'Cooked meat', 'correct' => false],
                    ]],
                ]]);
            }

            // Supplementary materials: for the course, for a lesson; links and one file
            $material($owner, $course, ['kind' => 'link', 'title' => 'Sowing calendar', 'url' => 'https://example.com/atlas/sowing-calendar']);
            $pdf = tempnam(sys_get_temp_dir(), 'atlas').'.txt';
            file_put_contents($pdf, "Atlas Garden - checklist for the first pot\n\n[ ] clay balls\n[ ] soil without peat\n[ ] seeds\n[ ] watering can\n");
            $material($owner, $course, ['kind' => 'file', 'title' => 'Checklist for the first pot', 'file' => new UploadedFile($pdf, 'checklist-first-pot.txt', 'text/plain', null, true)]);
            $material($moderator, $course, ['kind' => 'link', 'title' => 'How much soil for a pot (calculator)', 'url' => 'https://example.com/atlas/soil-calculator', 'page' => $videoLesson->cuid]);
            $material($moderator, $course, ['kind' => 'link', 'title' => 'What goes in the compost (list)', 'url' => 'https://example.com/atlas/compost-list', 'page' => $compost->cuid]);

            // Progress of the member: two lessons done, the first quiz passed with one wrong answer
            $progress = app(TrackLessonProgress::class);
            foreach ([$lessonOne, $lessonTwo] as $lesson) {
                if (DB::table('lesson_progress')->where('user_id', $member->id)->where('page_id', $lesson->id)->doesntExist()) {
                    $progress->complete($member, $lesson);
                }
            }
            $quiz = $quizOne->quiz()->with('questions.choices')->firstOrFail();
            if (QuizResult::query()->where('user_id', $member->id)->where('quiz_id', $quiz->id)->doesntExist()) {
                $answers = [];
                foreach ($quiz->questions as $index => $question) {
                    $right = $question->choices->where('is_correct', true)->pluck('cuid')->values()->all();
                    // The second question gets one answer of two: the result shows a wrong answer and its explanation
                    $answers[] = ['question' => $question->cuid, 'choices' => $index === 1 ? [$right[0]] : $right];
                }
                app(AttemptQuiz::class)->attempt($member, $quiz, ['answers' => $answers]);
            }
            // Another member finished the whole first section (a second progress in the community)
            foreach ([$lessonOne, $lessonTwo, $videoLesson] as $lesson) {
                if (DB::table('lesson_progress')->where('user_id', $lena->id)->where('page_id', $lesson->id)->doesntExist()) {
                    $progress->complete($lena, $lesson);
                }
            }

            // --- Resources spaces: "Reading list" (members add) and "Tools" (the team curates): the two collections
            $guide = $resource($member, $reading, 'balcony-vegetables-guide', 'Balcony vegetables, a guide for the first year', 'Clear and short. The chapter about pots is the best one.', 'article', 'beginner');
            $resource($lena, $reading, 'soil-life-explained', 'Soil life explained', 'A talk of 40 minutes about what lives in the soil.', 'video', 'intermediate');
            $resource($owner, $reading, 'the-small-garden-book', 'The small garden book', 'The book that I give to every new gardener.', 'book', 'beginner');
            $resource($tomas, $reading, 'compost-science-paper', 'Compost temperature and seed survival', 'A research paper. Dry, but it answers the question about weeds in compost.', 'paper', 'expert');
            $resource($moderator, $reading, 'garden-radio-episode-12', 'Garden radio, episode 12, winter herbs', '', 'audio', 'unknown');
            $resource($lena, $reading, 'seed-saving-advanced', 'Seed saving for people who did it once', 'The next step after your first saved seeds.', 'article', 'advanced');
            $resource($tomas, $reading, 'a-resource-with-a-very-long-name', 'A resource name that is as long as the form allows, 64 in all', 'The description is short.', 'other', 'intermediate');
            $resource($moderator, $tools, 'pot-size-calculator', 'Pot size calculator', 'Gives the litres of soil for a pot.', 'tool', 'beginner');
            $resource($owner, $tools, 'sowing-planner', 'Sowing planner', 'A calendar that you fill with your own plants.', 'tool', 'intermediate');
            $resource($moderator, $tools, 'sun-hours-map', 'Sun hours map', 'Shows the hours of sun for an address.', 'tool', 'unknown');
            $note = $comment($tomas, $guide, 'I read it last winter. Chapter 3 is worth the whole book.');
            $comment($member, $guide, 'Agreed. I go back to it every spring.', $note);
            $comment($lena, $guide, 'Is there a translation?');

            return compact('mine', 'long', 'gallery', 'video', 'pinned', 'mention', 'followed', 'muted', 'rules', 'threadOne', 'threadTwo', 'quizOne', 'quizTwo', 'lessonOne', 'lessonTwo', 'videoLesson', 'watering', 'compost', 'guide', 'fourth');
        });

    } finally {
        // Also after an error in the middle: what this run made gets its dates
        $backdate();
    }

    // The pinned post
    if (! $refs['pinned']->fresh()->is_pinned) {
        app(PinPost::class)->pin($moderator, $refs['pinned'], ['pinned' => true]);
    }

    $say('history ready');

    // ---------------------------------------------------------------------------------------------------------------
    // 3b. Events (#1514): an event with a cover and a meeting address, one in a room, a cancelled one, a draft and a
    // past one. No notification (Notify::silenced): the counts of the other pictures stay what they were
    // ---------------------------------------------------------------------------------------------------------------

    $events = Notify::silenced(function () use ($garden, $owner, $moderator, $gardenEvents, $event, $upload): array {
        $cover = $upload($owner, $garden, 'Paper bags with seeds on a wooden table', 1600, 900, [255, 236, 179], [129, 199, 132]);
        $swap = $event($owner, $gardenEvents, $owner, 'Seed swap, online', 3, 18, [
            'body' => "Bring the seeds that you saved this year, and a short story about each one.\n\n## How it works\n\n1. Each person shows one seed and says where it grew.\n2. We write down who wants which seed.\n3. You send the seeds by post after the call.\n\nNew gardeners are welcome: you do not need seeds to join.",
            'meeting_url' => 'https://example.com/atlas/meet/seed-swap',
            'cover_upload_cuid' => $cover->cuid,
            'cover_alt' => 'Paper bags with seeds on a wooden table',
        ]);
        $workshop = $event($owner, $gardenEvents, $moderator, 'Pruning workshop for small fruit trees', 10, 10, [
            'body' => 'A workshop of one hour in the greenhouse. Bring gloves and your own shears.',
            'place' => 'Greenhouse 2, City garden, Brussels',
        ]);
        $cancelled = $event($owner, $gardenEvents, $moderator, 'Open garden day', 5, 14, [
            'body' => 'The garden is open for visitors. Members show their beds.',
            'place' => 'City garden, Brussels',
        ]);
        if ($cancelled->state->value === 'published') {
            app(SetEventState::class)->setState($owner, $cancelled, ['state' => 'cancelled']);
        }
        $draft = $event($owner, $gardenEvents, $owner, 'Autumn planning, question hour', 20, 19, [
            'body' => 'A draft: the date is not sure yet.',
            'state' => 'draft',
        ]);
        $past = $event($owner, $gardenEvents, $moderator, 'Compost question hour', -7, 18, [
            'body' => 'Everything about compost: what goes in, what stays out, and why it smells.',
            'meeting_url' => 'https://example.com/atlas/meet/compost',
        ]);

        return compact('swap', 'workshop', 'cancelled', 'draft', 'past');
    });
    $say('events ready');

    // ---------------------------------------------------------------------------------------------------------------
    // 4. What the member chose: levels of spaces, threads followed and muted
    // ---------------------------------------------------------------------------------------------------------------

    $levels = app(ChooseSpaceLevels::class);
    $levelOf = static fn (Space $space): ?string => DB::table('notification_space_levels')->where('user_id', $member->id)->where('space_id', $space->id)->value('level');
    foreach ([[$announcements, 'all'], [$lounge, 'all'], [$offTopic, 'none']] as [$target, $level]) {
        if ($levelOf($target) !== $level) {
            $levels->set($member, $target, ['level' => $level]);
        }
    }
    $follows = app(FollowThreads::class);
    if ($follows->state($member, $refs['followed']) === null) {
        $follows->follow($member, $refs['followed']);
    }
    if ($follows->state($member, $refs['threadTwo']) === null) {
        $follows->follow($member, $refs['threadTwo']);
    }
    if ($follows->state($member, $refs['mine']) === null) {
        $follows->follow($member, $refs['mine']);
    }
    if ($follows->state($member, $refs['muted'])?->state->value !== 'muted') {
        $follows->mute($member, $refs['muted']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // 5. Today: what tells the member something. Every line below writes notifications through the real jobs
    // ---------------------------------------------------------------------------------------------------------------

    // post_comment, twice by two members: one bundle ("Tomas and Lena commented")
    $comment($tomas, $refs['mine'], 'One more idea: check that the holes of the pot are open.');
    $comment($lena, $refs['mine'], 'And turn the pot a quarter each week, so that every side gets sun.');
    // comment_reply: an answer to a comment of the member
    $own = $comment($member, $refs['long'], 'Question about section 5: which liquid food do you use?');
    $comment($owner, $refs['long'], 'I use a food made from nettles. You can make it in a bucket.', $own);
    // mention in a comment, in a post and in the chat
    $comment($moderator, $refs['gallery'], $at($member).' this is the raised bed that I told you about.');
    $post($lena, $discussion, 'Who wants to share an order of seeds?', 'The order is cheaper with five people. '.$at($member).' and '.$at($tomas).', are you in?');
    $chat($tomas, $lounge, $at($member).' the tomato cage is ready for you.');
    // thread_comment: a new comment in a thread that the member follows
    $comment($tomas, $refs['followed'], 'Our street did it. Start with a list of what goes in, on the lid.');
    $comment($admin, $refs['followed'], 'Ask the city: some cities give the bin for free.');
    // thread_reply: a new reply in a chat thread that the member follows
    $chat($lena, $lounge, 'I can bring a second table.', $refs['threadTwo']);
    // space_post: a new post in a space where the member chose "Everything" (the moderator sends it now)
    $post($moderator, $announcements, 'The garden hall is closed next week', "The hall is painted next week. The seed swap moves to the park.\n\nSame hour.", ['notify_now' => true]);
    // space_chat: a first-level message in a chat space where the member chose "Everything"
    $chat($owner, $lounge, 'The new lesson about pruning is in the course.');
    // lesson_new: a new lesson in a course that the member started
    $page($moderator, $course, 'Pruning', "Cut the side shoots of a tomato plant when they are small.\n\n1. Find the shoot between the stem and a leaf.\n2. Pinch it with two fingers.\n3. Do it once a week.");
    // Nothing: a post in the space that the member muted, and a comment in the thread that the member muted
    $post($tomas, $offTopic, 'The best biscuits for a garden break', 'Mine are the ones with oats.');
    $comment($tomas, $refs['muted'], 'Day 4. Four centimetres!');
    $say('today ready');

    // ---------------------------------------------------------------------------------------------------------------
    // 6. Direct messages: one conversation of two, one small group
    // ---------------------------------------------------------------------------------------------------------------

    $direct = $garden->conversations()->where('direct_key', Conversation::directKey($member, $tomas))->first()
        ?? app(StartConversation::class)->start($tomas, $garden, ['participant_cuids' => [$member->cuid], 'body' => 'Hi Marc, the tomato cage is in my hall. When do you come?']);
    $groupOpening = 'Shall we three prepare the seed swap together?';
    $group = ChatMessage::query()->where('community_id', $garden->id)->whereNotNull('conversation_id')->where('body', $groupOpening)->first()?->conversation
        ?? app(StartConversation::class)->start($lena, $garden, ['participant_cuids' => [$member->cuid, $moderator->cuid], 'body' => $groupOpening]);
    $direct2 = $garden->conversations()->where('direct_key', Conversation::directKey($member, $owner))->first()
        ?? app(StartConversation::class)->start($member, $garden, ['participant_cuids' => [$owner->cuid], 'body' => 'Hello Olivia, can I help at the next seed swap?']);
    $send = static function (User $by, Conversation $conversation, string $body) use (&$made): void {
        if ($conversation->messages()->where('body', $body)->exists()) {
            return;
        }
        app(SendDirectMessage::class)->send($by, $conversation, ['body' => $body]);
        $made++;
    };
    $send($member, $direct, 'Saturday morning, around ten?');
    $send($tomas, $direct, 'Ten is fine. Ring at number 12.');
    $send($member, $direct, "Perfect. I bring you some chilli seeds:\n\n- Cayenne\n- Jalapeño");
    $send($tomas, $direct, 'Deal 🌶️');
    $send($moderator, $group, 'Yes. I make the list of the tables.');
    $send($member, $group, 'I can print the labels for the seeds.');
    $send($lena, $group, 'Then I write the announcement. Thank you both!');
    $send($owner, $direct2, 'Yes, please! We need somebody at the door from 10:00.');

    // ---------------------------------------------------------------------------------------------------------------
    // 7. Volatile state: put back what a visit changes. Runs every time
    // ---------------------------------------------------------------------------------------------------------------

    // Read and open notifications of the member: the answer to a comment ("Needs you") and the comments of the followed
    // thread ("Following") are read, everything else is open
    $marks = app(MarkNotificationsRead::class);
    foreach (Notification::query()->with(['community', 'space', 'actor', 'post', 'comment', 'chatMessage', 'page'])->where('user_id', $member->id)->where('community_id', $garden->id)->get() as $notification) {
        $shouldBeRead = in_array($notification->kind->value, ['comment_reply', 'thread_comment'], true);
        if ($shouldBeRead && $notification->read_at === null) {
            $marks->read($member, $notification);
        } elseif (! $shouldBeRead && $notification->read_at !== null) {
            $marks->unread($member, $notification);
        }
    }
    // DIRECT WRITES below: the application has no action that goes back (a visit only moves these forward).
    $viewers = [$owner->id, $admin->id, $moderator->id, $member->id];
    // "Seen" has no way back: the open rows of the member are new again, so that the bell shows a count
    DB::table('notifications')->where('user_id', $member->id)->where('community_id', $garden->id)->whereNull('read_at')->update(['seen_at' => null]);
    // The other accounts that the atlas signs in with: every notification is open and new
    DB::table('notifications')->whereIn('user_id', [$owner->id, $admin->id, $moderator->id])->update(['seen_at' => null, 'read_at' => null]);
    // The chat is not read, the direct messages are not read
    DB::table('chat_reads')->whereIn('user_id', $viewers)->whereIn('space_id', $garden->spaces()->select('id'))->delete();
    DB::table('conversation_participants')->whereIn('user_id', $viewers)->whereIn('conversation_id', $garden->conversations()->select('id'))->update(['last_read_message_id' => 0]);
    // The spaces were last visited nine days ago: the dot "New content" shows where content came since
    DB::table('space_visits')->whereIn('user_id', $viewers)->whereIn('space_id', $garden->spaces()->select('id'))->update(['visited_at' => now()->subDays(9)]);
    // The new member has no time zone again: the application saves the zone of the browser at the first page load of
    // an account without a zone, so a visit sets it
    DB::table('users')->where('id', $newcomer->id)->update(['time_zone' => null]);
    // The member was away for nine days (no action sets the date of the last visit): the "Catch up" card shows in the
    // next session
    DB::table('users')->where('id', $member->id)->update(['last_visit_on' => now('UTC')->subDays(9)->toDateString()]);
    // The events keep their distance from today (#1514), so that "Upcoming" and "Past" hold the same events on each run.
    // The action of the application moves them: a change of an event tells nobody
    foreach (['swap' => [3, 18], 'workshop' => [10, 10], 'cancelled' => [5, 14], 'draft' => [20, 19], 'past' => [-7, 18]] as $key => [$days, $hour]) {
        app(UpdateEvent::class)->update($owner, $events[$key]->refresh(), ['starts_at' => $eventTime($days, $hour), 'ends_at' => $eventTime($days, $hour + 1), 'time_zone' => 'Europe/Brussels']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // 8. The manifest: the addresses that the screenshot tool needs
    // ---------------------------------------------------------------------------------------------------------------

    $slugOfPost = static fn (Space $space, string $title): string => (string) $space->posts()->where('title', $title)->value('slug');
    $slugOfPage = static fn (Space $space, string $title): string => (string) $space->pages()->where('title', $title)->value('slug');
    $emptySpaces = $empty->spaces()->pluck('slug', 'type')->all();
    $manifest = [
        'password' => $password,
        // The secret of the authenticator app of atlas-2fa@example.com: the tool makes the six digits from it
        'twoFactorSecret' => decrypt(User::query()->findOrFail($twoFactor->id)->two_factor_secret),
        'accounts' => [
            'owner' => $owner->email, 'admin' => $admin->email, 'moderator' => $moderator->email, 'member' => $member->email,
            'new-member' => $newcomer->email, 'unverified' => $unverified->email, 'two-factor' => $twoFactor->email,
        ],
        'usernames' => ['owner' => $owner->username, 'admin' => $admin->username, 'moderator' => $moderator->username, 'member' => $member->username, 'tomas' => $tomas->username, 'new-member' => $newcomer->username],
        'garden' => $garden->slug,
        'empty' => $empty->slug,
        'personal' => ['member' => (string) $member->ownedCommunities()->where('visibility', 'personal')->value('slug'), 'new-member' => (string) $newcomer->ownedCommunities()->where('visibility', 'personal')->value('slug'), 'owner' => (string) $owner->ownedCommunities()->where('visibility', 'personal')->value('slug')],
        'spaces' => [
            'welcome' => $welcome->slug, 'announcements' => $announcements->slug, 'link' => $website->slug, 'discussion' => $discussion->slug, 'off-topic' => $offTopic->slug,
            'chat' => $lounge->slug, 'course' => $course->slug, 'resources' => $reading->slug, 'tools' => $tools->slug, 'patrons' => $patronsSpace->slug,
            'events' => $gardenEvents->slug,
        ],
        'emptySpaces' => $emptySpaces,
        'posts' => [
            'mine' => $refs['mine']->slug, 'long' => $refs['long']->slug, 'gallery' => $refs['gallery']->slug, 'video' => $refs['video']->slug, 'pinned' => $refs['pinned']->slug,
            'short' => $slugOfPost($discussion, 'Short one: is rain water better?'), 'announcement' => $refs['rules']->slug,
            'patrons' => $slugOfPost($patronsSpace, 'Thank you, patrons'),
        ],
        'pages' => [
            'first' => $slugOfPage($welcome, 'Start here'), 'guidelines' => $slugOfPage($welcome, 'Community guidelines'), 'faq' => $slugOfPage($welcome, 'Frequently asked questions'),
            'lesson' => $refs['lessonOne']->slug, 'lesson-open' => $refs['watering']->slug, 'video-lesson' => $refs['videoLesson']->slug, 'materials-lesson' => $refs['compost']->slug,
            'quiz-after' => $refs['quizOne']->slug, 'quiz-before' => $refs['quizTwo']->slug,
        ],
        'resources' => ['guide' => $refs['guide']->slug],
        'events' => ['next' => $events['swap']->slug, 'place' => $events['workshop']->slug, 'cancelled' => $events['cancelled']->slug, 'draft' => $events['draft']->slug, 'past' => $events['past']->slug],
        'conversations' => ['direct' => $direct->cuid, 'group' => $group->cuid, 'owner' => $direct2->cuid],
        'chat' => ['thread' => $refs['threadTwo']->cuid],
    ];
    file_put_contents(storage_path('app/atlas-manifest.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

    $say('new items in this run: '.$made);
    $say('notifications of the member: '.Notification::query()->where('user_id', $member->id)->count()
        .' ('.Notification::query()->where('user_id', $member->id)->whereNull('read_at')->count().' open)');
    $say('manifest: storage/app/atlas-manifest.json');
    $say('done');
})();
