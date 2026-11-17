# Gate D — Execution Plan

**Project:** Basmat Nour · **Baseline:** `1e8fbd3` (Gate C, unchanged)
**Date:** 2026-11-17 · **Status:** planning only — no Gate D code has been written.

---

## 1. Gate C closure verdict

**Verdict: PASS — Gate C is closed. No migration or schema change is required.**

| # | Check | Result | Evidence (verified against the live `basma_noor` DB and commit `1e8fbd3`) |
|---|---|---|---|
| 1 | Approved schema implemented | ✅ | 18 migrations · 30 domain tables + `migrations`, exactly the 30 tables defined in `DATABASE_DESIGN.md §4` · 0 PostgreSQL enums · 0 schema drift since the commit |
| 2 | P-12 / P-20 / P-31 / P-15 consistent | ✅ | P-12: `consent_version` + `consent_text_snapshot` **NOT NULL**, `accepted_by_user_id` nullable, `recorded_by_user_id` NOT NULL, **0 triggers** on `child_consents`. P-20: 2 append-only triggers, `RESTRICT` FKs, no purge/anonymization objects. P-31: **no** `report_goals`, **no** `reports.status`, partial unique `(report_id, goal_id)`. P-15: `appointments.specialist_id` nullable, `status varchar NULL`, **0 CHECK constraints**, no cancel/reschedule columns |
| 3 | D-046 / D-047 / D-048 / D-049 respected | ✅ | `children.age smallint` + `age_recorded_at date`, **no `date_of_birth`** · `functional_scale_scores.score smallint` + `CHECK 0..10` · **no** `final_assessments`, `session_notes`, `sessions` |
| 4 | No unapproved schema changes | ✅ | Table set matches design exactly; no extra table/column was introduced. Missing tables are absent by decision, not by omission |
| 5 | No pending requirement silently approved | ✅ | P-04 (parent name), P-09 (scale names), P-03, P-07, P-02, P-16, P-19, P-26, P-27 remain open/blocked in docs and roadmap |
| 6 | Historical readiness reports untouched | ✅ | Both remain **untracked**, not part of `1e8fbd3`, unmodified: `GATE_C_READINESS_REPORT.md` `90DCEB04…` · `GATE_C_FINAL_READINESS_REPORT.md` `F638C786…` |

**Executable proof:** `89 passed (483 assertions)` · `Pint PASS — 108 files` · `git diff 1e8fbd3 -- database app/Models database/seeders database/factories` is empty.

### Terminology correction (important, does not change the verdict)
- **Age** is locked by **D-046** (it closes the age sub-question of **P-04**). The **remaining** P-04 question — storing the parent's name — is **still open**.
- **Integer scores** are locked by **D-047** (it closes the type sub-question of **P-09**). The **remaining** P-09 question — the ten scale names/descriptions — is **still open**.
- P-21/P-22 are outside V1 via **D-048/D-049**.
Do not treat P-04 or P-09 as fully approved in Gate D.

### Non-blocking documentation nit
`docs/PROJECT_ROADMAP.md` line 113 says **14 factories**; the committed implementation and `GATE_C_IMPLEMENTATION_REPORT.md` say **15**. Cosmetic only — no code/schema impact. Fix in a docs-only commit when convenient.

---

## 2. Remaining blockers

Severity: 🔴 blocks release · 🟠 blocks a feature · 🟡 edge case.

