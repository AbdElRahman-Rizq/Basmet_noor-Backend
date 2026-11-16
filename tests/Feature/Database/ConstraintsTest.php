<?php

use App\Models\Appointment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentReinforcer as ReinforcerSelection;
use App\Models\Branch;
use App\Models\ChildConsent;
use App\Models\ConsentType;
use App\Models\FunctionalScale;
use App\Models\FunctionalScaleScore;
use App\Models\Goal;
use App\Models\GoalProgressHistory;
use App\Models\Homework;
use App\Models\HomeworkComment;
use App\Models\InitialAssessment;
use App\Models\ParentProfile;
use App\Models\PlanSpecialist;
use App\Models\ReinforcerCatalog;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('enforces the age range 0..120', function () {
    expectDbFailure(fn () => fixtureChild(['age' => 121]));
    expectDbFailure(fn () => fixtureChild(['age' => -1]));

    expect(fixtureChild(['age' => 0])->age)->toBe(0)
        ->and(fixtureChild(['age' => 120])->age)->toBe(120);
});

it('enforces functional scale scores 0..10 as integers', function () {
    $assessment = InitialAssessment::factory()->create(['child_id' => fixtureChild()->id]);
    $scale = FunctionalScale::factory()->create();

    $payload = fn (int $score) => [
        'assessment_id' => $assessment->id,
        'scale_id' => $scale->id,
        'scale_name_snapshot' => $scale->name,
        'score' => $score,
    ];

    expectDbFailure(fn () => FunctionalScaleScore::create($payload(11)));
    expectDbFailure(fn () => FunctionalScaleScore::create($payload(-1)));

    expect(FunctionalScaleScore::create($payload(0))->score)->toBe(0);
    expect(FunctionalScaleScore::create([
        'assessment_id' => InitialAssessment::factory()->create(['child_id' => fixtureChild()->id])->id,
        'scale_id' => $scale->id,
        'scale_name_snapshot' => $scale->name,
        'score' => 10,
    ])->score)->toBe(10);
});

it('enforces goal progress between 0 and 100', function () {
    expectDbFailure(fn () => fixtureGoal(null, ['progress_percentage' => 101]));
    expectDbFailure(fn () => fixtureGoal(null, ['progress_percentage' => -1]));

    expect(fixtureGoal(null, ['progress_percentage' => 0])->progress_percentage)->toBe('0.00')
        ->and(fixtureGoal(null, ['progress_percentage' => 100])->progress_percentage)->toBe('100.00');
});

it('enforces treatment plan date ordering', function () {
    $plan = fixturePlan();

    expectDbFailure(fn () => $plan->update([
        'start_date' => '2026-05-10',
        'end_date' => '2026-05-09',
    ]));
});

it('enforces the child code format', function () {
    foreach (['abcd1', 'ABC12', 'ABCDE1', 'ABCD', 'ABCD-1'] as $code) {
        expectDbFailure(fn () => fixtureChild(['child_code' => $code]));
    }
});

it('enforces role levels, question types and reinforcer categories', function () {
    expectDbFailure(fn () => Role::create(['name' => 'TOO_HIGH', 'level' => 101, 'is_system_role' => false]));
    expectDbFailure(fn () => Role::create(['name' => 'TOO_LOW', 'level' => -1, 'is_system_role' => false]));

    expectDbFailure(fn () => AssessmentQuestion::create([
        'section_name' => 'Section',
        'question_text' => 'Question?',
        'question_type' => 'essay',
        'display_order' => 1,
    ]));

    expectDbFailure(fn () => ReinforcerCatalog::create([
        'category' => 'toys',
        'name' => 'Bubbles',
        'display_order' => 1,
    ]));
});

it('enforces PDF-only report files with a positive size', function () {
    expectDbFailure(fn () => Report::factory()->create(['file_mime' => 'image/png']));
    expectDbFailure(fn () => Report::factory()->create(['file_size' => 0]));
    expectDbFailure(fn () => Report::factory()->create(['file_size' => -10]));

    expect(Report::factory()->create()->file_mime)->toBe('application/pdf');
});

