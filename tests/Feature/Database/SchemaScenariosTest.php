<?php

use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestion;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Child;
use App\Models\ChildConsent;
use App\Models\ConsentType;
use App\Models\FunctionalScale;
use App\Models\FunctionalScaleScore;
use App\Models\GoalProgressHistory;
use App\Models\InitialAssessment;
use App\Models\ParentProfile;
use App\Models\Report;
use App\Models\Specialist;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('scenario 2: 50 nextval calls return 50 distinct numbers', function () {
    $numbers = [];

    for ($i = 0; $i < 50; $i++) {
        $numbers[] = (int) DB::scalar("select nextval('child_code_sequence')");
    }

    expect(array_unique($numbers))->toHaveCount(50);
});

it('scenario 3: a functional scale score of 11 is rejected', function () {
    $assessment = InitialAssessment::factory()->create(['child_id' => fixtureChild()->id]);
    $scale = FunctionalScale::factory()->create();

    expectDbFailure(fn () => FunctionalScaleScore::create([
        'assessment_id' => $assessment->id,
        'scale_id' => $scale->id,
        'scale_name_snapshot' => $scale->name,
        'score' => 11,
    ]));
});

it('scenario 4: a goal progress of -1 is rejected', function () {
    expectDbFailure(fn () => fixtureGoal(null, ['progress_percentage' => -1]));
});

it('scenario 5: audit_logs cannot be updated or deleted', function () {
    $log = AuditLog::create([
        'user_id' => fixtureUser()->id,
        'action' => 'created',
        'entity_name' => 'children',
    ]);

    expectDbFailure(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'updated']));
    expectDbFailure(fn () => DB::table('audit_logs')->where('id', $log->id)->delete());
});

it('scenario 6: goal_progress_history cannot be updated or deleted', function () {
    $goal = fixtureGoal();
    $row = GoalProgressHistory::create([
        'goal_id' => $goal->id,
        'previous_percentage' => 0,
        'new_percentage' => 10,
        'recorded_by_user_id' => fixtureUser()->id,
        'recorded_at' => now(),
    ]);

    expectDbFailure(fn () => DB::table('goal_progress_history')->where('id', $row->id)->update(['new_percentage' => 20]));
    expectDbFailure(fn () => DB::table('goal_progress_history')->where('id', $row->id)->delete());
});

it('scenario 7: child consents expose no delete path in V1', function () {
    // The prohibition lives in the application layer (no revocation/correction
    // endpoint, D-050/C0). No database trigger was approved for this table.
    $triggers = DB::select("select tgname from pg_trigger where not tgisinternal and tgrelid = 'child_consents'::regclass");

    expect($triggers)->toBeEmpty();
});

it('scenario 8: editing a question keeps the old answer snapshot', function () {
    $question = AssessmentQuestion::create([
        'section_name' => 'Section',
        'question_text' => 'Original question',
        'question_type' => 'text',
        'display_order' => 1,
    ]);
    $assessment = InitialAssessment::factory()->create(['child_id' => fixtureChild()->id]);

    $answer = AssessmentAnswer::create([
        'assessment_id' => $assessment->id,
        'question_id' => $question->id,
        'question_text_snapshot' => $question->question_text,
        'section_name_snapshot' => $question->section_name,
        'question_type_snapshot' => $question->question_type,
    ]);

    $question->update(['question_text' => 'Edited question']);

    expect($answer->fresh()->question_text_snapshot)->toBe('Original question');
});

it('scenario 9: editing a scale keeps the old score snapshot', function () {
    $scale = FunctionalScale::factory()->create(['name' => 'Original scale']);
    $assessment = InitialAssessment::factory()->create(['child_id' => fixtureChild()->id]);

    $score = FunctionalScaleScore::create([
        'assessment_id' => $assessment->id,
        'scale_id' => $scale->id,
        'scale_name_snapshot' => $scale->name,
        'score' => 5,
    ]);

    $scale->update(['name' => 'Edited scale']);

    expect($score->fresh()->scale_name_snapshot)->toBe('Original scale');
});

it('scenario 10: soft deleting a report keeps the history reference', function () {
    $report = fixtureReport();
    $history = GoalProgressHistory::create([
        'goal_id' => fixtureGoal()->id,
        'report_id' => $report->id,
        'previous_percentage' => 0,
        'new_percentage' => 10,
        'recorded_by_user_id' => fixtureUser()->id,
        'recorded_at' => now(),
    ]);

    $report->delete();

    expect($history->fresh()->report_id)->toBe($report->id);
});

it('scenario 11: hard deleting a report with history is rejected', function () {
    $report = fixtureReport();
    GoalProgressHistory::create([
        'goal_id' => fixtureGoal()->id,
        'report_id' => $report->id,
        'previous_percentage' => 0,
        'new_percentage' => 10,
        'recorded_by_user_id' => fixtureUser()->id,
        'recorded_at' => now(),
    ]);

    expectDbFailure(fn () => $report->forceDelete());
});

