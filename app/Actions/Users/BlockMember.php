<?php

namespace App\Actions\Users;

use App\Contracts\Users\BlocksMembers;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * A member blocks another member, in every community (#1186, ADR 0028): neither starts nor continues a one-to-one
 * conversation with the other, and neither adds the other to a group conversation. The blocked member is not told.
 */
class BlockMember implements BlocksMembers
{
    final public function block(User $user, User $blocked): void
    {
        if ($user->is($blocked)) {
            throw new BusinessException('You cannot block yourself');
        }

        $user->blockedUsers()->syncWithoutDetaching([$blocked->id => ['created_at' => now()]]);
        Log::info('Member blocked', ['user' => $user->cuid, 'blocked' => $blocked->cuid]);
    }

    final public function unblock(User $user, User $blocked): void
    {
        $user->blockedUsers()->detach($blocked->id);
        Log::info('Member unblocked', ['user' => $user->cuid, 'blocked' => $blocked->cuid]);
    }
}