it('enforces the unique constraints', function () {
    $child = fixtureChild();

    expectDbFailure(fn () => fixtureChild(['child_code' => $child->child_code]));
    expectDbFailure(fn () => fixtureChild(['sequence_number' => $child->sequence_number]));

    $user = fixtureUser();

    expectDbFailure(fn () => User::factory()->create(['email' => $user->email]));

    $parent = fixtureParent();

    expectDbFailure(fn () => ParentProfile::factory()->create(['user_id' => $parent->user_id]));

    $assessment = InitialAssessment::factory()->create(['child_id' => $child->id]);

    expectDbFailure(fn () => InitialAssessment::factory()->create(['child_id' => $child->id]));

    $question = AssessmentQuestion::create([
        'section_name' => 'Section',
        'question_text' => 'Question?',
        'question_type' => 'text',
        'display_order' => 1,
    ]);

    $answer = [
        'assessment_id' => $assessment->id,
        'question_id' => $question->id,
        'question_text_snapshot' => $question->question_text,
        'section_name_snapshot' => $question->section_name,
        'question_type_snapshot' => $question->question_type,
        'answer_value' => 'Yes',
    ];

    AssessmentAnswer::create($answer);
    expectDbFailure(fn () => AssessmentAnswer::create($answer));

    $scale = FunctionalScale::factory()->create();

    $score = [
        'assessment_id' => $assessment->id,
        'scale_id' => $scale->id,
        'scale_name_snapshot' => $scale->name,
        'score' => 5,
    ];

    FunctionalScaleScore::create($score);
    expectDbFailure(fn () => FunctionalScaleScore::create($score));

    $reinforcer = ReinforcerCatalog::factory()->create();

    $selection = [
        'assessment_id' => $assessment->id,
        'reinforcer_id' => $reinforcer->id,
        'reinforcer_name_snapshot' => $reinforcer->name,
        'category_snapshot' => $reinforcer->category,
    ];

    ReinforcerSelection::create($selection);
    expectDbFailure(fn () => ReinforcerSelection::create($selection));

    $consentType = ConsentType::factory()->create();
    $recordedBy = fixtureUser();
    $consent = [
        'child_id' => $child->id,
        'consent_type_id' => $consentType->id,
        'accepted_at' => now(),
        'accepted_by_user_id' => null,
        'recorded_by_user_id' => $recordedBy->id,
        'consent_version' => 'v1',
        'consent_text_snapshot' => 'Placeholder consent text approved for tests.',
    ];

    ChildConsent::create($consent);
    expectDbFailure(fn () => ChildConsent::create($consent));
});

it('allows reusing a soft-deleted branch name only', function () {
    $branch = Branch::factory()->create(['name' => 'Duplicate Name']);

    expectDbFailure(fn () => Branch::factory()->create(['name' => 'Duplicate Name']));

    $branch->delete();

    expect(Branch::factory()->create(['name' => 'Duplicate Name'])->name)->toBe('Duplicate Name');
});

it('enforces one progress row per report and goal, but allows report-less history', function () {
    $goal = fixtureGoal();
    $report = fixtureReport($goal->plan->caseFile);
    $user = fixtureUser();

    $row = fn (?string $reportId) => [
        'goal_id' => $goal->id,
        'report_id' => $reportId,
        'previous_percentage' => 0,
        'new_percentage' => 10,
        'recorded_by_user_id' => $user->id,
        'recorded_at' => now(),
    ];

    GoalProgressHistory::create($row($report->id));
    expectDbFailure(fn () => GoalProgressHistory::create($row($report->id)));

    GoalProgressHistory::create($row(null));
    GoalProgressHistory::create($row(null));

    expect(GoalProgressHistory::whereNull('report_id')->count())->toBe(2);
});

