<?php

namespace App\Http\Controllers\Api\V1;

use App\Audit\AuditLogger;
use App\Http\Requests\Api\V1\UpdateSpecialistRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class UpdateSpecialistController
{
    public function __invoke(UpdateSpecialistRequest $request, User $specialist, AuditLogger $audit): JsonResponse
    {
        // This endpoint manages SPECIALIST accounts only. SUPER_ADMIN bypasses
        // policies through Gate::before, so the target-role rule is enforced
        // here as well (I-3/I-8).
        if ($specialist->role?->name !== Role::SPECIALIST) {
            throw new AuthorizationException;
        }

        $validated = $request->validated();
        $actor = $request->user();

        DB::transaction(function () use ($specialist, $validated, $actor, $request, $audit): void {
            $specialist->fill($validated);

            $changes = $specialist->getDirty();

            if ($changes === []) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach (array_keys($changes) as $attribute) {
                $oldValues[$attribute] = $specialist->getOriginal($attribute);
                $newValues[$attribute] = $changes[$attribute];
            }

            $specialist->save();

            $audit->log(
                actor: $actor,
                action: 'updated',
                entityName: 'users',
                recordId: $specialist->id,
                oldValues: $oldValues,
                newValues: $newValues,
                request: $request,
            );
        });

        $role = $specialist->role;

        return ApiResponse::success([
            'id' => $specialist->id,
            'name' => $specialist->name,
            'email' => $specialist->email,
            'mobile' => $specialist->mobile,
            'is_active' => $specialist->is_active,
            'role' => [
                'id' => $role?->id,
                'name' => $role?->name,
            ],
        ], 'SPECIALIST updated successfully');
    }
}