it('scenario 12: case assignment consistency is enforced at database level', function () {
    // The 422 response for creating a plan without an assigned specialist is a
    // Gate D application rule; the database guarantees the triple is coherent.
    $specialist = fixtureSpecialist();

    expectDbFailure(fn () => CaseFile::factory()->create([
        'specialist_id' => $specialist->id,
        'assigned_at' => null,
        'assigned_by_user_id' => null,
    ]));

    $case = CaseFile::factory()->create([
        'specialist_id' => null,
        'assigned_at' => null,
        'assigned_by_user_id' => null,
    ]);

    expect(TreatmentPlan::factory()->create(['case_id' => $case->id])->exists)->toBeTrue();
});

it('scenario 13: non-PDF reports are rejected before any file is stored', function () {
    // File storage and the 422 contract are Gate D; the database rejects any
    // non-PDF mime and non-positive size.
    expectDbFailure(fn () => Report::factory()->create(['file_mime' => 'text/plain']));
    expectDbFailure(fn () => Report::factory()->create(['file_size' => 0]));
});

it('scenario 14: the cross-case goal check is a Gate D validation', function () {
    // No database constraint can tie reports.case_id to goals.plan_id.case_id.
    // Gate D must return 422; the schema only guards the storage rules.
    $report = fixtureReport();
    $foreignGoal = fixtureGoal();

    $history = GoalProgressHistory::create([
        'goal_id' => $foreignGoal->id,
        'report_id' => $report->id,
        'previous_percentage' => 0,
        'new_percentage' => 10,
        'recorded_by_user_id' => fixtureUser()->id,
        'recorded_at' => now(),
    ]);

    expect($history->exists)->toBeTrue()
        ->and($foreignGoal->plan->case_id)->not->toBe($report->case_id);
});

it('scenario 15: the three-consents rule is a Gate D transaction', function () {
    // The database intentionally allows a child without consent rows; Gate D
    // wraps child creation + all required consents in one transaction.
    $child = fixtureChild();

    expect(Child::find($child->id))->not->toBeNull()
        ->and(ConsentType::where('is_required', true)->count())->toBe(0);
});

it('scenario 16: a second child for the same parent reuses the parent profile', function () {
    $parent = fixtureParent();
    $usersBefore = User::count();

    $first = fixtureChild(['parent_id' => $parent->id]);
    $second = fixtureChild(['parent_id' => $parent->id]);

    expect($second->parent_id)->toBe($first->parent_id)
        ->and(ParentProfile::count())->toBe(1)
        ->and(User::count())->toBe($usersBefore);
});

it('scenario 17: duplicate parent identifiers are rejected', function () {
    $parent = fixtureParent();

    expectDbFailure(fn () => ParentProfile::factory()->create(['user_id' => $parent->user_id]));
    expectDbFailure(fn () => User::factory()->create(['email' => $parent->user->email]));
});

it('scenario 18: child names never reach the database', function () {
    $token = 'ChildNameToken'.Str::random(12);

    $sequence = (int) DB::scalar("select nextval('child_code_sequence')");

    Child::create([
        'parent_id' => fixtureParent()->id,
        'child_code' => 'ABCD'.$sequence,
        'sequence_number' => $sequence,
        'age' => 6,
        'age_recorded_at' => now()->toDateString(),
        'nationality' => 'Test',
        'neighborhood' => 'Test',
        'initial_problem' => 'Test',
        'child_name' => $token,
    ]);

    $columns = DB::select(<<<'SQL'
        select table_name, column_name
        from information_schema.columns
        where table_schema = 'public'
          and data_type in ('text', 'character varying', 'character')
    SQL);

    foreach ($columns as $column) {
        $count = DB::scalar(sprintf(
            'select count(*) from "%s" where "%s"::text = ?',
            $column->table_name,
            $column->column_name,
        ), [$token]);

        expect((int) $count)->toBe(0);
    }
});

it('scenario 19: an invalid child code is rejected', function () {
    foreach (['abcd1', 'ABC12', 'ABCDE1', 'ABCD', '1ABCD'] as $code) {
        expectDbFailure(fn () => fixtureChild(['child_code' => $code]));
    }
});

it('scenario 20: a consent is recorded by staff with no digital acceptor (D-050)', function () {
    // The v4 scenario described digital acceptance; D-050 replaced it with a
    // paper record: accepted_by_user_id = NULL, recorded_by_user_id = staff.
    $recordedBy = fixtureUser();
    $consent = ChildConsent::create([
        'child_id' => fixtureChild()->id,
        'consent_type_id' => ConsentType::factory()->create()->id,
        'accepted_at' => now(),
        'accepted_by_user_id' => null,
        'recorded_by_user_id' => $recordedBy->id,
        'consent_version' => 'v1',
        'consent_text_snapshot' => 'Center-approved placeholder wording.',
    ]);

    expect($consent->accepted_by_user_id)->toBeNull()
        ->and($consent->recorded_by_user_id)->toBe($recordedBy->id);
});

it('scenario 21: hard deleting a user with audit logs is rejected', function () {
    $user = fixtureUser();
    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'created',
        'entity_name' => 'children',
    ]);

    expectDbFailure(fn () => $user->forceDelete());
});

it('scenario 22: hidden reports are stored with visible_to_parent = false', function () {
    $report = Report::factory()->create(['visible_to_parent' => false]);

    expect($report->fresh()->visible_to_parent)->toBeFalse();

    // The 404 for parents is a Gate D authorization rule (P-07); the schema
    // only stores the visibility flag.
});
