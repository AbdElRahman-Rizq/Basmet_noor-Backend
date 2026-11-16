<?php

use App\Models\AuditLog;
use App\Models\GoalProgressHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('makes audit_logs append-only', function () {
    $log = AuditLog::create([
        'user_id' => fixtureUser()->id,
        'actor_role' => 'CO_ADMIN',
        'action' => 'created',
        'entity_name' => 'cases',
        'record_id' => null,
        'old_values' => null,
        'new_values' => ['status' => 'open'],
    ]);

    expectDbFailure(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'updated']));
    expectDbFailure(fn () => DB::table('audit_logs')->where('id', $log->id)->delete());

    expect(DB::table('audit_logs')->where('id', $log->id)->value('action'))->toBe('created');
});

it('makes goal_progress_history append-only', function () {
    $goal = fixtureGoal();
    $row = GoalProgressHistory::create([
        'goal_id' => $goal->id,
        'report_id' => null,
        'previous_percentage' => 0,
        'new_percentage' => 10,
        'recorded_by_user_id' => fixtureUser()->id,
        'recorded_at' => now(),
    ]);

    expectDbFailure(fn () => DB::table('goal_progress_history')->where('id', $row->id)->update(['new_percentage' => 20]));
    expectDbFailure(fn () => DB::table('goal_progress_history')->where('id', $row->id)->delete());

    expect(DB::table('goal_progress_history')->where('id', $row->id)->value('new_percentage'))->toBe('10.00');
});

it('does not put an unapproved immutability trigger on child_consents', function () {
    $triggers = collect(DB::select("select tgname from pg_trigger where not tgisinternal and tgrelid = 'child_consents'::regclass"))
        ->pluck('tgname');

    // D-050/C0: revocation and correction are forbidden by the application layer
    // in V1; no extra database trigger is approved.
    expect($triggers)->toBeEmpty();
});
