<?php

use App\Models\Appointment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestion;
use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\Child;
use App\Models\ChildConsent;
use App\Models\ConsentType;
use App\Models\FunctionalScale;
use App\Models\FunctionalScaleScore;
use App\Models\Goal;
use App\Models\GoalProgressHistory;
use App\Models\InitialAssessment;
use App\Models\PlanSpecialist;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('generates unique global child sequence numbers', function () {
    $values = [];

    for ($i = 0; $i < 50; $i++) {
        $values[] = (int) DB::scalar("select nextval('child_code_sequence')");
    }

    expect($values)->toHaveCount(50)
        ->and(array_unique($values))->toHaveCount(50)
        ->and(min($values))->toBeGreaterThan(0);
});

it('creates sequential, formatted child codes with no reuse', function () {
    $first = fixtureChild();
    $second = fixtureChild();

    expect($first->child_code)->toMatch('/^[A-Z]{4}[0-9]+$/')
        ->and($second->child_code)->toMatch('/^[A-Z]{4}[0-9]+$/')
        ->and($second->sequence_number)->toBeGreaterThan($first->sequence_number)
        ->and($second->child_code)->not->toBe($first->child_code);

    $first->delete();

    $third = fixtureChild();

    expect($third->sequence_number)->toBeGreaterThan($second->sequence_number)
        ->and($third->child_code)->not->toBe($first->child_code);
});

it('requires a consistent case assignment triple', function () {
    $specialist = fixtureSpecialist();
    $assigner = fixtureUser();

    expectDbFailure(fn () => CaseFile::factory()->create([
        'specialist_id' => $specialist->id,
        'assigned_at' => null,
        'assigned_by_user_id' => null,
    ]));

    expectDbFailure(fn () => CaseFile::factory()->create([
        'specialist_id' => $specialist->id,
        'assigned_at' => now(),
        'assigned_by_user_id' => null,
    ]));

    $unassigned = CaseFile::factory()->create([
        'specialist_id' => null,
        'assigned_at' => null,
        'assigned_by_user_id' => null,
    ]);

    $assigned = CaseFile::factory()->create([
        'specialist_id' => $specialist->id,
        'assigned_at' => now(),
        'assigned_by_user_id' => $assigner->id,
    ]);

    expect($unassigned->specialist_id)->toBeNull()
        ->and($assigned->specialist_id)->toBe($specialist->id);
});

it('supports multiple specialists on a single treatment plan', function () {
    $plan = fixturePlan();
    $a = fixtureSpecialist();
    $b = fixtureSpecialist();

    PlanSpecialist::create(['plan_id' => $plan->id, 'specialist_id' => $a->id]);
    PlanSpecialist::create(['plan_id' => $plan->id, 'specialist_id' => $b->id]);

    expect($plan->specialists()->count())->toBe(2)
        ->and(PlanSpecialist::where('plan_id', $plan->id)->count())->toBe(2);
});

it('does not force goal specialists into plan_specialists', function () {
    $plan = fixturePlan();
    $attached = fixtureSpecialist();

    PlanSpecialist::create(['plan_id' => $plan->id, 'specialist_id' => $attached->id]);

    $goal = Goal::factory()->create([
        'plan_id' => $plan->id,
        'specialist_id' => fixtureSpecialist()->id,
    ]);

    expect($goal->exists)->toBeTrue()
        ->and($goal->specialist_id)->not->toBe($attached->id)
        ->and(PlanSpecialist::where('plan_id', $plan->id)->count())->toBe(1);
});

it('records a paper consent with a version and text snapshot', function () {
    $child = fixtureChild();
    $recordedBy = fixtureUser();
    $type = ConsentType::factory()->create();

    $consent = ChildConsent::create([
        'child_id' => $child->id,
        'consent_type_id' => $type->id,
        'accepted_at' => now(),
        'accepted_by_user_id' => null,
        'recorded_by_user_id' => $recordedBy->id,
        'consent_version' => 'v1',
        'consent_text_snapshot' => 'Placeholder wording supplied by the center.',
    ]);

    expect($consent->accepted_by_user_id)->toBeNull()
        ->and($consent->recorded_by_user_id)->toBe($recordedBy->id)
        ->and($consent->consent_version)->toBe('v1')
        ->and($consent->consent_text_snapshot)->not->toBeEmpty();

    expectDbFailure(fn () => ChildConsent::create([
        'child_id' => $child->id,
        'consent_type_id' => ConsentType::factory()->create()->id,
        'accepted_at' => now(),
        'recorded_by_user_id' => $recordedBy->id,
        'consent_version' => null,
        'consent_text_snapshot' => 'Text',
    ]));

    expectDbFailure(fn () => ChildConsent::create([
        'child_id' => $child->id,
        'consent_type_id' => ConsentType::factory()->create()->id,
        'accepted_at' => now(),
        'recorded_by_user_id' => $recordedBy->id,
        'consent_version' => 'v1',
        'consent_text_snapshot' => null,
    ]));
});

