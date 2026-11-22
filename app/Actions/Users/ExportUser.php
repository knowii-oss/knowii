<?php

namespace App\Actions\Users;

use App\Constants;
use App\Contracts\Users\ExportsUsers;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityJoinBlock;
use App\Models\CommunityMember;
use App\Models\CommunityResource;
use App\Models\Event;
use App\Models\EventAnswer;
use App\Models\InvitationImportRow;
use App\Models\LessonProgress;
use App\Models\MemberTag;
use App\Models\Mention;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Page;
use App\Models\Post;
use App\Models\QuizResult;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceVisit;
use App\Models\Upload;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserIdentity;
use App\Support\JsonExportFile;
use App\Support\LegalPages;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Exports the data of a member to one zip file (#1343): the copy of the data that the privacy policy promises (section
 * 8, GDPR art. 12(3), 15 and 20). The operator runs `php artisan users:export` on the request of the member.
 *
 * - JSON files with `format_version` 1, the avatar and the uploaded images, and a README.
 * - Only what is about the member or what the member wrote. Never what other members wrote: a comment shows the
 *   cuid and the title of its post, a direct message shows only the messages of the member.
 * - Public identifiers are cuids. Never the internal ids, the password hash, the tokens, the two-factor secrets, the
 *   session payload, the "remember me" token nor the rendered HTML (derived from the Markdown).
 * - The file is a copy of personal data: `retention:prune` deletes it after `retention.exports_days`.
 *
 * @phpstan-import-type Row from JsonExportFile
 */
