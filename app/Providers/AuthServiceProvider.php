<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * SUPER_ADMIN has a full authorization override (D-020, D-030).
     *
     * Returning null for everyone else keeps normal Gate/policy evaluation and
     * the deny-by-default behaviour untouched.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user === null || $user->trashed() || ! $user->is_active) {
                return null;
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }
}
