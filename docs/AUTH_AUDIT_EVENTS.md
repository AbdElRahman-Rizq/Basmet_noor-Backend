# Authentication Audit Events (Gate D — D1.6 / T-2.13)

This document records the authentication audit events that are **explicitly approved and
currently implementable**. It is deliberately small: login and failed-login events are
blocked by pending requirements and must not be invented.

## Implemented now

| Event | Trigger | `action` | `entity_name` | `record_id` | `old_values` | `new_values` | Notes |
|---|---|---|---|---|---|---|---|
| Logout | `POST /api/v1/logout` succeeds for an authenticated, active account | `logout` | `users` | actor user UUID | `null` | `{ "token_revoked": true \| false }` | One row per successful logout. `true` when a Sanctum personal access token was deleted; `false` for a transient stateful session with no stored token. |

The row is written through the existing `AuditLogger`, so it also records `user_id`,
historical `actor_role`, `ip_address`, `user_agent` and `created_at`. The token deletion and
the audit insert share one database transaction.

### Explicitly not stored

- passwords, password confirmations, remember tokens;
- bearer token values (plaintext or hash) and token identifiers;
- request bodies, headers or arbitrary payloads;
- child or parent names (the redaction list remains active).

### Duplicate and failure behaviour

- One audit row per successful logout request; no row is written when authentication
  fails, because the route middleware rejects the request before the controller.
- Reusing an already revoked token is rejected with `401 UNAUTHENTICATED` and writes no
  audit row.
- `audit_logs` remains append-only through the database trigger (`D-006`); the event is
  never updated or deleted by application code.

## Blocked events (do not implement yet)

| Event | Blocker | Why |
|---|---|---|
| Login success / token issuance | **P-32** | Staff login identifier is undecided and no login endpoint exists (T-2.1 blocked). |
| Failed login | **P-32** | Same endpoint prerequisite; the execution plan explicitly defers the failed-login event until P-32. |
| Parent login / token issuance | **P-01 / P-29** | Parent authentication and onboarding remain blocked. |
| Token refresh | **P-32** | No refresh flow exists. |

Token revocation caused by deactivation is already covered by the D1.3 `deactivated` audit
row (`entity_name = users`, old/new `is_active`). D1.6 does not add a second row for the
same transition, to avoid duplicate audit entries.

## Test coverage

- `tests/Feature/Auth/LogoutTest.php` — successful logout audit shape, no secret leakage,
  no audit row on unauthenticated logout.
- `tests/Feature/Audit/AuditLoggerTest.php` and `tests/Feature/Database/TriggersTest.php` —
  redaction and database-level append-only enforcement.