| ID | Open question | Blocks | Severity | Can Gate D proceed without it? |
|---|---|---|---|---|
| **P-32** | Staff login by email, mobile, or both? | T-2.1 login, T-2.13 failed-login audit | 🔴 | Everything except the login endpoint. Use Sanctum test tokens meanwhile. **Production login impossible** |
| **P-01** | Parent login mechanism | T-2.3, all parent-facing endpoints | 🔴 for parent app | Yes for the staff API; parent API cannot ship |
| **P-29** | Who creates the parent account / how credentials are delivered | T-3.6b, parent onboarding | 🔴 for parent app | Yes for staff API; no real parent can be onboarded |
| **P-02** | RECEPTION role/permissions | T-2.6b, reception operations | 🟠 | Yes — CO_ADMIN/SUPER_ADMIN can operate V1; RECEPTION stays disabled |
| **P-03** | Child-code edge cases/fallback | T-3.4 | 🟡 | Yes — happy path defined by D-041; reject invalid names, no manual fallback |
| **P-07** | Who may hide a report + replace/delete workflow | T-6.7; replace/delete | 🟠 | Yes — reports are create-only, hidden flag untouched except seeded SUPER_ADMIN permission |
| **P-26** | Max PDF size, retention, parent download | T-6.2 size limit, T-6.8 parent download | 🟠 | Partially — use a clearly-marked config placeholder, enforce private storage; retention = keep file (D-038/D-042) |
| **P-27** | May progress decrease? | Report progress validation | 🟡 | Yes — do **not** invent a rejection rule; store `previous/new` and accept 0–100 |
| **P-16** | Notification channels/recipients/auto-creation | T-7.4 auto notifications | 🟠 | Yes — list/mark-read endpoints only; no automatic notifications |
| **P-19** | Editing child data | T-3.11 | 🟠 | Yes — defer edit endpoints |
| **P-05 / P-08** | Case status values / assessment edit | status workflow, edit assessment | 🟡 | Yes — both remain immutable/null |
| **P-10 / P-09** | Assessment question content / scale names | usable assessment content | 🟠 | Code yes; demo data remains placeholder until content arrives |

### Minimum V1 backend scope (definition)
**In scope (staff API):** auth token plumbing + RBAC, staff/CO_ADMIN/SPECIALIST management, branches/knowledge sources, child registration for an **existing** parent + consents, cases + assignment gate, initial assessment, plans/goals/progress, PDF reports + progress transaction, appointments, homework, notifications (read only), OpenAPI + security tests.

**Independent of client decisions:** everything above except staff login (**P-32**) and real parent onboarding (**P-01/P-29**).

**Explicitly out:** parent authentication/onboarding, RECEPTION activation, automatic notifications, report hiding/replacement, child editing, case-status workflow, assessment editing, hard delete/anonymization, final assessment, session notes, frontend.

**Recommended split:** **V1-A (staff backend)** and **V1-B (parent backend)**. V1-A can reach release readiness without P-01/P-29; V1-B starts only after those decisions.

---

## 3. Gate D task breakdown (execution order)

**Rules for every task:** one scope · one commit · `php artisan test` + `./vendor/bin/pint` green · no domain schema changes (only **T-2.0** adds the framework table `personal_access_tokens`) · no unapproved business rules.

Legend: ✅ ready now · 🔶 ready with a documented temporary default · ⛔ blocked.

### Wave 0 — Foundation (all ✅; no client decision needed)

| ID | Roadmap | Task (≈15–30 min) | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D0.1** | T-2.0 | Install Sanctum + `personal_access_tokens` migration | Token issue/revoke works; protected route accepts token; only approved new table | Gate C |
| **D0.2** | T-2.4 | `PermissionResolver`: `role_permissions ∪ grants − denies`; SUPER_ADMIN = all | Unit: role only · grant · deny wins · SUPER_ADMIN · no-role denial | D0.1 |
| **D0.3** | T-2.5 | `Gate::before` SUPER_ADMIN bypass + policy base class | Non-admin denied; SUPER_ADMIN allowed; policy unit test | D0.2 |
| **D0.4** | T-2.2 | `GET /me` + `POST /logout` | Payload = role + effective permissions; logout revokes current token; 401 unauthenticated | D0.1, D0.2 |
| **D0.5** | T-2.12 | `AuditLogger` + redaction list | Append-only insert; redacts `password`, `password_confirmation`, `token`, `remember_token`, `child_name`, `parent_name`; no update/delete path | D0.1 |

**Wave gate:** Sanctum auth works in tests; resolver invariants proven; audit writes survive PostgreSQL trigger.

### Wave 1 — Staff management & anti-escalation (✅, D1.6 partial)

