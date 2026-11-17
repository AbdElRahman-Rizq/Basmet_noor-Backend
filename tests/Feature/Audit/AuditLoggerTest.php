<?php

use App\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function d05Request(): Request
{
    return Request::create('/api/v1/test', 'POST', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Basma Audit Test/1.0',
    ]);
}

function d05Log(
    ?User $actor,
    ?array $oldValues = null,
    ?array $newValues = null,
): AuditLog {
    return app(AuditLogger::class)->log(
        actor: $actor,
        action: 'created',
        entityName: 'branches',
        recordId: (string) Str::uuid(),
        oldValues: $oldValues,
        newValues: $newValues,
        request: d05Request(),
    );
}

it('writes a complete audit record', function () {
    $actor = fixtureUser(fixtureRole('CO_ADMIN', 80));
    $recordId = (string) Str::uuid();

    $log = app(AuditLogger::class)->log(
        actor: $actor,
        action: 'created',
        entityName: 'branches',
        recordId: $recordId,
        newValues: ['name' => 'Main Branch', 'is_active' => true],
        request: d05Request(),
    );

    $log->refresh();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->actor_role)->toBe('CO_ADMIN')
        ->and($log->action)->toBe('created')
        ->and($log->entity_name)->toBe('branches')
        ->and($log->record_id)->toBe($recordId)
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toEqual(['name' => 'Main Branch', 'is_active' => true])
        ->and($log->ip_address)->toBe('203.0.113.10')
        ->and($log->user_agent)->toBe('Basma Audit Test/1.0')
        ->and($log->created_at)->not->toBeNull();
});

it('redacts every sensitive top-level field', function () {
    $log = d05Log(
        fixtureUser(),
        oldValues: [
            'password' => 'old-password-value',
            'remember_token' => 'old-remember-value',
        ],
        newValues: [
            'name' => 'Safe Name',
            'password' => 'new-password-value',
            'password_confirmation' => 'confirmation-value',
            'token' => 'token-value',
            'remember_token' => 'remember-value',
            'child_name' => 'Child Name Value',
            'parent_name' => 'Parent Name Value',
        ],
    );

    $log->refresh();

    expect($log->old_values)->toEqual([
        'password' => AuditLogger::REDACTED,
        'remember_token' => AuditLogger::REDACTED,
    ])->and($log->new_values)->toEqual([
        'name' => 'Safe Name',
        'password' => AuditLogger::REDACTED,
        'password_confirmation' => AuditLogger::REDACTED,
        'token' => AuditLogger::REDACTED,
        'remember_token' => AuditLogger::REDACTED,
        'child_name' => AuditLogger::REDACTED,
        'parent_name' => AuditLogger::REDACTED,
    ]);
});

it('redacts sensitive fields recursively', function () {
    $log = d05Log(fixtureUser(), newValues: [
        'profile' => [
            'name' => 'Safe Profile',
            'password' => 'nested-password-value',
        ],
        'items' => [
            ['label' => 'first', 'token' => 'nested-token-value'],
            ['child_name' => 'Nested Child Value', 'note' => 'keep me'],
        ],
        'PASSWORD' => 'uppercase-password-value',
    ]);

    $log->refresh();

    expect($log->new_values)->toEqual([
        'profile' => [
            'name' => 'Safe Profile',
            'password' => AuditLogger::REDACTED,
        ],
        'items' => [
            ['label' => 'first', 'token' => AuditLogger::REDACTED],
            ['child_name' => AuditLogger::REDACTED, 'note' => 'keep me'],
        ],
        'PASSWORD' => AuditLogger::REDACTED,
    ]);
});

it('preserves non-sensitive metadata unchanged', function () {
    $metadata = [
        'status' => 'active',
        'role' => ['name' => 'SPECIALIST', 'level' => 40],
        'count' => 3,
        'flags' => ['enabled' => true, 'archived' => false],
        'notes' => 'Visible operational note',
    ];

    $log = d05Log(fixtureUser(), newValues: $metadata);
    $log->refresh();

    expect($log->new_values)->toEqual($metadata);
});

it('keeps audit records append-only at the database level', function () {
    $log = d05Log(fixtureUser(), newValues: ['status' => 'open']);

    expectDbFailure(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']));
    expectDbFailure(fn () => DB::table('audit_logs')->where('id', $log->id)->delete());

    expect($log->fresh()->action)->toBe('created');
});

it('accepts a null actor for system events', function () {
    $log = app(AuditLogger::class)->log(
        actor: null,
        action: 'system',
        entityName: 'system',
        newValues: ['event' => 'cleanup'],
    );

    $log->refresh();

    expect($log->user_id)->toBeNull()
        ->and($log->actor_role)->toBeNull()
        ->and($log->ip_address)->toBeNull()
        ->and($log->user_agent)->toBeNull()
        ->and($log->new_values)->toEqual(['event' => 'cleanup']);
});

it('never persists sensitive values in the audit row', function () {
    $markers = [
        'SECRET-PASSWORD',
        'SECRET-CONFIRMATION',
        'SECRET-TOKEN',
        'SECRET-REMEMBER',
        'SECRET-CHILD-NAME',
        'SECRET-PARENT-NAME',
        'SECRET-NESTED-TOKEN',
    ];

    $log = d05Log(fixtureUser(), newValues: [
        'password' => 'SECRET-PASSWORD',
        'password_confirmation' => 'SECRET-CONFIRMATION',
        'token' => 'SECRET-TOKEN',
        'remember_token' => 'SECRET-REMEMBER',
        'child_name' => 'SECRET-CHILD-NAME',
        'parent_name' => 'SECRET-PARENT-NAME',
        'nested' => ['token' => 'SECRET-NESTED-TOKEN'],
    ]);

    $raw = json_encode(DB::table('audit_logs')->where('id', $log->id)->first());

    foreach ($markers as $marker) {
        expect($raw)->not->toContain($marker);
    }
});
