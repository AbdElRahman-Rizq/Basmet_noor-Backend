<?php

use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\Child;
use App\Models\Goal;
use App\Models\ParentProfile;
use App\Models\Report;
use App\Models\Role;
use App\Models\Specialist;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

it('runs against the dedicated test database, never the development one', function () {
    expect(config('database.connections.pgsql.database'))->toBe('basma_noor_test');

    $current = DB::selectOne('select current_database() as db')->db;

    expect($current)->toBe('basma_noor_test');
});

/*
|--------------------------------------------------------------------------
| Gate C fixtures
|--------------------------------------------------------------------------
|
| Small helpers shared by the schema tests. They never store a child name:
| `child_name` is a request-only field (DATABASE_DESIGN.md §9.4).
|
*/

/**
 * Assert that the database rejects a statement.
 *
 * PostgreSQL aborts the whole transaction after an error, so the failing
 * statement runs inside a nested transaction (SAVEPOINT) that Laravel rolls
 * back. The surrounding RefreshDatabase transaction stays usable.
 */
function expectDbFailure(Closure $callback): void
{
    try {
        DB::transaction($callback);
    } catch (QueryException $exception) {
        expect($exception)->toBeInstanceOf(QueryException::class);

        return;
    }

    throw new RuntimeException('Expected the database to reject this statement, but it succeeded.');
}

function fixtureRole(string $name = 'SPECIALIST', int $level = 40): Role
{
    return Role::firstOrCreate(
        ['name' => $name],
        ['description' => null, 'is_system_role' => true, 'level' => $level],
    );
}

function fixtureUser(?Role $role = null, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role_id' => ($role ?? fixtureRole())->id,
    ], $attributes));
}

function fixtureParent(?User $user = null): ParentProfile
{
    return ParentProfile::factory()->create([
        'user_id' => ($user ?? fixtureUser(fixtureRole('PARENT', 20)))->id,
    ]);
}

function fixtureChild(array $attributes = []): Child
{
    return Child::factory()->create($attributes);
}

function fixtureSpecialist(?User $user = null): Specialist
{
    return Specialist::factory()->create([
        'user_id' => ($user ?? fixtureUser(fixtureRole('SPECIALIST', 40)))->id,
    ]);
}

function fixtureCase(?Child $child = null): CaseFile
{
    return CaseFile::factory()->create([
        'child_id' => ($child ?? fixtureChild())->id,
        'branch_id' => Branch::factory()->create()->id,
    ]);
}

function fixturePlan(?CaseFile $case = null): TreatmentPlan
{
    return TreatmentPlan::factory()->create([
        'case_id' => ($case ?? fixtureCase())->id,
        'created_by_user_id' => fixtureUser(fixtureRole('CO_ADMIN', 80))->id,
    ]);
}

function fixtureGoal(?TreatmentPlan $plan = null, array $attributes = []): Goal
{
    return Goal::factory()->create(array_merge([
        'plan_id' => ($plan ?? fixturePlan())->id,
        'specialist_id' => fixtureSpecialist()->id,
    ], $attributes));
}

function fixtureReport(?CaseFile $case = null): Report
{
    return Report::factory()->create([
        'case_id' => ($case ?? fixtureCase())->id,
        'specialist_id' => fixtureSpecialist()->id,
    ]);
}
