<?php

namespace App\Http\Controllers\Api\V1;

use App\Audit\AuditLogger;
use App\Http\Requests\Api\V1\CreateSpecialistRequest;
use App\Models\Role;
use App\Models\Specialist;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class CreateSpecialistController
{
    public function __invoke(CreateSpecialistRequest $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();

        // The role is resolved server-side; no client-supplied role is trusted.
        $role = Role::query()->where('name', Role::SPECIALIST)->firstOrFail();

        $user = DB::transaction(function () use ($validated, $role, $actor, $request, $audit): User {
            $user = User::create([
                'role_id' => $role->id,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'password' => $validated['password'],
                'is_active' => true,
                'created_by_user_id' => $actor->id,
            ]);

            // Every SPECIALIST account owns exactly one domain profile
            // (DATABASE_DESIGN §4.1); clinical records reference this row.
            Specialist::create(['user_id' => $user->id]);

            $audit->log(
                actor: $actor,
                action: 'created',
                entityName: 'users',
                recordId: $user->id,
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'role' => $role->name,
                    'is_active' => $user->is_active,
                ],
                request: $request,
            );

            return $user;
        });

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'is_active' => $user->is_active,
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
        ], 'SPECIALIST created successfully', status: 201);
    }
}