| ID | Roadmap | Task | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D1.1** | T-2.6a | SUPER_ADMIN creates CO_ADMIN | Non-SUPER_ADMIN → 403; audit row; validation for role/email/mobile | D0.4, D0.5 |
| **D1.2** | T-2.7 | CO_ADMIN creates/edits **SPECIALIST only** | Any other role → 403 even with `users.create`; audit | D1.1 |
| **D1.3** | T-2.8 | Deactivate/reactivate account + revoke tokens | Matrix **D-055**: SUPER_ADMIN any except self; CO_ADMIN SPECIALIST only via `specialists.manage`; self banned incl. SUPER_ADMIN; inactive token → 401; tokens revoked | D1.2 |
| **D1.4** | T-2.9 | Per-user permission overrides | Grant/deny respected; no overrides on SUPER_ADMIN; audit before/after | D0.3 |
| **D1.5** | T-2.10/2.11 | Escalation invariants I-1…I-11 + self-modification ban | 11+ tests: role level, grant-not-owned, self role/status/permissions, no SUPER_ADMIN override | All Wave 1 |
| **D1.6** | T-2.13 | Auth audit events | 🔶 logout + token events now; failed-login event after **P-32** | D0.5 |

**Wave gate:** A CO_ADMIN cannot create an admin, elevate themselves, or grant a permission they lack.

### Wave 2 — Lookups, children, cases (✅; P-13 resolved by D-054)

| ID | Roadmap | Task | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D2.1** | T-3.1 | Branches CRUD | Soft delete; partial unique allows name reuse only after delete | D0.3 |
| **D2.2** | T-3.2 | Knowledge sources CRUD | `is_active` only; no hard delete | D0.3 |
| **D2.3** | T-3.3 | `EnglishNameNormalizer` | `Muhammad→MU`, `Ali→AL`, `Sarah→SA`; strip non A–Z; no Arabic transliteration. **Edge cases P-03 excluded** | — |
| **D2.4** | T-3.5/3.9 | `ChildCodeGenerator` (sequence) + concurrency test | 50 concurrent/sequential calls → 50 unique codes; format `^[A-Z]{4}[0-9]+$`; no reuse after rollback/delete | D2.3 |
| **D2.5** | T-3.6a/3.7 | Register child for **existing parent** + 3 consents in one transaction; require center-approved `consent_version` + `consent_text_snapshot` | 10 §10.2 cases (adjusted to **D-050**: no digital accept); missing text/version → 422; any failure → no child row; `accepted_by = NULL`, `recorded_by = actor` | D2.4, D0.5 |
| **D2.6** | T-3.8 | “No child name anywhere” regression suite | DB columns · audit · logs · validation errors contain no name | D2.5 |
| **D2.7** | T-3.10 | Children list/show scopes | Parent sees own only (404 otherwise); admin sees all; specialist sees only assigned cases | D2.5 |
| **D2.8** | T-3.12 | Case create/update (admin) | Assignment triple stays consistent; case can be created unassigned; no status workflow | D2.5 |
| **D2.9** | T-3.14 | `requireAssigned()` gate | 5 tests: plan · goal · progress · report · homework reject when `cases.specialist_id` is null | D2.8 |
| **D2.10** | T-3.13 | `AssignSpecialistAction` + audit | ✅ **P-13 resolved (D-054)**. SUPER_ADMIN + CO_ADMIN authorized; target must be active SPECIALIST; grant `cases.assign_specialist` to CO_ADMIN defaults + update SeedersTest; audit | D0.3, D2.8 |
| **D2.11** | T-3.15 | Child/Case policies + IDOR | 404/403 matrix for parent/specialist/CO_ADMIN/SUPER_ADMIN | D2.7, D2.9 |

**Critical note:** P-13 is resolved (D-054), so **D2.10 is unblocked**. Case assignment still requires the approved `cases.assign_specialist` default for CO_ADMIN before the clinical flow can start.

### Wave 3 — Initial assessment (✅; question/scale **content** remains P-10/P-09)

