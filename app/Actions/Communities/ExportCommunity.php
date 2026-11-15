<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\ExportsCommunities;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityResource;
use App\Models\Conversation;
use App\Models\CourseMaterial;
use App\Models\LessonProgress;
use App\Models\Page;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use App\Support\JsonExportFile;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Exports a community to one zip file (#1353, ADR 0030): the return of the data that the DPA promises (GDPR art.
 * 28(3)(g)). The operator runs `php artisan communities:export` on the request of the owner.
 *
 * - JSON files with `format_version` 1, and the stored files (uploads and course materials).
 * - Public identifiers are cuids. Never the internal ids, the password hashes, the tokens, the two-factor secrets,
 *   the rendered HTML (derived from the Markdown), nor the data of another community.
 * - Direct messages only with the option: they are private between their participants.
 * - The file is a copy of personal data: `retention:prune` deletes it after `retention.exports_days`.
 *
 * @phpstan-import-type Row from JsonExportFile
 */
final class ExportCommunity implements ExportsCommunities
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'exports';

    private const int CHUNK = 200;

    /**
     * The ids of the users of the member list (the owner included): an author who is not in it gets a few details
     *
     * @var array<int, true>
     */
    private array $memberIds = [];

    /**
     * The stored files to put in the zip file: the entry name, with the temporary copy
     *
     * @var array<string, string>
     */
    private array $files = [];

    private string $workDirectory = '';

    final public function export(Community $community, bool $withDirectMessages = false): string
    {
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(self::DIRECTORY);
        $path = $disk->path(self::DIRECTORY.'/community-'.$community->slug.'-'.now()->format('Ymd-His').'.zip');

        $this->workDirectory = sys_get_temp_dir().'/knowii-export-'.Str::random(16);
        $this->files = [];
        File::ensureDirectoryExists($this->workDirectory);

        try {
            $this->memberIds = [$community->owner_id => true];
            foreach (CommunityMember::query()->where('community_id', $community->id)->get(['user_id']) as $member) {
                $this->memberIds[$member->user_id] = true;
            }

            $json = [
                'community.json' => [$this->communityHead($community), $this->communityLists($community)],
                'posts.json' => [[], ['posts' => $this->posts($community)]],
                'comments.json' => [[], ['comments' => $this->comments($community)]],
                'pages.json' => [[], ['pages' => $this->pages($community)]],
                'chat-messages.json' => [[], ['chat_messages' => $this->chatMessages($community)]],
                'reactions.json' => [[], ['reactions' => $this->reactions($community, $withDirectMessages)]],
                'resources.json' => [[], ['resources' => $this->resources($community)]],
                'quizzes.json' => [[], ['quizzes' => $this->quizzes($community)]],
                'quiz-results.json' => [[], ['quiz_results' => $this->quizResults($community)]],
                'lesson-progress.json' => [[], ['lesson_progress' => $this->lessonProgress($community)]],
                'course-materials.json' => [[], ['course_materials' => $this->courseMaterials($community)]],
                'uploads.json' => [[], ['uploads' => $this->uploads($community)]],
            ];
            if ($withDirectMessages) {
                $json['conversations.json'] = [[], ['conversations' => $this->conversations($community)]];
            }

            foreach ($json as $name => [$head, $lists]) {
                JsonExportFile::write($this->workDirectory.'/'.$name, $head, $lists);
            }

            $this->zip($path, array_keys($json), $withDirectMessages);
        } finally {
            File::deleteDirectory($this->workDirectory);
            $this->files = [];
        }

        Log::info('Community exported', ['community' => $community->cuid, 'direct_messages' => $withDirectMessages]);

        return $path;
    }

    /**
     * @return array<string, Row>
     */
    private function communityHead(Community $community): array
    {
        $community->loadMissing('owner');

        return ['community' => [
            'cuid' => $community->cuid,
            'name' => $community->name,
            'slug' => $community->slug,
            'description' => $community->description,
            'visibility' => $community->visibility->value,
            'created_at' => $this->date($community->created_at),
            'updated_at' => $this->date($community->updated_at),
            'dpa_accepted_at' => $this->date($community->dpa_accepted_at),
            'dpa_version' => $community->dpa_version?->toDateString(),
            'owner' => $community->owner->cuid,
        ]];
    }

    /**
     * @return array<string, iterable<Row>>
     */
    private function communityLists(Community $community): array
    {
        return [
            'members' => $this->members($community),
            'invitations' => $this->invitations($community),
            'access_groups' => $this->accessGroups($community),
            'space_groups' => $this->spaceGroups($community),
            'spaces' => $this->spaces($community),
        ];
    }

    /**
     * The owner is not a row of the member table: it comes first, with the creation date of the community
     *
     * @return Generator<int, Row>
     */
    private function members(Community $community): Generator
    {
        $owner = $community->owner;
        yield [
            'user' => $owner->cuid,
            'username' => $owner->username,
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => 'owner',
            'joined_at' => $this->date($community->created_at),
        ];

        foreach (CommunityMember::query()->where('community_id', $community->id)->with('user')->lazyById(self::CHUNK) as $member) {
            yield [
                'user' => $member->user->cuid,
                'username' => $member->user->username,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
                'joined_at' => $this->date($member->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function invitations(Community $community): Generator
    {
        foreach ($community->communityInvitations()->lazyById(self::CHUNK) as $invitation) {
            yield [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'created_at' => $this->date($invitation->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function accessGroups(Community $community): Generator
    {
        foreach (AccessGroup::query()->where('community_id', $community->id)->with(['members', 'spaces', 'spaceGroups'])->lazyById(self::CHUNK) as $group) {
            /** @var AccessGroup $group */
            yield [
                'cuid' => $group->cuid,
                'name' => $group->name,
                'description' => $group->description,
                'members' => $group->members->map(static fn (User $member): string => $member->cuid)->values()->all(),
                'spaces' => $group->spaces->map(static fn (Space $space): string => $space->cuid)->values()->all(),
                'space_groups' => $group->spaceGroups->map(static fn (SpaceGroup $spaceGroup): string => $spaceGroup->cuid)->values()->all(),
                'created_at' => $this->date($group->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function spaceGroups(Community $community): Generator
    {
        foreach (SpaceGroup::query()->where('community_id', $community->id)->lazyById(self::CHUNK) as $group) {
            yield [
                'cuid' => $group->cuid,
                'name' => $group->name,
                'position' => $group->position,
                'created_at' => $this->date($group->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function spaces(Community $community): Generator
    {
        foreach (Space::query()->where('community_id', $community->id)->with('spaceGroup')->lazyById(self::CHUNK) as $space) {
            yield [
                'cuid' => $space->cuid,
                'space_group' => $space->spaceGroup->cuid,
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
                'emoji' => $space->emoji,
                'url' => $space->url,
                'type' => $space->type->value,
                'visibility' => $space->visibility->value,
                'posting_policy' => $space->posting_policy->value,
                'position' => $space->position,
                'created_at' => $this->date($space->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function posts(Community $community): Generator
    {
        foreach (Post::query()->where('community_id', $community->id)->whereNull('deleted_at')->with(['space', 'author'])->lazyById(self::CHUNK) as $post) {
            yield [
                'cuid' => $post->cuid,
                'space' => $post->space->cuid,
                ...$this->person('author', $post->author),
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
     * @return Generator<int, Row>
     */
    private function comments(Community $community): Generator
    {
        foreach (Comment::query()->where('community_id', $community->id)->whereNull('deleted_at')->with(['post', 'communityResource', 'parent', 'author'])->lazyById(self::CHUNK) as $comment) {
            yield [
                'cuid' => $comment->cuid,
                'post' => $comment->post?->cuid,
                'resource' => $comment->communityResource?->cuid,
                'parent' => $comment->parent?->cuid,
                ...$this->person('author', $comment->author),
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
    private function pages(Community $community): Generator
    {
        foreach (Page::query()->where('community_id', $community->id)->with(['space', 'author'])->lazyById(self::CHUNK) as $page) {
            yield [
                'cuid' => $page->cuid,
                'space' => $page->space->cuid,
                ...$this->person('author', $page->author),
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
     * The messages of the chat spaces. A deleted message stays as an empty marker.
     *
     * @return Generator<int, Row>
     */
    private function chatMessages(Community $community): Generator
    {
        $messages = ChatMessage::query()->where('community_id', $community->id)->whereNotNull('space_id')->with(['space', 'parent', 'author']);
        foreach ($messages->lazyById(self::CHUNK) as $message) {
            yield [
                'cuid' => $message->cuid,
                'space' => $message->space?->cuid,
                'parent' => $message->parent?->cuid,
                ...$this->person('author', $message->author),
                'body' => $message->body,
                'edited_at' => $this->date($message->edited_at),
                'deleted_at' => $this->date($message->deleted_at),
                'created_at' => $this->date($message->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function reactions(Community $community, bool $withDirectMessages): Generator
    {
        $reactions = Reaction::query()->where('community_id', $community->id)->with(['user', 'post', 'comment', 'chatMessage']);
        if (! $withDirectMessages) {
            $reactions->whereDoesntHave('chatMessage', static fn ($query) => $query->whereNotNull('conversation_id'));
        }

        foreach ($reactions->lazyById(self::CHUNK) as $reaction) {
            [$type, $item] = match (true) {
                $reaction->post_id !== null => ['post', $reaction->post?->cuid],
                $reaction->comment_id !== null => ['comment', $reaction->comment?->cuid],
                default => ['chat_message', $reaction->chatMessage?->cuid],
            };

            yield [
                ...$this->person('user', $reaction->user),
                'item_type' => $type,
                'item' => $item,
                'emoji' => $reaction->emoji,
                'created_at' => $this->date($reaction->created_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function resources(Community $community): Generator
    {
        $resources = CommunityResource::query()->where('community_id', $community->id)->with(['resource', 'space', 'capture', 'curator.user']);
        foreach ($resources->lazyById(self::CHUNK) as $resource) {
            yield [
                'cuid' => $resource->cuid,
                'space' => $resource->space->cuid,
                'name' => $resource->name,
                'description' => $resource->description,
                'url' => $resource->resource->url,
                'type' => $resource->type->value,
                'level' => $resource->level->value,
                'is_featured' => $resource->is_featured,
                ...$this->person('curator', $resource->curator?->user),
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
    private function quizzes(Community $community): Generator
    {
        foreach (Quiz::query()->where('community_id', $community->id)->with(['page', 'questions.choices'])->lazyById(self::CHUNK) as $quiz) {
            $questions = [];
            foreach ($quiz->questions->sortBy('position') as $question) {
                $choices = [];
                foreach ($question->choices->sortBy('position') as $choice) {
                    $choices[] = [
                        'cuid' => $choice->cuid,
                        'position' => $choice->position,
                        'label' => $choice->label,
                        'is_correct' => $choice->is_correct,
                    ];
                }
                $questions[] = [
                    'cuid' => $question->cuid,
                    'position' => $question->position,
                    'kind' => $question->kind->value,
                    'prompt' => $question->prompt,
                    'explanation' => $question->explanation,
                    'choices' => $choices,
                ];
            }

            yield [
                'cuid' => $quiz->cuid,
                'page' => $quiz->page->cuid,
                'pass_mark' => $quiz->pass_mark,
                'questions' => $questions,
                'created_at' => $this->date($quiz->created_at),
                'updated_at' => $this->date($quiz->updated_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function quizResults(Community $community): Generator
    {
        foreach (QuizResult::query()->where('community_id', $community->id)->with(['user', 'quiz'])->lazyById(self::CHUNK) as $result) {
            yield [
                'cuid' => $result->cuid,
                'quiz' => $result->quiz->cuid,
                ...$this->person('user', $result->user),
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
    private function lessonProgress(Community $community): Generator
    {
        foreach (LessonProgress::query()->where('community_id', $community->id)->with(['user', 'space', 'page'])->lazyById(self::CHUNK) as $progress) {
            yield [
                ...$this->person('user', $progress->user),
                'space' => $progress->space->cuid,
                'page' => $progress->page->cuid,
                'completed_at' => $this->date($progress->completed_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function courseMaterials(Community $community): Generator
    {
        foreach (CourseMaterial::query()->where('community_id', $community->id)->with(['space', 'page'])->lazyById(self::CHUNK) as $material) {
            yield [
                'cuid' => $material->cuid,
                'space' => $material->space->cuid,
                'page' => $material->page?->cuid,
                'title' => $material->title,
                'position' => $material->position,
                'kind' => $material->kind->value,
                'url' => $material->url,
                'file_name' => $material->file_name,
                'mime_type' => $material->mime_type,
                'size_bytes' => $material->size_bytes,
                'file' => $this->collectFile('materials', $material->cuid, $material->disk, $material->path),
                'created_at' => $this->date($material->created_at),
                'updated_at' => $this->date($material->updated_at),
            ];
        }
    }

    /**
     * @return Generator<int, Row>
     */
    private function uploads(Community $community): Generator
    {
        foreach (Upload::query()->where('community_id', $community->id)->with('user')->lazyById(self::CHUNK) as $upload) {
            yield [
                'cuid' => $upload->cuid,
                ...$this->person('user', $upload->user),
                'alt' => $upload->alt,
                'mime_type' => $upload->mime_type,
                'width' => $upload->width,
                'height' => $upload->height,
                'size_bytes' => $upload->size_bytes,
                'file' => $this->collectFile('uploads', $upload->cuid, $upload->disk, $upload->path),
                'created_at' => $this->date($upload->created_at),
            ];
        }
    }

    /**
     * Direct messages (only with the option): the participants and the messages of each conversation
     *
     * @return Generator<int, Row>
     */
    private function conversations(Community $community): Generator
    {
        foreach (Conversation::query()->where('community_id', $community->id)->with('participants')->lazyById(self::CHUNK) as $conversation) {
            $participants = [];
            foreach ($conversation->participants as $participant) {
                $participants[] = ['user' => $participant->cuid, 'username' => $participant->username, 'name' => $participant->name];
            }

            $messages = [];
            foreach ($conversation->messages()->with('author')->lazyById(self::CHUNK) as $message) {
                $messages[] = [
                    'cuid' => $message->cuid,
                    ...$this->person('author', $message->author),
                    'body' => $message->body,
                    'edited_at' => $this->date($message->edited_at),
                    'deleted_at' => $this->date($message->deleted_at),
                    'created_at' => $this->date($message->created_at),
                ];
            }

            yield [
                'cuid' => $conversation->cuid,
                'participants' => $participants,
                'messages' => $messages,
                'created_at' => $this->date($conversation->created_at),
            ];
        }
    }

    /**
     * A user as a cuid, null for a deleted user. A user who is not in the member list (an author who left) comes with
     * the username and the name.
     *
     * @return array<string, string|array<string, string>|null>
     */
    private function person(string $key, ?User $user): array
    {
        if ($user === null) {
            return [$key => null];
        }
        if (isset($this->memberIds[$user->id])) {
            return [$key => $user->cuid];
        }

        return [$key => $user->cuid, $key.'_details' => ['username' => $user->username, 'name' => $user->name]];
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    /**
     * Copies a stored file to the work directory, to put it in the zip file. Returns its path in the zip file, null
     * when the file is not on its disk any more.
     */
    private function collectFile(string $folder, string $cuid, ?string $disk, ?string $path): ?string
    {
        if ($disk === null || $path === null || $path === '') {
            return null;
        }

        $storage = Storage::disk($disk);
        $stream = $storage->exists($path) ? $storage->readStream($path) : null;
        if ($stream === null) {
            return null;
        }

        $entry = 'files/'.$folder.'/'.$cuid.'-'.basename($path);
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
    private function zip(string $path, array $jsonFiles, bool $withDirectMessages): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the export file');
        }

        $zip->addFromString('README.txt', $this->readme($jsonFiles, $withDirectMessages));
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
    private function readme(array $jsonFiles, bool $withDirectMessages): string
    {
        $lines = [
            'Knowii community export',
            '',
            'Format version: '.JsonExportFile::FORMAT_VERSION,
            'Created: '.now()->toIso8601String(),
            '',
            'The JSON files are UTF-8. People are named by their identifier (cuid). The member list in community.json',
            'gives the username, name and email address of each member. The owner is the first member. Its joined date is',
            'the creation date of the community.',
            '',
            'Files:',
            '',
        ];
        $descriptions = [
            'community.json' => 'the community, its members, invitations, access groups, space groups and spaces',
            'posts.json' => 'the posts (Markdown)',
            'comments.json' => 'the comments on posts and on resources (Markdown)',
            'pages.json' => 'the pages and the lessons (Markdown)',
            'chat-messages.json' => 'the messages of the chat spaces',
            'reactions.json' => 'the reactions to posts, comments and messages',
            'resources.json' => 'the resources with their address and the captured text (Markdown)',
            'quizzes.json' => 'the quizzes with their questions and choices',
            'quiz-results.json' => 'the results of the members in the quizzes',
            'lesson-progress.json' => 'the lessons that members marked as done',
            'course-materials.json' => 'the course materials (links and files)',
            'uploads.json' => 'the uploaded images',
            'conversations.json' => 'the direct messages, with their participants',
        ];
        foreach ($jsonFiles as $name) {
            $lines[] = $name.': '.$descriptions[$name];
        }
        $lines[] = 'files/uploads/: the uploaded images, named after their cuid (see uploads.json)';
        $lines[] = 'files/materials/: the files of the course materials, named after their cuid (see course-materials.json)';
        $lines[] = '';
        $lines[] = 'Left out:';
        $lines[] = '';
        $lines[] = $withDirectMessages
            ? '1. Nothing of the direct messages: they are included, because the operator asked for them.'
            : '1. The direct messages and the reactions to them: they are private between their participants.';
        $lines[] = '2. The internal numeric identifiers, the password hashes, the tokens, the two-factor secrets and the HTML that Knowii derives from the Markdown.';
        $lines[] = '3. The data of other communities, the avatars and profile fields of the members, the mentions and the read markers.';

        return implode("\n", $lines)."\n";
    }
}
