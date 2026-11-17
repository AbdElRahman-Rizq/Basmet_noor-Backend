<?php

namespace App\Users;

use App\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Applies an account activation change atomically (D-055).
 *
 * The row is locked before the comparison so two concurrent requests cannot
 * write duplicate audit rows. Deactivation also revokes every Sanctum token of
 * the target inside the same transaction; reactivation never restores them.
 */
final class ChangeUserStatusAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(User $actor, User $target, bool $active, Request $request): User
    {
        return DB::transaction(function () use ($actor, $target, $active, $request): User {
            $target = User::query()->lockForUpdate()->findOrFail($target->getKey());

            // No-op: no state, token, or audit change (D-055).
            if ($target->is_active === $active) {
                return $target;
            }

            $previous = $target->is_active;

            $target->is_active = $active;
            $target->save();

            if (! $active) {
                $target->tokens()->delete();
            }

            $this->audit->log(
                actor: $actor,
                action: $active ? 'reactivated' : 'deactivated',
                entityName: 'users',
                recordId: $target->id,
                oldValues: ['is_active' => $previous],
                newValues: ['is_active' => $active],
                request: $request,
            );

            return $target;
        });
    }
}