| ID | Roadmap | Task | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D3.1** | T-4.1 | Questions CRUD + `is_active`/order | No hard delete; ordering respected; content entered by admin (P-10) | D0.3, D2.8 |
| **D3.2** | T-4.2 | Create assessment once per child | Second attempt → 409/422; assignment gate applies | D2.9 |
| **D3.3** | T-4.3/4.11 | Answers + snapshots | Editing a question never changes old answers; uniqueness `(assessment, question)` | D3.2 |
| **D3.4** | T-4.4/4.5 | Reinforcer catalog CRUD + child selection + snapshot | Only 4 categories; no duplicate selection | D0.3 |
| **D3.5** | T-4.6/4.7/4.8 | Scales CRUD + scores 0–10 + optional comment | 11/−1 rejected; comment nullable (D-045); old scale name preserved | D3.3 |
| **D3.6** | T-4.9/4.10 | Chart data + consents read-only | Ordered by `display_order`; consent list read-only, no edit/delete path | D3.3, D2.5 |

### Wave 4 — Treatment plans & goals (✅; T-5.4 deliberately no enforcement)

| ID | Roadmap | Task | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D4.1** | T-5.1/5.2 | Plan create/update + multiple specialists | `end_date` nullable + date CHECK; no duplicate pivot rows; plan requires assigned case | D2.9 |
| **D4.2** | T-5.3 | Goals CRUD | One responsible specialist per goal; **no** plan-specialist membership enforcement (D-040/T-5.4) | D4.1 |
| **D4.3** | T-5.5 | `RecordGoalProgressAction` (internal only) | One transaction: read current → insert history → update goal; no direct endpoint | D4.2, D0.5 |
| **D4.4** | T-5.6/5.7 | Cross-case rejection + history pagination | Foreign goal → 422 `GOAL_NOT_IN_CASE`; history ordered, immutable | D4.3 |
| **D4.5** | T-5.8/5.9 | Specialist policies + progress tests | Unassigned → 404; three updates keep correct previous/new chain | D4.4 |

### Wave 5 — Reports (PDF) & homework (✅ except T-6.7)

| ID | Roadmap | Task | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D5.1** | T-6.2/6.3 | PDF validation + private storage + checksum | MIME + `%PDF-` + size (config placeholder for **P-26**); random path, no child name; file not stored on failure | D0.1 |
| **D5.2** | T-6.1a/6.1b/6.4/6.5/6.9 | **Core transaction:** report + ≥1 goal + percentage per goal + history + goal update | Cross-case goal → 422; duplicate goal → 422; zero goals → 422; no partial report/file on failure; soft delete keeps history, hard delete blocked | D4.3, D5.1, D2.9 |
| **D5.3** | T-6.6 | Report list/show scopes | Admin all · specialist assigned only · parent own child visible only | D5.2 |
| **D5.4** | T-6.8 | Protected download | Policy-gated; hidden report → 404 for parent, still available to admin/specialist-as-approved; no public URL | D5.3 |
| **D5.5** | T-6.10/6.11 | Homework create/complete | Specialists create on assigned case; parent completes own child's homework | D2.9 |
| **D5.6** | T-6.12/6.13 | Comments append-only + IDOR suite | No edit/delete; cross-family access → 404; 12-case matrix | D5.5 |
| **—** | T-6.7 | Hide report | ⛔ **P-07** — do not implement | P-07 |

### Wave 6 — Appointments & notifications (✅/🔶)

| ID | Roadmap | Task | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D6.1** | T-7.2/7.3 | Appointment create/update | Child + branch + date; `specialist_id` optional; `status` untouched; no cancellation/reschedule | D2.9 |
| **D6.2** | T-7.1 | Assignment-before-confirm/complete guard | 🔶 Implement guard + unit tests now; expose confirm/complete only after the status literals are confirmed. No enums (D-053) | D6.1 + status literals |
| **D6.3** | T-7.5 | Notifications list + mark-read | Scoped to recipient only; no auto-creation (P-16) | D0.4 |
| **—** | T-7.4 | Auto notification on booking | ⛔ **P-16** | P-16 |

### Wave 7 — Hardening & delivery