it('links a report to at least one goal and keeps append-only history', function () {
    $case = fixtureCase();
    $plan = fixturePlan($case);
    $goalA = fixtureGoal($plan);
    $goalB = fixtureGoal($plan);
    $report = fixtureReport($case);
    $user = fixtureUser();

    foreach ([[$goalA, 0, 25], [$goalB, 10, 40]] as [$goal, $previous, $new]) {
        GoalProgressHistory::create([
            'goal_id' => $goal->id,
            'report_id' => $report->id,
            'previous_percentage' => $previous,
            'new_percentage' => $new,
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now(),
        ]);

        $goal->update(['progress_percentage' => $new]);
    }

    expect($report->progressHistory()->count())->toBe(2)
        ->and($goalA->fresh()->progress_percentage)->toBe('25.00')
        ->and($goalB->fresh()->progress_percentage)->toBe('40.00');

    expectDbFailure(fn () => GoalProgressHistory::create([
        'goal_id' => $goalA->id,
        'report_id' => $report->id,
        'previous_percentage' => 25,
        'new_percentage' => 30,
        'recorded_by_user_id' => $user->id,
        'recorded_at' => now(),
    ]));
});

it('soft deleting a report keeps its progress history attached', function () {
    $goal = fixtureGoal();
    $report = fixtureReport($goal->plan->caseFile);

    $history = GoalProgressHistory::create([
        'goal_id' => $goal->id,
        'report_id' => $report->id,
        'previous_percentage' => 0,
        'new_percentage' => 10,
        'recorded_by_user_id' => fixtureUser()->id,
        'recorded_at' => now(),
    ]);

    $report->delete();

    expect(Report::withTrashed()->find($report->id))->not->toBeNull()
        ->and($history->fresh()->report_id)->toBe($report->id)
        ->and(DB::table('goal_progress_history')->where('id', $history->id)->value('report_id'))->toBe($report->id);
});

it('keeps question and scale snapshots after the source row changes', function () {
    $question = AssessmentQuestion::create([
        'section_name' => 'Section',
        'question_text' => 'Original question',
        'question_type' => 'text',
        'display_order' => 1,
    ]);
    $scale = FunctionalScale::factory()->create(['name' => 'Original scale']);

    $assessment = InitialAssessment::factory()->create(['child_id' => fixtureChild()->id]);

    $answer = AssessmentAnswer::create([
        'assessment_id' => $assessment->id,
        'question_id' => $question->id,
        'question_text_snapshot' => $question->question_text,
        'section_name_snapshot' => $question->section_name,
        'question_type_snapshot' => $question->question_type,
    ]);

    $score = FunctionalScaleScore::create([
        'assessment_id' => $assessment->id,
        'scale_id' => $scale->id,
        'scale_name_snapshot' => $scale->name,
        'score' => 7,
    ]);

    $question->update(['question_text' => 'Changed question']);
    $scale->update(['name' => 'Changed scale']);

    expect($answer->fresh()->question_text_snapshot)->toBe('Original question')
        ->and($score->fresh()->scale_name_snapshot)->toBe('Original scale');
});

it('allows appointments without a specialist and without a status', function () {
    $appointment = Appointment::create([
        'child_id' => fixtureChild()->id,
        'branch_id' => Branch::factory()->create()->id,
        'scheduled_by_user_id' => fixtureUser()->id,
        'appointment_date' => now()->addDay(),
    ]);

    expect($appointment->specialist_id)->toBeNull()
        ->and($appointment->status)->toBeNull();

    $specialist = fixtureSpecialist();

    $appointment->update(['specialist_id' => $specialist->id]);

    expect($appointment->fresh()->specialist_id)->toBe($specialist->id);
});

it('never stores a child name anywhere in the schema', function () {
    $token = 'ChildNameToken'.Str::random(12);

    expect(Schema::getColumnListing('children'))
        ->not->toContain('name')
        ->not->toContain('child_name')
        ->not->toContain('parent_name')
        ->not->toContain('full_name');

    // `child_name` is request-only; guarded mass assignment silently drops it.
    $sequence = (int) DB::scalar("select nextval('child_code_sequence')");

    $child = Child::create([
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

    expect($child->getAttributes())->not->toHaveKey('child_name');

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

        expect((int) $count)->toBe(0, "Child name leaked into {$column->table_name}.{$column->column_name}");
    }

    expect(Schema::getColumnListing('audit_logs'))->not->toContain('child_name');
});