it('blocks hard deletes through RESTRICT foreign keys', function () {
    $child = fixtureChild();
    fixtureCase($child);

    expectDbFailure(fn () => $child->forceDelete());

    $auditUser = fixtureUser();
    DB::table('audit_logs')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $auditUser->id,
        'action' => 'created',
        'entity_name' => 'children',
        'created_at' => now(),
    ]);

    expectDbFailure(fn () => $auditUser->forceDelete());

    $goal = fixtureGoal();
    $report = fixtureReport($goal->plan->caseFile);

    GoalProgressHistory::create([
        'goal_id' => $goal->id,
        'report_id' => $report->id,
        'previous_percentage' => 0,
        'new_percentage' => 5,
        'recorded_by_user_id' => fixtureUser()->id,
        'recorded_at' => now(),
    ]);

    expectDbFailure(fn () => $report->forceDelete());

    $consentType = ConsentType::factory()->create();
    ChildConsent::create([
        'child_id' => fixtureChild()->id,
        'consent_type_id' => $consentType->id,
        'accepted_at' => now(),
        'recorded_by_user_id' => fixtureUser()->id,
        'consent_version' => 'v1',
        'consent_text_snapshot' => 'Placeholder consent text approved for tests.',
    ]);

    expectDbFailure(fn () => $consentType->delete());

    $specialist = fixtureSpecialist();
    Goal::factory()->create([
        'plan_id' => fixturePlan()->id,
        'specialist_id' => $specialist->id,
    ]);

    expectDbFailure(fn () => $specialist->forceDelete());
});

it('cascades the approved child rows', function () {
    $assessment = InitialAssessment::factory()->create(['child_id' => fixtureChild()->id]);
    $question = AssessmentQuestion::create([
        'section_name' => 'Section',
        'question_text' => 'Question?',
        'question_type' => 'text',
        'display_order' => 1,
    ]);
    $scale = FunctionalScale::factory()->create();
    $reinforcer = ReinforcerCatalog::factory()->create();

    AssessmentAnswer::create([
        'assessment_id' => $assessment->id,
        'question_id' => $question->id,
        'question_text_snapshot' => $question->question_text,
        'section_name_snapshot' => $question->section_name,
        'question_type_snapshot' => $question->question_type,
    ]);
    FunctionalScaleScore::create([
        'assessment_id' => $assessment->id,
        'scale_id' => $scale->id,
        'scale_name_snapshot' => $scale->name,
        'score' => 5,
    ]);
    ReinforcerSelection::create([
        'assessment_id' => $assessment->id,
        'reinforcer_id' => $reinforcer->id,
        'reinforcer_name_snapshot' => $reinforcer->name,
        'category_snapshot' => $reinforcer->category,
    ]);

    $assessment->forceDelete();

    expect(AssessmentAnswer::count())->toBe(0)
        ->and(FunctionalScaleScore::count())->toBe(0)
        ->and(ReinforcerSelection::count())->toBe(0);

    $plan = fixturePlan();
    PlanSpecialist::create(['plan_id' => $plan->id, 'specialist_id' => fixtureSpecialist()->id]);

    $plan->forceDelete();

    expect(PlanSpecialist::count())->toBe(0);

    $homework = Homework::create([
        'case_id' => fixtureCase()->id,
        'created_by_user_id' => fixtureUser()->id,
        'title' => 'Homework',
        'description' => 'Do the thing',
    ]);
    HomeworkComment::create([
        'homework_id' => $homework->id,
        'user_id' => fixtureUser()->id,
        'body' => 'Comment',
    ]);

    $homework->forceDelete();

    expect(HomeworkComment::count())->toBe(0);
});

it('applies SET NULL to optional actors', function () {
    $admin = fixtureUser();
    $created = fixtureUser(null, ['created_by_user_id' => $admin->id]);

    $admin->forceDelete();

    expect($created->fresh()->created_by_user_id)->toBeNull();

    $scheduler = fixtureUser();
    $appointment = Appointment::create([
        'child_id' => fixtureChild()->id,
        'branch_id' => Branch::factory()->create()->id,
        'scheduled_by_user_id' => $scheduler->id,
        'appointment_date' => now()->addDay(),
    ]);

    $scheduler->forceDelete();
    expect($appointment->fresh()->scheduled_by_user_id)->toBeNull();

    $completer = fixtureUser();
    $homework = Homework::create([
        'case_id' => fixtureCase()->id,
        'created_by_user_id' => fixtureUser()->id,
        'title' => 'Homework',
        'description' => 'Do the thing',
        'completed_by_user_id' => $completer->id,
    ]);

    $completer->forceDelete();
    expect($homework->fresh()->completed_by_user_id)->toBeNull();
});