| ID | Roadmap | Task | Acceptance / tests | Depends |
|---|---|---|---|---|
| **D7.1** | T-8.1/8.2a–c | Policy matrix + IDOR suites (children/cases, assessments/consents, reports/files/homework) | ≥20 access cases; expected 404 vs 403 | All feature waves |
| **D7.2** | T-8.3/8.4 | File access + rate limiting | Direct storage path fails; login/upload throttled | D5.4 |
| **D7.3** | T-8.5 | N+1 review | Query-count assertions on list endpoints | D5.3 |
| **D7.4** | T-8.6 | Complete OpenAPI | Every route documented; `/docs/api.json` valid; no auth leakage | All endpoints |
| **D7.5** | T-8.7/8.8 | README + PENDING audit | Setup/seed/test instructions; every open item documented or closed | All |
| **D7.6** | T-8.9 | Demo seeders | 2 branches, 3 specialists, 5 children, plans, reports — **no real data**, placeholders only. 🔶 parent names depend on P-04 | D7.5 |

---

## 4. Critical path to V1 backend delivery

```
P-32 (client) ──► T-2.1 login ──► D0.4 /me
                              └─► D1.1 → D1.2 → D1.5
D0.1 → D0.2 → D0.3 ───────────────┘

D1.x ──► D2.5 child registration ──► D2.8 case ──► [D2.10 assign] ──► D2.9 gate
                                                   │
                                                   ├─► D3.x assessment
                                                   ├─► D4.x plans/goals
                                                   └─► D5.2 reports + progress
                                                            │
                                                            └─► D5.3/D5.4 → D7.4 OpenAPI → V1-A
```

**Parallel tracks that do not wait for client decisions:**
- D0.x foundation, D1.x user management, D8/D2 lookups, D2.3/D2.4 name/code logic.
- D5.1 PDF validation/storage plumbing (only the size cap is a placeholder).
- D6.3 notifications list; D7.1 security test scaffolding.

**Hard dependencies on decisions:**
- **P-32** → production login (**T-2.1**). Nothing else blocks Wave 0.
- **P-13** is resolved by **D-054**; case assignment (**D2.10**) is unblocked subject to its CO_ADMIN default grant.
- **P-01/P-29** → V1-B parent app only.
- **P-07/P-16/P-02** → optional/deferred features, not V1-A.

**Milestones**
| Milestone | Content | Exit criterion |
|---|---|---|
| **M1 Foundation** | D0.1–D0.5 | Auth/RBAC/audit tests green; `/me` works |
| **M2 Staff admin** | D1.1–D1.5 | Anti-escalation suite green |
| **M3 Clinical core** | D2.1–D2.9, D3.x, D4.x | Child → case → assessment → plan → goal chain works in tests |
| **M4 Delivery core** | D5.1–D5.6, D6.1–D6.3 | Report transaction + homework + appointments green |
| **M5 Release** | D7.1–D7.5 | OpenAPI complete, IDOR suite green, docs updated |

**Rough effort:** ~45 implementable tasks × 20–30 focused minutes ≈ **18–23 engineering hours** (≈4–5 working days at 5 h/day), excluding client waiting time and review cycles.

---

## 5. Recommended first Gate D task

**D0.1 — T-2.0: Install Sanctum and the `personal_access_tokens` migration.**

Why first:
- Requires **no client decision**; it is the only Gate D task that adds a schema object, and it is explicitly approved by the roadmap (framework table only).
- Unblocks every authenticated endpoint and all RBAC work.
- Small, isolated, and verifiable in one commit.

Acceptance checklist:
1. Sanctum installed; `personal_access_tokens` migration runs on **both `basma_noor_test` and `basma_noor`** via `php artisan migrate` (no fresh/wipe).
2. A token can be issued, used on a protected test route, and revoked.
3. Unauthenticated access → 401 using the existing envelope.
4. `php artisan test` + `./vendor/bin/pint` green.
5. One commit: `Gate D: T-2.0 Sanctum personal access tokens`.

**P-13 is resolved by D-054.** D2.10 (case assignment) is unblocked and must add the CO_ADMIN default before the clinical flow can start.

---

**Stopping here.** No Controllers, APIs, or business logic were created. Awaiting approval to start Gate D with D0.1.
