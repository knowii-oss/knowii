<?php

namespace App\Actions\Chat;

use App\Constants;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Notifications\FollowsThreads;
use App\Contracts\Notifications\Notifies;
use App\Enums\KnowiiFollowReason;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\User;
use App\Rules\MaxMarkdownImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Write a message in a chat space, or a reply in the thread of a message (one level, #1185). The posting policy of the
 * space decides who writes.
 */
class CreateChatMessage implements CreatesChatMessages
{
    final public function create(User $user, Space $space, array $input): ChatMessage
    {
        Gate::forUser($user)->authorize('contribute', $space);

        if ($space->type !== KnowiiSpaceType::Chat) {
            throw new BusinessException('Messages can only be written in chat spaces');
        }

        /** @var array{body: string, parent_cuid?: string|null} $validated */
        $validated = Validator::make($input, [
            ...self::rules(),
            'parent_cuid' => ['nullable', 'string'],
        ])->validate();

        $parent = null;
        if (! empty($validated['parent_cuid'])) {
            $parent = $space->chatMessages()->where('cuid', $validated['parent_cuid'])->first()
                ?? throw new BusinessException('This message does not exist in this space');
            if ($parent->parent_id !== null) {
                throw new BusinessException('Threads have one level: reply to the first message of the thread');
            }
            if ($parent->isDeleted()) {
                throw new BusinessException('This message was deleted');
            }
        }

        $message = DB::transaction(function () use ($space, $user, $validated, $parent) {
            $message = ChatMessage::create([
                'community_id' => $space->community_id,
                'space_id' => $space->id,
                'author_id' => $user->id,
                'parent_id' => $parent?->id,
                'body' => $validated['body'],
                'body_html' => '',
                // Explicit: the model does not read the database default after the insert
                'reply_count' => 0,
            ]);
            // Mentions need the message id: render once it exists (#1177)
            $message->update(['body_html' => $message->renderBodyAndSyncMentions($validated['body'], $user)]);
            $message->syncUploadUsages($validated['body']);

            if ($parent !== null) {
                $parent->update(['reply_count' => $parent->replies()->whereNull('deleted_at')->count(), 'last_reply_at' => $message->created_at]);
            }

            return $message;
        });

        Log::info('Chat message created', ['message' => $message->cuid, 'thread' => $parent?->cuid]);
        // A reply makes a thread: the author of the first message and the member who replies follow it (#1475). A first
        // message follows nothing until it has a reply, so that a chat does not write a row per message
        if ($parent !== null) {
            $follows = app(FollowsThreads::class);
            if ($parent->author !== null) {
                $follows->followByWriting($parent->author, $parent, KnowiiFollowReason::Wrote);
            }
            $follows->followByWriting($user, $parent, KnowiiFollowReason::Replied);
        }
        // The members mentioned in the message are told (#1474), and the ones who follow the thread or chose "Everything"
        // for the space (#1475): queued jobs, after the commit
        app(Notifies::class)->notify($message);
        event(SpaceContentChanged::forMessage(SpaceContentChanged::MESSAGE_CREATED, $message));

        return $message;
    }

    /**
     * WARNING: aligned with chatMessageFormSchema (chat-message.schema.ts)
     *
     * @return array<string, list<string|object>>
     */
    public static function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_CHAT_MESSAGE, new MaxMarkdownImages],
        ];
    }
}