final class ExportUser implements ExportsUsers
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'exports';

    private const int CHUNK = 200;

    /**
     * The communities that the lists name, by internal id: the cuid, the name and the slug
     *
     * @var array<int, array{cuid: string, name: string, slug: string}|null>
     */
    private array $communities = [];

    /**
     * The stored files to put in the zip file: the entry name, with the temporary copy
     *
     * @var array<string, string>
     */
    private array $files = [];

    private string $workDirectory = '';

    final public function export(User $user): string
    {
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(self::DIRECTORY);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $user->username) ?? 'member';
        $path = $disk->path(self::DIRECTORY.'/user-'.$name.'-'.now()->format('Ymd-His').'.zip');

        $this->workDirectory = sys_get_temp_dir().'/knowii-export-'.Str::random(16);
        $this->files = [];
        $this->communities = [];
        File::ensureDirectoryExists($this->workDirectory);

        try {
            $json = [
                'account.json' => [$this->accountHead($user), ['connected_accounts' => $this->connectedAccounts($user), 'entitlements' => $this->entitlements($user)]],
                'memberships.json' => [[], [
                    'memberships' => $this->memberships($user),
                    'owned_communities' => $this->ownedCommunities($user),
                    'access_groups' => $this->accessGroups($user),
                    'member_tags' => $this->memberTags($user),
                    'invitations' => $this->invitations($user),
                    'invitation_import_rows' => $this->invitationImportRows($user),
                    'join_blocks' => $this->joinBlocks($user),
                ]],
                'activity.json' => [[], [
                    'lessons_done' => $this->lessonsDone($user),
                    'quiz_results' => $this->quizResults($user),
                    'event_answers' => $this->eventAnswers($user),
                    'chat_space_read_markers' => $this->chatSpaceReadMarkers($user),
                    'conversation_read_markers' => $this->conversationReadMarkers($user),
                    'blocked_members' => $this->blockedMembers($user),
                    'mentions_of_you' => $this->mentions($user),
                    'notifications' => $this->notifications($user),
                    'notification_follows' => $this->notificationFollows($user),
                    'notification_levels' => $this->notificationLevels($user),
                    'space_visits' => $this->spaceVisits($user),
                    'api_tokens' => $this->apiTokens($user),
                    'sessions' => $this->sessions($user),
                    'invitation_mails' => $this->invitationMails($user),
                ]],
                'posts.json' => [[], ['posts' => $this->posts($user)]],
                'comments.json' => [[], ['comments' => $this->comments($user)]],
                'pages.json' => [[], ['pages' => $this->pages($user)]],
                'events.json' => [[], ['events' => $this->events($user)]],
                'chat-messages.json' => [[], ['chat_messages' => $this->chatMessages($user)]],
                'direct-messages.json' => [[], ['direct_messages' => $this->directMessages($user)]],
                'reactions.json' => [[], ['reactions' => $this->reactions($user)]],
                'resources.json' => [[], ['resources' => $this->resources($user)]],
                'uploads.json' => [[], ['uploads' => $this->uploads($user)]],
            ];

            foreach ($json as $file => [$head, $lists]) {
                JsonExportFile::write($this->workDirectory.'/'.$file, $head, $lists);
            }

            $this->zip($path, array_keys($json));
        } finally {
            File::deleteDirectory($this->workDirectory);
            $this->files = [];
            $this->communities = [];
        }

        Log::info('User exported', ['user' => $user->cuid]);

        return $path;
    }

    /**
     * The account and the profile
     *
     * @return array<string, Row>
     */
    private function accountHead(User $user): array
    {
        $profile = $user->profile()->first();
        $profileRow = [];
        if ($profile !== null) {
            $profileRow = [
                'cuid' => $profile->cuid,
                'name' => $profile->name,
                'username' => $profile->username,
                'headline' => $profile->headline,
                'email' => $profile->email,
                'bio' => $profile->bio,
                'location' => $profile->location,
                'phone' => $profile->phone,
            ];
            foreach (Constants::SOCIAL_MEDIA_LINK_PROPERTIES as $field) {
                $value = $profile->getAttribute($field);
                $profileRow[$field] = is_string($value) ? $value : null;
            }
            $profileRow['field_visibility'] = $profile->fieldVisibility();
            $profileRow['created_at'] = $this->date($profile->created_at);
            $profileRow['updated_at'] = $this->date($profile->updated_at);
        }

        return [
            'account' => [
                'cuid' => $user->cuid,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'email_verified_at' => $this->date($user->email_verified_at),
                // A new address that waits for its confirmation (#1706, ADR 0058), as it is stored, with the time of the request
                'pending_email' => is_string($pending = $user->getRawOriginal('pending_email')) ? $pending : null,
                'pending_email_requested_at' => $this->date($user->pending_email_requested_at),
                'two_factor_authentication' => $user->two_factor_confirmed_at !== null,
                'show_online_status' => $user->showsOnlineStatus(),
                // Where I am (#1476, ADR 0048): the zone that the member chose, the date of the last visit (nobody else reads it) and
                // the switch of the pop-ups
                'time_zone' => $user->timeZone(),
                'last_visit_on' => $this->lastVisitOn($user),
                'notification_popups' => $user->showsNotificationPopups(),
                'avatar' => $this->collectAvatar($user),
                'created_at' => $this->date($user->created_at),
                'updated_at' => $this->date($user->updated_at),
            ],
            'profile' => $profileRow,
        ];
    }

    /**
     * The accounts of OpenID Connect providers that the member connected (#1385, ADR 0045): the issuer, the identifier
     * of the member's own account at the provider (the subject: it is their data) and the dates. Never a token: none is
     * stored.
     *
     * @return Generator<int, Row>
     */
    private function connectedAccounts(User $user): Generator
    {
        foreach (UserIdentity::query()->where('user_id', $user->id)->lazyById(self::CHUNK) as $identity) {
            yield [
                'cuid' => $identity->cuid,
                'issuer' => $identity->issuer,
                'subject' => $identity->subject,
                'linked_at' => $this->date($identity->linked_at),
                'last_used_at' => $this->date($identity->last_used_at),
                'entitlements_checked_at' => $this->date($identity->entitlements_checked_at),
            ];
        }
    }

    /**
     * The entitlement keys that the sign-in provider reported for the member (#1386, ADR 0047): the issuer and the key. Only
     * the keys: no grant, source or date of the provider is stored.
     *
     * @return Generator<int, Row>
     */
    private function entitlements(User $user): Generator
    {
        foreach (UserEntitlement::query()->where('user_id', $user->id)->orderBy('issuer')->orderBy('key')->lazyById(self::CHUNK) as $entitlement) {
            yield [
                'issuer' => $entitlement->issuer,
                'key' => $entitlement->key,
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function memberships(User $user): Generator
    {
        foreach (CommunityMember::query()->where('user_id', $user->id)->lazyById(self::CHUNK) as $member) {
            yield [
                'cuid' => $member->cuid,
                ...$this->place($member->community_id, null),
                'role' => $member->role->value,
                'joined_at' => $this->date($member->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function ownedCommunities(User $user): Generator
    {
        foreach ($user->ownedCommunities()->lazyById(self::CHUNK) as $community) {
            yield [
                'cuid' => $community->cuid,
                'name' => $community->name,
                'slug' => $community->slug,
                'created_at' => $this->date($community->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function accessGroups(User $user): Generator
    {
        foreach ($user->accessGroups()->get() as $group) {
            yield [
                'cuid' => $group->cuid,
                'name' => $group->name,
                ...$this->place($group->community_id, null),
                'joined_at' => $this->pivotDate($group),
                // Where the membership comes from (#1386, ADR 0047): added by hand, given by an entitlement rule, or both
                'added_by_hand' => $this->pivotFlag($group, 'manual'),
                'given_by_rule' => $this->pivotFlag($group, 'by_rule'),
            ];
        }
    }

    /**
     * The tags that the managers of a community put on the member (#1389, ADR 0060), of both visibilities: the member
     * reads here also the tags that no screen shows them. No other person is named.
     *
     * @return Generator<int, Row>
     */
    private function memberTags(User $user): Generator
    {
        foreach ($user->memberTags()->orderBy('member_tags.id')->get() as $tag) {
            yield [
                'cuid' => $tag->cuid,
                'name' => $tag->name,
                'visibility' => $tag->visibility->value,
                ...$this->place($tag->community_id, null),
                'tagged_at' => $this->pivotDate($tag),
            ];
        }
    }

    /**
     * The invitations sent to the email address of the member, compared without the case (an invitation stores the
     * address in lower case, an account can hold capitals), with the access groups and the member tags that each gives at
     * the join (#1390): both visibilities of a tag, the person has a right to their data (ADR 0060 decision 14)
     *
     * @return Generator<int, Row>
     */
    private function invitations(User $user): Generator
    {
        $invitations = CommunityInvitation::query()
            ->whereRaw('lower(email) = lower(?)', [$user->email])
            ->with(['accessGroups' => static fn ($query) => $query->orderByRaw('lower(access_groups.name)'), 'memberTags' => static fn ($query) => $query->orderByRaw('lower(member_tags.name)')]);
        foreach ($invitations->lazyById(self::CHUNK) as $invitation) {
            yield [
                'cuid' => $invitation->cuid,
                ...$this->place($invitation->community_id, null),
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'access_groups' => $invitation->accessGroups->map(static fn (AccessGroup $group): array => ['cuid' => $group->cuid, 'name' => $group->name])->values()->all(),
                'member_tags' => $invitation->memberTags->map(static fn (MemberTag $tag): array => ['cuid' => $tag->cuid, 'name' => $tag->name])->values()->all(),
                'created_at' => $this->date($invitation->created_at),
            ];
        }
    }

    /**
     * The rows of the imports of invitations from a file with the address of the member, compared without the case (#1390,
     * ADR 0061): the community, the date, the result and the reason. No name of another person
     *
     * @return Generator<int, Row>
     */
    private function invitationImportRows(User $user): Generator
    {
        $rows = InvitationImportRow::query()->whereRaw('lower(email) = lower(?)', [$user->email])->with('invitationImport');
        foreach ($rows->lazyById(self::CHUNK) as $row) {
            yield [
                ...$this->place($row->invitationImport->community_id, null),
                'email' => $row->email,
                'outcome' => $row->outcome->value,
                'reason' => $row->reason?->value,
                'created_at' => $this->date($row->created_at),
                'sent_at' => $this->date($row->sent_at),
            ];
        }
    }

    /**
     * The communities where a manager removed the member: they cannot join them again by themselves (#1486)
     *
     * @return Generator<int, Row>
     */
    private function joinBlocks(User $user): Generator
    {
        foreach (CommunityJoinBlock::query()->where('user_id', $user->id)->lazyById(self::CHUNK) as $block) {
            yield [
                ...$this->place($block->community_id, null),
                'removed_at' => $this->date($block->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function lessonsDone(User $user): Generator
    {
        foreach (LessonProgress::query()->where('user_id', $user->id)->with(['space', 'page'])->lazyById(self::CHUNK) as $progress) {
            yield [
                ...$this->place($progress->community_id, $progress->space),
                'page' => $progress->page->cuid,
                'page_title' => $progress->page->title,
                'completed_at' => $this->date($progress->completed_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function quizResults(User $user): Generator
    {
        foreach (QuizResult::query()->where('user_id', $user->id)->with('quiz.page.space')->lazyById(self::CHUNK) as $result) {
            yield [
                'cuid' => $result->cuid,
                ...$this->place($result->community_id, $result->quiz->page->space),
                'quiz' => $result->quiz->cuid,
                'page' => $result->quiz->page->cuid,
                'best_score' => $result->best_score,
                'last_score' => $result->last_score,
                'last_answers' => $result->last_answers,
                'attempts' => $result->attempts,
                'passed_at' => $this->date($result->passed_at),
                'updated_at' => $this->date($result->updated_at),
            ];
        }
    }

    /**
     * The answers of the member to events (#1515, ADR 0053): the event by its cuid and its title, and the answer. Never
     * the host, never another member.
     *
     * @return Generator<int, Row>
     */
    private function eventAnswers(User $user): Generator
    {
        foreach (EventAnswer::query()->where('user_id', $user->id)->with('event.space')->lazyById(self::CHUNK) as $answer) {
            yield [
                'cuid' => $answer->cuid,
                ...$this->place($answer->community_id, $answer->event->space),
                'event' => $answer->event->cuid,
                'event_title' => $answer->event->title,
                'answer' => $answer->answer?->value,
                'created_at' => $this->date($answer->created_at),
                'updated_at' => $this->date($answer->updated_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function chatSpaceReadMarkers(User $user): Generator
    {
        foreach (ChatRead::query()->where('user_id', $user->id)->with('space')->lazyById(self::CHUNK) as $read) {
            yield [
                ...$this->place($read->space->community_id, $read->space),
                'last_read_message' => $this->messageCuid($read->last_read_message_id),
                'updated_at' => $this->date($read->updated_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function conversationReadMarkers(User $user): Generator
    {
        foreach ($user->conversations()->get() as $conversation) {
            $pivot = $conversation->relationLoaded('pivot') ? $conversation->getRelation('pivot') : null;
            $lastRead = $pivot instanceof Pivot ? $pivot->getAttribute('last_read_message_id') : null;

            yield [
                'conversation' => $conversation->cuid,
                ...$this->place($conversation->community_id, null),
                'last_read_message' => is_int($lastRead) ? $this->messageCuid($lastRead) : null,
                'updated_at' => $this->pivotDate($conversation, 'updated_at'),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function blockedMembers(User $user): Generator
    {
        foreach ($user->blockedUsers()->get() as $blocked) {
            yield [
                'user' => $blocked->cuid,
                'username' => $blocked->username,
                'blocked_at' => $this->pivotDate($blocked),
            ];
        }
    }

    /**
     * The places where other members mentioned the member: the item only, never its text
     *
     * @return Generator<int, Row>
     */
    private function mentions(User $user): Generator
    {
        foreach (Mention::query()->where('user_id', $user->id)->with(['post', 'comment', 'chatMessage'])->lazyById(self::CHUNK) as $mention) {
            [$type, $item] = match (true) {
                $mention->post_id !== null => ['post', $mention->post?->cuid],
                $mention->comment_id !== null => ['comment', $mention->comment?->cuid],
                default => ['chat_message', $mention->chatMessage?->cuid],
            };

            yield [
                ...$this->place($mention->community_id, null),
                'item_type' => $type,
                'item' => $item,
                'created_at' => $this->date($mention->created_at),
            ];
        }
    }

    /**
     * The notifications of the member (#1474, ADR 0042): the kind, the community, the items by cuid and the dates. Never
     * a text of content and never the name of the member who caused them.
     *
     * @return Generator<int, Row>
     */
    private function notifications(User $user): Generator
    {
        foreach (Notification::query()->where('user_id', $user->id)->with(['post:id,cuid', 'comment:id,cuid', 'chatMessage:id,cuid', 'event:id,cuid'])->lazyById(self::CHUNK) as $notification) {
            yield [
                'cuid' => $notification->cuid,
                'kind' => $notification->kind->value,
                ...$this->place($notification->community_id, null),
                'post' => $notification->post?->cuid,
                'comment' => $notification->comment?->cuid,
                'chat_message' => $notification->chatMessage?->cuid,
                'event' => $notification->event?->cuid,
                'seen_at' => $this->date($notification->seen_at),
                'read_at' => $this->date($notification->read_at),
                'created_at' => $this->date($notification->created_at),
                'updated_at' => $this->date($notification->updated_at),
            ];
        }
    }

    /**
     * The threads that the member follows, stopped following or muted (#1475, ADR 0042): the state, the reason, the
     * community, the post or the message by cuid, and the dates. The choices of the member, never a text of content.
     *
     * @return Generator<int, Row>
     */
    private function notificationFollows(User $user): Generator
    {
        foreach (NotificationFollow::query()->where('user_id', $user->id)->with(['post:id,cuid', 'chatMessage:id,cuid'])->lazyById(self::CHUNK) as $follow) {
            yield [
                ...$this->place($follow->community_id, null),
                'post' => $follow->post?->cuid,
                'chat_message' => $follow->chatMessage?->cuid,
                'state' => $follow->state->value,
                'reason' => $follow->reason->value,
                'created_at' => $this->date($follow->created_at),
                'updated_at' => $this->date($follow->updated_at),
            ];
        }
    }

    /**
     * The level that the member chose for each space (#1475, ADR 0042): everything, only what is for me, or muted
     *
     * @return Generator<int, Row>
     */
    private function notificationLevels(User $user): Generator
    {
        foreach (NotificationSpaceLevel::query()->where('user_id', $user->id)->with('space')->lazyById(self::CHUNK) as $level) {
            yield [
                ...$this->place($level->space->community_id, $level->space),
                'level' => $level->level->value,
                'created_at' => $this->date($level->created_at),
                'updated_at' => $this->date($level->updated_at),
            ];
        }
    }

    /**
     * The date of the last visit (#1476): a date without an hour
     */
    private function lastVisitOn(User $user): ?string
    {
        $value = $user->getRawOriginal('last_visit_on');

        return is_string($value) && $value !== '' ? substr($value, 0, 10) : null;
    }

    /**
     * The last visit of each space (#1476, ADR 0048): the marker behind the dots of the navigation
     *
     * @return Generator<int, Row>
     */
    private function spaceVisits(User $user): Generator
    {
        foreach (SpaceVisit::query()->where('user_id', $user->id)->with('space')->lazyById(self::CHUNK) as $visit) {
            yield [
                ...$this->place($visit->space->community_id, $visit->space),
                'visited_at' => $this->date($visit->visited_at),
            ];
        }
    }

    /**
     * The name, the abilities and the dates of the API tokens. Never the token: Knowii stores only its hash.
     *
     * @return Generator<int, Row>
     */
    private function apiTokens(User $user): Generator
    {
        foreach ($user->tokens()->lazyById(self::CHUNK) as $token) {
            yield [
                'name' => $token->name,
                'abilities' => array_values(array_filter($token->abilities ?? [], is_string(...))),
                'created_at' => $this->date($token->created_at),
                'last_used_at' => $this->date($token->last_used_at),
                'expires_at' => $this->date($token->expires_at),
            ];
        }
    }

    /**
     * The sessions stored in the database: the IP address, the browser and the last activity. Never the payload.
     *
     * @return Generator<int, Row>
     */
    private function sessions(User $user): Generator
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        foreach (DB::table('sessions')->where('user_id', $user->id)->orderBy('last_activity')->get() as $session) {
            $values = get_object_vars($session);
            $ip = $values['ip_address'] ?? null;
            $browser = $values['user_agent'] ?? null;
            $activity = $values['last_activity'] ?? null;

            yield [
                'ip_address' => is_string($ip) ? $ip : null,
                'browser' => is_string($browser) ? $browser : null,
                'last_activity_at' => is_int($activity) || (is_string($activity) && ctype_digit($activity))
                    ? Carbon::createFromTimestamp((int) $activity)->toIso8601String()
                    : null,
            ];
        }
    }

    /**
     * The invitation emails that the account sent, as the daily caps count them (#1390, ADR 0061): the community and the
     * time. A row stays Retention::invitationMailsDays() days at most
     *
     * @return Generator<int, Row>
     */
    private function invitationMails(User $user): Generator
    {
        foreach (DB::table('invitation_mails')->where('user_id', $user->id)->orderBy('created_at')->orderBy('id')->get(['community_id', 'created_at']) as $mail) {
            $communityId = $mail->community_id;
            $createdAt = $mail->created_at;

            yield [
                'community' => is_numeric($communityId) ? $this->place((int) $communityId, null)['community'] : null,
                'created_at' => is_string($createdAt) ? $this->date(Carbon::parse($createdAt)) : null,
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function posts(User $user): Generator
    {
        foreach (Post::query()->where('author_id', $user->id)->with('space')->lazyById(self::CHUNK) as $post) {
            yield [
                'cuid' => $post->cuid,
                ...$this->place($post->community_id, $post->space),
                'title' => $post->title,
                'slug' => $post->slug,
                'body' => $post->body,
                'is_pinned' => $post->is_pinned,
                'pinned_at' => $this->date($post->pinned_at),
                'edited_at' => $this->date($post->edited_at),
                'created_at' => $this->date($post->created_at),
                'updated_at' => $this->date($post->updated_at),
            ];
        }
    }

    /**
     * The comments of the member. The post or the resource that they answer shows its cuid and its title only.
     *
     * @return Generator<int, Row>
     */
    private function comments(User $user): Generator
    {
        foreach (Comment::query()->where('author_id', $user->id)->with(['post.space', 'communityResource.space', 'parent'])->lazyById(self::CHUNK) as $comment) {
            $space = $comment->post !== null ? $comment->post->space : $comment->communityResource?->space;

            yield [
                'cuid' => $comment->cuid,
                ...$this->place($comment->community_id, $space),
                'post' => $comment->post === null ? null : ['cuid' => $comment->post->cuid, 'title' => $comment->post->title],
                'resource' => $comment->communityResource === null ? null : ['cuid' => $comment->communityResource->cuid, 'name' => $comment->communityResource->name],
                'parent' => $comment->parent?->cuid,
                'body' => $comment->body,
                'edited_at' => $this->date($comment->edited_at),
                'created_at' => $this->date($comment->created_at),
                'updated_at' => $this->date($comment->updated_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function pages(User $user): Generator
    {
        foreach (Page::query()->where('author_id', $user->id)->with('space')->lazyById(self::CHUNK) as $page) {
            yield [
                'cuid' => $page->cuid,
                ...$this->place($page->community_id, $page->space),
                'kind' => $page->kind->value,
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $page->body,
                'video_url' => $page->video_url,
                'position' => $page->position,
                'created_at' => $this->date($page->created_at),
                'updated_at' => $this->date($page->updated_at),
            ];
        }
    }

    /**
     * The events that the member hosts (#1513): the title, the space, the times (UTC) and the zone, the state
     *
     * @return Generator<int, Row>
     */
    private function events(User $user): Generator
    {
        foreach (Event::query()->where('host_id', $user->id)->with('space')->lazyById(self::CHUNK) as $event) {
            yield [
                'cuid' => $event->cuid,
                ...$this->place($event->community_id, $event->space),
                'title' => $event->title,
                'starts_at' => $this->date($event->starts_at->toImmutable()->utc()),
                'ends_at' => $this->date($event->ends_at->toImmutable()->utc()),
                'time_zone' => $event->time_zone,
                'state' => $event->state->value,
                'created_at' => $this->date($event->created_at),
                'updated_at' => $this->date($event->updated_at),
            ];
        }
    }

    /**
     * The messages of the member in chat spaces. The message that they answer shows its cuid only.
     *
     * @return Generator<int, Row>
     */
    private function chatMessages(User $user): Generator
    {
        $messages = ChatMessage::query()->where('author_id', $user->id)->whereNotNull('space_id')->with(['space', 'parent']);
        foreach ($messages->lazyById(self::CHUNK) as $message) {
            yield [
                'cuid' => $message->cuid,
                ...$this->place($message->community_id, $message->space),
                'parent' => $message->parent?->cuid,
                'body' => $message->body,
                'edited_at' => $this->date($message->edited_at),
                'deleted_at' => $this->date($message->deleted_at),
                'created_at' => $this->date($message->created_at),
            ];
        }
    }

    /**
     * Only the direct messages that the member wrote, with the cuid of their conversation. Never the other side.
     *
     * @return Generator<int, Row>
     */
    private function directMessages(User $user): Generator
    {
        $messages = ChatMessage::query()->where('author_id', $user->id)->whereNotNull('conversation_id')->with('conversation');
        foreach ($messages->lazyById(self::CHUNK) as $message) {
            yield [
                'cuid' => $message->cuid,
                ...$this->place($message->community_id, null),
                'conversation' => $message->conversation?->cuid,
                'body' => $message->body,
                'edited_at' => $this->date($message->edited_at),
                'deleted_at' => $this->date($message->deleted_at),
                'created_at' => $this->date($message->created_at),
            ];
        }
    }

    /**
     * The reactions of the member: the item (cuid) and the emoji
     *
     * @return Generator<int, Row>
     */
    private function reactions(User $user): Generator
    {
        foreach (Reaction::query()->where('user_id', $user->id)->with(['post', 'comment', 'chatMessage'])->lazyById(self::CHUNK) as $reaction) {
            [$type, $item] = match (true) {
                $reaction->post_id !== null => ['post', $reaction->post?->cuid],
                $reaction->comment_id !== null => ['comment', $reaction->comment?->cuid],
                default => [$reaction->chatMessage?->conversation_id !== null ? 'direct_message' : 'chat_message', $reaction->chatMessage?->cuid],
            };

            yield [
                ...$this->place($reaction->community_id, null),
                'item_type' => $type,
                'item' => $item,
                'emoji' => $reaction->emoji,
                'created_at' => $this->date($reaction->created_at),
            ];
        }
    }

    /**
     * The community resources that the member curated (the curator is the profile of the member)
     *
     * @return Generator<int, Row>
     */
    private function resources(User $user): Generator
    {
        $profile = $user->profile()->first();
        if ($profile === null) {
            return;
        }

        $resources = CommunityResource::query()->where('curator_id', $profile->id)->with(['resource', 'space', 'capture']);
        foreach ($resources->lazyById(self::CHUNK) as $resource) {
            yield [
                'cuid' => $resource->cuid,
                ...$this->place($resource->community_id, $resource->space),
                'name' => $resource->name,
                'description' => $resource->description,
                'url' => $resource->resource->url,
                'type' => $resource->type->value,
                'level' => $resource->level->value,
                'is_featured' => $resource->is_featured,
                'capture_status' => $resource->capture_status->value,
                'captured_markdown' => $resource->capture?->markdown,
                'captured_at' => $this->date($resource->capture?->captured_at),
                'created_at' => $this->date($resource->created_at),
                'updated_at' => $this->date($resource->updated_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function uploads(User $user): Generator
    {
        foreach (Upload::query()->where('user_id', $user->id)->lazyById(self::CHUNK) as $upload) {
            yield [
                'cuid' => $upload->cuid,
                ...$this->place($upload->community_id, null),
                'alt' => $upload->alt,
                'mime_type' => $upload->mime_type,
                'width' => $upload->width,
                'height' => $upload->height,
                'size_bytes' => $upload->size_bytes,
                'file' => $this->collectFile('uploads/'.$upload->cuid.'-'.basename($upload->path), $upload->disk, $upload->path),
                'created_at' => $this->date($upload->created_at),
            ];
        }
    }

    /**
     * The community of an item (cuid and slug), and its space when it has one
     *
     * @return array<string, string|null>
     */
    private function place(int $communityId, ?Space $space): array
    {
        if (! array_key_exists($communityId, $this->communities)) {
            $community = Community::query()->find($communityId, ['id', 'cuid', 'name', 'slug']);
            $this->communities[$communityId] = $community === null ? null : ['cuid' => $community->cuid, 'name' => $community->name, 'slug' => $community->slug];
        }
        $community = $this->communities[$communityId];

        $place = ['community' => $community['cuid'] ?? null, 'community_slug' => $community['slug'] ?? null];
        if ($space !== null) {
            $place['space'] = $space->cuid;
            $place['space_slug'] = $space->slug;
        }

        return $place;
    }

    /**
     * The cuid of a message, from its internal id (a read marker): null when there is no such message
     */
    private function messageCuid(int $id): ?string
    {
        $cuid = $id > 0 ? ChatMessage::query()->whereKey($id)->value('cuid') : null;

        return is_string($cuid) ? $cuid : null;
    }

    /**
     * The date of the pivot row of a many-to-many relation
     */
    private function pivotFlag(Model $model, string $column): bool
    {
        $pivot = $model->relationLoaded('pivot') ? $model->getRelation('pivot') : null;

        return $pivot instanceof Pivot && (bool) $pivot->getAttribute($column);
    }

    private function pivotDate(Model $model, string $column = 'created_at'): ?string
    {
        $pivot = $model->relationLoaded('pivot') ? $model->getRelation('pivot') : null;
        $value = $pivot instanceof Pivot ? $pivot->getAttribute($column) : null;

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return is_string($value) && $value !== '' ? Carbon::parse($value)->toIso8601String() : null;
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    /**
     * The avatar goes in the zip file as `files/avatar.<extension>`: its path in the zip file, null without avatar
     */
    private function collectAvatar(User $user): ?string
    {
        $path = $user->getAttributes()['avatar_path'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $this->collectFile('avatar'.($extension === '' ? '' : '.'.$extension), UpdateAvatar::disk(), $path);
    }

    /**
     * Copies a stored file to the work directory, to put it in the zip file under `files/<name>`. Returns its path in
     * the zip file, null when the file is not on its disk any more.
     */
    private function collectFile(string $name, ?string $disk, ?string $path): ?string
    {
        if ($disk === null || $path === null || $path === '') {
            return null;
        }

        $storage = Storage::disk($disk);
        $stream = $storage->exists($path) ? $storage->readStream($path) : null;
        if ($stream === null) {
            return null;
        }

        $entry = 'files/'.$name;
        $copy = $this->workDirectory.'/file-'.count($this->files);
        $target = fopen($copy, 'wb');
        if ($target === false) {
            throw new RuntimeException('Cannot write the export file');
        }
        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        $this->files[$entry] = $copy;

        return $entry;
    }

    /**
     * @param  list<string>  $jsonFiles
     */
    private function zip(string $path, array $jsonFiles): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the export file');
        }

        $zip->addFromString('README.txt', $this->readme($jsonFiles));
        foreach ($jsonFiles as $name) {
            $zip->addFile($this->workDirectory.'/'.$name, $name);
        }
        foreach ($this->files as $entry => $copy) {
            $zip->addFile($copy, $entry);
        }

        if (! $zip->close()) {
            throw new RuntimeException('Cannot write the export file');
        }
    }

    /**
     * @param  list<string>  $jsonFiles
     */
    private function readme(array $jsonFiles): string
    {
        $lines = [
            'Knowii member data export',
            '',
            'Format version: '.JsonExportFile::FORMAT_VERSION,
            'Created: '.now()->toIso8601String(),
            '',
            'The JSON files are UTF-8. Things are named by their identifier (cuid). Each item names its community',
            '(cuid and slug) and its space when it has one. Dates use the ISO 8601 format.',
            '',
            'Files:',
            '',
        ];
        $descriptions = [
            'account.json' => 'your account and your profile, with the choice of who sees each profile field, and the accounts of other providers that you connected to sign in (the provider, your identifier there and the dates), with the entitlement keys that the provider reported for you and the date of the last check, your time zone, the date of your last visit (a date, no hour) and your choice of pop-ups',
            'memberships.json' => 'your communities and your role, the communities you own, your access groups (and whether you were added by hand or by an entitlement rule), the tags that the managers of a community put on you (the name, who sees it: the members or the managers only, and the date), the invitations sent to your email address (with the access groups and the member tags that each gives when you join), the rows of the imports of invitations from a file with your address (the community, the date, whether the invitation went out and why a row was refused) and the communities where a manager removed you and where you cannot join again by yourself',
            'activity.json' => 'the lessons you marked as done, your quiz results, your answers to events (the event by its identifier and its title, and your answer: yes, replay only or no), what you read in chat spaces and conversations, the members you blocked, where you were mentioned, your notifications (kind, community, the items by identifier and the dates, never a text), the threads that you follow or muted and the level that you chose for each space, the date when you last visited each space, your API tokens, your sessions and the invitation emails that you sent in the last days (the community and the time)',
            'posts.json' => 'your posts (Markdown)',
            'comments.json' => 'your comments (Markdown), with the cuid and the title of what they answer',
            'pages.json' => 'the pages and lessons you wrote (Markdown)',
            'events.json' => 'the events that you host, with their title, times, zone and state',
            'chat-messages.json' => 'your messages in chat spaces',
            'direct-messages.json' => 'your direct messages, with the cuid of their conversation',
            'reactions.json' => 'your reactions to posts, comments and messages',
            'resources.json' => 'the community resources that you curated, with the captured text (Markdown)',
            'uploads.json' => 'your uploaded images',
        ];
        foreach ($jsonFiles as $name) {
            $lines[] = $name.': '.$descriptions[$name];
        }
        $lines[] = 'files/avatar.*: your avatar';
        $lines[] = 'files/uploads/: your uploaded images, named after their cuid (see uploads.json)';
        $lines[] = '';
        $lines[] = 'Left out:';
        $lines[] = '';
        $lines[] = '1. What other members wrote: their posts and comments, and the messages that they wrote in your conversations. This file names a post or a conversation of someone else only by its cuid and its title.';
        $lines[] = '2. Your password hash, your two-factor secret and recovery codes, the value of your API tokens, the data of your sessions and the "remember me" token. They protect your account, and Knowii cannot show them in a readable form.';
        $lines[] = '3. The internal numeric identifiers and the HTML that Knowii derives from the Markdown.';
        // The instance can have no privacy policy (#1653): then the sentence does not point to one
        $lines[] = '4. Logs and backups. They hold the identifier of your account at most, and they expire'.(LegalPages::exists('privacy-policy') ? ' as the privacy policy says.' : ' after a time.');

        return implode("\n", $lines)."\n";
    }
}
