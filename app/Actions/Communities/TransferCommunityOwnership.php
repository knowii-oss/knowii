<?php

namespace App\Actions\Communities;

use App\Actions\CommunityMembers\FindsCommunityMembers;
use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Enums\KnowiiCommunityMemberRole;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Exceptions\BusinessException;
use App\Mail\CommunityOwnershipTransferred;
use App\Models\Community;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TransferCommunityOwnership implements TransfersCommunityOwnership
{
    use FindsCommunityMembers;

    /**
     * The owner gives the community to one of its admins (#1339, ADR 0035). The new owner leaves the member table
     * (the owner is not a row of it), and the former owner becomes an admin, in one transaction. The owner confirms
     * with their password: they cannot undo the change by themselves.
     *
     * @throws AuthorizationException
     * @throws BusinessException
     * @throws ValidationException
     */
    final public function transfer(User $user, Community $community, array $input): User
    {
        Gate::forUser($user)->authorize('transferOwnership', $community);

        /** @var array{member: string, password: string} $validated */
        $validated = Validator::make($input, [
            'member' => ['required', 'string'],
            'password' => ['required', 'string'],
        ])->validate();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => [__('The provided password does not match your current password.')]]);
        }

        [$newOwner, $role] = $this->findMember($community, $validated['member']);

        if ($role !== KnowiiCommunityMemberRole::Admin) {
            throw new BusinessException('Only an admin can become the owner. Make this member an admin first');
        }

        DB::transaction(static function () use ($community, $user, $newOwner): void {
            $community->users()->detach($newOwner);
            $community->update(['owner_id' => $newOwner->id]);
            $community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Admin]);
        });

        Mail::to($newOwner)->queue(new CommunityOwnershipTransferred($community, $user));

        Log::info('Community ownership transferred', ['community' => $community->cuid, 'from' => $user->cuid, 'to' => $newOwner->cuid]);
        CommunityMembersChanged::dispatch($community->cuid);

        return $newOwner;
    }
}
