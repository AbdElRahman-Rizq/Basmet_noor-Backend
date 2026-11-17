<?php

namespace App\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;
use stdClass;

/**
 * Append-only audit writer.
 *
 * Callers pass explicit old/new values; request bodies are never captured
 * wholesale. Sensitive keys are redacted recursively before persistence.
 */
final class AuditLogger
{
    public const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'remember_token',
        'child_name',
        'parent_name',
    ];

    /**
     * @param  array<array-key, mixed>|null  $oldValues
     * @param  array<array-key, mixed>|null  $newValues
     */
    public function log(
        ?User $actor,
        string $action,
        string $entityName,
        ?string $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): AuditLog {
        $userAgent = $request?->userAgent();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'actor_role' => $actor?->role?->name,
            'action' => Str::limit($action, 100, ''),
            'entity_name' => Str::limit($entityName, 100, ''),
            'record_id' => $recordId,
            'old_values' => $oldValues === null ? null : $this->redact($oldValues),
            'new_values' => $newValues === null ? null : $this->redact($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = $this->redactValue($value);
        }

        return $redacted;
    }

    private function redactValue(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif ($value instanceof stdClass) {
            $value = (array) $value;
        }

        return is_array($value) ? $this->redact($value) : $value;
    }

    private function isSensitive(string $key): bool
    {
        return in_array(mb_strtolower($key), self::SENSITIVE_KEYS, true);
    }
}
