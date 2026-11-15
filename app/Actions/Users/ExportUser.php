<?php

namespace App\Actions\Users;

use App\Constants;
use App\Contracts\Users\ExportsUsers;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityJoinBlock;
use App\Models\CommunityMember;
use App\Models\CommunityResource;
use App\Models\LessonProgress;
use App\Models\Mention;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Post;
use App\Models\QuizResult;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\Upload;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\JsonExportFile;
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
                'account.json' => [$this->accountHead($user), ['connected_accounts' => $this->connectedAccounts($user)]],
                'memberships.json' => [[], [
                    'memberships' => $this->memberships($user),
                    'owned_communities' => $this->ownedCommunities($user),
                    'access_groups' => $this->accessGroups($user),
                    'invitations' => $this->invitations($user),
                    'join_blocks' => $this->joinBlocks($user),
                ]],
                'activity.json' => [[], [
                    'lessons_done' => $this->lessonsDone($user),
                    'quiz_results' => $this->quizResults($user),
                    'chat_space_read_markers' => $this->chatSpaceReadMarkers($user),
                    'conversation_read_markers' => $this->conversationReadMarkers($user),
                    'blocked_members' => $this->blockedMembers($user),
                    'mentions_of_you' => $this->mentions($user),
                    'notifications' => $this->notifications($user),
                    'api_tokens' => $this->apiTokens($user),
                    'sessions' => $this->sessions($user),
                ]],
                'posts.json' => [[], ['posts' => $this->posts($user)]],
                'comments.json' => [[], ['comments' => $this->comments($user)]],
                'pages.json' => [[], ['pages' => $this->pages($user)]],
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
                'two_factor_authentication' => $user->two_factor_confirmed_at !== null,
                'show_online_status' => $user->showsOnlineStatus(),
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
            ];
        }
    }

    /**
     * The invitations sent to the email address of the member
     *
     * @return Generator<int, Row>
     */
    private function invitations(User $user): Generator
    {
        foreach (CommunityInvitation::query()->where('email', $user->email)->lazyById(self::CHUNK) as $invitation) {
            yield [
                'cuid' => $invitation->cuid,
                ...$this->place($invitation->community_id, null),
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'created_at' => $this->date($invitation->created_at),
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
        foreach (Notification::query()->where('user_id', $user->id)->with(['post:id,cuid', 'comment:id,cuid', 'chatMessage:id,cuid'])->lazyById(self::CHUNK) as $notification) {
            yield [
                'cuid' => $notification->cuid,
                'kind' => $notification->kind->value,
                ...$this->place($notification->community_id, null),
                'post' => $notification->post?->cuid,
                'comment' => $notification->comment?->cuid,
                'chat_message' => $notification->chatMessage?->cuid,
                'seen_at' => $this->date($notification->seen_at),
                'read_at' => $this->date($notification->read_at),
                'created_at' => $this->date($notification->created_at),
                'updated_at' => $this->date($notification->updated_at),
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
            'account.json' => 'your account and your profile, with the choice of who sees each profile field, and the accounts of other providers that you connected to sign in (the provider, your identifier there and the dates)',
            'memberships.json' => 'your communities and your role, the communities you own, your access groups, the invitations sent to your email address and the communities where a manager removed you and where you cannot join again by yourself',
            'activity.json' => 'the lessons you marked as done, your quiz results, what you read in chat spaces and conversations, the members you blocked, where you were mentioned, your notifications (kind, community, the items by identifier and the dates, never a text), your API tokens and your sessions',
            'posts.json' => 'your posts (Markdown)',
            'comments.json' => 'your comments (Markdown), with the cuid and the title of what they answer',
            'pages.json' => 'the pages and lessons you wrote (Markdown)',
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
        $lines[] = '4. Logs and backups. They hold the identifier of your account at most, and they expire as the privacy policy says.';

        return implode("\n", $lines)."\n";
    }
}
