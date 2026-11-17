<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('runs every migration against the isolated test database', function () {
    expect(DB::selectOne('select current_database() as db')->db)->toBe('basma_noor_test');

    $applied = DB::table('migrations')->pluck('migration');

    expect($applied)->toHaveCount(19)
        ->and($applied->first())->toContain('000001_create_roles_permissions_tables')
        ->and($applied->contains('2026_11_17_000018_create_audit_logs_table'))->toBeTrue()
        ->and($applied->last())->toContain('102008_create_personal_access_tokens_table');
});

it('creates every approved table only', function () {
    $expected = [
        'roles', 'permissions', 'role_permissions',
        'users', 'user_permissions',
        'parents', 'specialists',
        'branches', 'knowledge_sources',
        'children', 'cases',
        'initial_assessments', 'assessment_questions', 'assessment_answers',
        'functional_scales', 'functional_scale_scores',
        'reinforcer_catalog', 'assessment_reinforcers',
        'consent_types', 'child_consents',
        'treatment_plans', 'plan_specialists', 'goals',
        'reports', 'goal_progress_history',
        'homework', 'homework_comments',
        'appointments', 'notifications', 'audit_logs',
        // Gate D / T-2.0 — framework token table, not a domain table.
        'personal_access_tokens',
    ];

    foreach ($expected as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }

    $forbidden = ['final_assessments', 'session_notes', 'sessions', 'report_goals', 'user_roles', 'payments', 'attachments'];

    foreach ($forbidden as $table) {
        expect(Schema::hasTable($table))->toBeFalse("Forbidden table exists: {$table}");
    }
});

it('keeps age columns and never adds date_of_birth', function () {
    $columns = Schema::getColumnListing('children');

    expect($columns)->toContain('age', 'age_recorded_at')
        ->and($columns)->not->toContain('date_of_birth');
});

it('stores the approved numeric types and nullability', function () {
    $childConsents = collect(DB::select(
        "select column_name, is_nullable from information_schema.columns where table_name = 'child_consents'"
    ))->keyBy('column_name');

    expect($childConsents['consent_version']->is_nullable)->toBe('NO')
        ->and($childConsents['consent_text_snapshot']->is_nullable)->toBe('NO')
        ->and($childConsents['accepted_by_user_id']->is_nullable)->toBe('YES')
        ->and($childConsents['recorded_by_user_id']->is_nullable)->toBe('NO');

    $scores = collect(DB::select(
        "select data_type from information_schema.columns where table_name = 'functional_scale_scores' and column_name = 'score'"
    ))->first();

    expect($scores->data_type)->toBe('smallint');

    $progress = collect(DB::select(
        "select data_type, numeric_precision, numeric_scale from information_schema.columns where table_name = 'goals' and column_name = 'progress_percentage'"
    ))->first();

    expect($progress->data_type)->toBe('numeric')
        ->and((int) $progress->numeric_precision)->toBe(5)
        ->and((int) $progress->numeric_scale)->toBe(2);

    $reports = Schema::getColumnListing('reports');

    expect($reports)->not->toContain('status');
});

it('creates the approved CHECK constraints', function () {
    $names = collect(DB::select("select conname from pg_constraint where contype = 'c'"))
        ->pluck('conname');

    $expected = [
        'roles_level_check',
        'children_age_check',
        'children_child_code_format_check',
        'functional_scale_scores_score_check',
        'cases_assignment_consistency_check',
        'assessment_questions_type_check',
        'reinforcer_catalog_category_check',
        'treatment_plans_dates_check',
        'goals_progress_percentage_check',
        'goal_progress_history_previous_check',
        'goal_progress_history_new_check',
        'reports_file_mime_check',
        'reports_file_size_check',
    ];

    foreach ($expected as $constraint) {
        expect($names)->toContain($constraint);
    }
});

it('creates the partial unique indexes', function () {
    $indexes = collect(DB::select("select indexname from pg_indexes where schemaname = 'public'"))
        ->pluck('indexname');

    expect($indexes)->toContain('branches_name_active_unique')
        ->and($indexes)->toContain('goal_progress_history_report_goal_unique')
        ->and($indexes)->not->toContain('report_goals_pkey');
});

it('creates the append-only triggers', function () {
    $triggers = collect(DB::select('select tgname from pg_trigger where not tgisinternal'))
        ->pluck('tgname');

    expect($triggers)->toContain('audit_logs_immutable')
        ->and($triggers)->toContain('goal_progress_history_immutable')
        ->and($triggers)->not->toContain('child_consents_immutable');
});

it('creates the global child code sequence', function () {
    $exists = DB::selectOne("select 1 as found from pg_class where relkind = 'S' and relname = 'child_code_sequence'");

    expect($exists)->not->toBeNull();
});

it('uses the approved foreign key delete behaviour', function () {
    $rules = collect(DB::select('select conname, confdeltype from pg_constraint where contype = \'f\''))
        ->pluck('confdeltype', 'conname');

    // r = RESTRICT, c = CASCADE, n = SET NULL
    expect($rules['children_parent_id_foreign'])->toBe('r')
        ->and($rules['cases_child_id_foreign'])->toBe('r')
        ->and($rules['child_consents_recorded_by_user_id_foreign'])->toBe('r')
        ->and($rules['child_consents_accepted_by_user_id_foreign'])->toBe('r')
        ->and($rules['goal_progress_history_report_id_foreign'])->toBe('r')
        ->and($rules['audit_logs_user_id_foreign'])->toBe('r')
        ->and($rules['plan_specialists_plan_id_foreign'])->toBe('c')
        ->and($rules['assessment_answers_assessment_id_foreign'])->toBe('c')
        ->and($rules['homework_comments_homework_id_foreign'])->toBe('c')
        ->and($rules['notifications_recipient_user_id_foreign'])->toBe('c')
        ->and($rules['users_created_by_user_id_foreign'])->toBe('n')
        ->and($rules['appointments_scheduled_by_user_id_foreign'])->toBe('n')
        ->and($rules['homework_completed_by_user_id_foreign'])->toBe('n');
});
