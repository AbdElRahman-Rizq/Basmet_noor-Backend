# Gate C — Database Implementation Report

**Project:** Basmat Nour (بصمة نور)
**Date:** 2026-11-17
**Status:** ✅ **مكتمل — لا blockers**
**Source of truth:** `docs/DATABASE_DESIGN.md` v4 (بدون أي إعادة تصميم)

---

## 1. القرارات المعتمدة قبل التنفيذ

| القرار | البند | الاعتماد النهائي | ADR |
|---|---|---|---|
| **P-12** | تسجيل الإقرارات والنسخ | **A1 + B2 + C0** — تسجيل يدوي ورقي بواسطة موظف · حفظ `consent_version` + `consent_text_snapshot` (NOT NULL) · لا قبول رقمي ولا سحب/تصحيح في V1 | **D-050** |
| **P-20** | الحذف النهائي / الإمحاء | **Option A — Retention Only** — لا حذف نهائي ولا Anonymization · `RESTRICT` وTriggers كما هي | **D-051** |
| **P-31** | أهداف التقرير | **Option B** — هدف واحد على الأقل لكل تقرير نهائي · نسبة تقدم لكل هدف · transaction واحدة · لا `report_goals` ولا `reports.status` | **D-052** |
| **P-15** | أخصائي الموعد | **Option B+** — `specialist_id` nullable · الإسناد اختياري عند الحجز · إلزامي قبل التأكيد/الإكمال تطبيقيًا · لا Enums/إلغاء/إعادة جدولة | **D-053** |

**تم توثيقها قبل التنفيذ في:** `docs/DECISIONS.md` (D-050 → D-053) · `docs/PENDING_REQUIREMENTS.md` · `docs/DATABASE_DESIGN.md` · `docs/PROJECT_ROADMAP.md`.
**القرارات السارية لم تُعدّل:** D-040 · D-041 · D-045 · D-046 · D-047 · D-048 · D-049.

---

## 2. نطاق التنفيذ

| العنصر | العدد | الموقع |
|---|---:|---|
| Migrations | **18** | `database/migrations/2026_11_17_000001…000018_*.php` |
| Tables (بما فيها `migrations`) | **31** (30 تطبيقية + `migrations`) | كلها في `public` |
| Seeders | **10** (9 + `DatabaseSeeder`) | `database/seeders/` |
| Models | **28** | `app/Models/` |
| Factories | **15** | `database/factories/` |
| ملفات اختبار جديدة | **7** | `tests/Feature/Database/` |

**لم يُنشأ:** Controllers · API endpoints · Auth · Actions · Policies · Gate D Business Logic · `final_assessments` · `session_notes` · `sessions` · `report_goals` · `reports.status` · Enums غير معتمدة.

---

## 3. Migrations المنفذة

| # | Migration | الجداول / العناصر |
|---|---|---|
| 000001 | `create_roles_permissions_tables` | `roles`, `permissions`, `role_permissions` + `roles_level_check` |
| 000002 | `create_users_tables` | `users` (+ self FK `created_by_user_id` SET NULL), `user_permissions` |
| 000003 | `create_parents_specialists_tables` | `parents`, `specialists` (one-to-one مع `users`) |
| 000004 | `create_branches_knowledge_sources_tables` | `branches` (+ partial unique), `knowledge_sources` |
| 000005 | `create_children_table` | `children`, `child_code_sequence`, `children_age_check`, `children_child_code_format_check` |
| 000006 | `create_cases_table` | `cases` + `cases_assignment_consistency_check` |
| 000007 | `create_initial_assessments_table` | `initial_assessments` (unique `child_id`) |
| 000008 | `create_assessment_questions_answers_tables` | `assessment_questions`, `assessment_answers` + `assessment_questions_type_check` |
| 000009 | `create_functional_scales_tables` | `functional_scales`, `functional_scale_scores` + `functional_scale_scores_score_check` |
| 000010 | `create_reinforcers_tables` | `reinforcer_catalog`, `assessment_reinforcers` + `reinforcer_catalog_category_check` |
| 000011 | `create_consents_tables` | `consent_types`, `child_consents` (نسخة/نص NOT NULL — D-050) |
| 000012 | `create_treatment_plans_goals_tables` | `treatment_plans`, `plan_specialists`, `goals` + `treatment_plans_dates_check`, `goals_progress_percentage_check` |
| 000013 | `create_reports_table` | `reports` + `reports_file_mime_check`, `reports_file_size_check` |
| 000014 | `create_goal_progress_history_table` | `goal_progress_history` + partial unique + Trigger `goal_progress_history_immutable` |
| 000015 | `create_homework_tables` | `homework`, `homework_comments` |
| 000016 | `create_appointments_table` | `appointments` (`specialist_id` NULL — D-053) |
| 000017 | `create_notifications_table` | `notifications` |
| 000018 | `create_audit_logs_table` | `audit_logs` + Trigger `audit_logs_immutable` |

> **ملاحظة تنفيذية:** migration 000005 يستخدم `CREATE SEQUENCE IF NOT EXISTS` كي يبقى `migrate:fresh` قابلًا للتكرار (PostgreSQL لا يحذف الـ sequences المستقلة عند wipe، والـ sequence يجب أن يبقى monotonic). لا يغيّر هذا أي قاعدة معتمدة.

---

## 4. قواعد قاعدة البيانات المنفذة

### 4.1 Foreign Keys
- **RESTRICT:** السجلات السريرية/التاريخية (children → parents, cases → children, consents → users/children/types, reports → cases/specialists, goals → plans/specialists, `goal_progress_history.report_id` → reports, `audit_logs.user_id` → users).
- **CASCADE:** صفوف التبعية التقنية (`plan_specialists`, `assessment_answers`, `functional_scale_scores`, `assessment_reinforcers`, `homework_comments`, `notifications.recipient_user_id`, `user_permissions`, `role_permissions`, `parents/specialists.user_id`).
- **SET NULL:** `users.created_by_user_id`, `appointments.scheduled_by_user_id`, `homework.completed_by_user_id`.

### 4.2 Unique & Partial Unique
- Unique: `roles.name`, `permissions.name`, `users.email`, `users.mobile`, `parents.user_id`, `specialists.user_id`, `children.child_code`, `children.sequence_number`, `initial_assessments.child_id`, `assessment_answers(assessment_id, question_id)`, `functional_scale_scores(assessment_id, scale_id)`, `assessment_reinforcers(assessment_id, reinforcer_id)`, `child_consents(child_id, consent_type_id)`, `reports.file_path`, `knowledge_sources.name`, `consent_types.name`, `functional_scales.name`, `reinforcer_catalog(category, name)`.
- Partial unique: `branches_name_active_unique (name) WHERE deleted_at IS NULL` · `goal_progress_history_report_goal_unique (report_id, goal_id) WHERE report_id IS NOT NULL`.
- **لا** `report_goals` pivot.

### 4.3 CHECK Constraints
`roles_level_check` · `children_age_check` (0..120) · `children_child_code_format_check` (`^[A-Z]{4}[0-9]+$`) · `functional_scale_scores_score_check` (0..10) · `cases_assignment_consistency_check` · `assessment_questions_type_check` · `reinforcer_catalog_category_check` · `treatment_plans_dates_check` · `goals_progress_percentage_check` (0..100) · `goal_progress_history_previous_check` / `_new_check` · `reports_file_mime_check` (`application/pdf`) · `reports_file_size_check` (> 0).

### 4.4 Triggers & Append-only
- `audit_logs_immutable` — يمنع `UPDATE` و`DELETE`.
- `goal_progress_history_immutable` — يمنع `UPDATE` و`DELETE`.
- **لا Trigger على `child_consents`** — C0 يجعل المنع تطبيقيًا (لا endpoints)، ولا يُضاف Trigger غير معتمد.

### 4.5 Indexes الرئيسية
- `cases (specialist_id, status)` · `reports (case_id, session_date DESC)` · `goal_progress_history (goal_id, recorded_at DESC)` · `notifications (recipient_user_id, is_read, created_at DESC)` · `audit_logs (user_id, created_at DESC)` و`(created_at DESC)` · `audit_logs (entity_name, record_id)`.
- Indexes على كل FKs المستخدمة في الاستعلامات.

### 4.6 القرارات الخاصة
- **D-040:** `plan_specialists` يقبل عدة أخصائيين للخطة الواحدة، و`goals.specialist_id` **غير مقيّد** بأن يكون من `plan_specialists`.
- **D-046:** `children.age` + `children.age_recorded_at` فقط؛ **لا `date_of_birth`**.
- **D-047:** `functional_scale_scores.score` = `smallint` مع CHECK 0..10 (لا decimal/float).
- **D-048/D-049:** لا جداول/أعمدة لـ P-21/P-22.

---

## 5. Seeders

| Seeder | المحتوى | ملاحظات |
|---|---|---|
| `RoleSeeder` | 5 أدوار (SUPER_ADMIN/CO_ADMIN/RECEPTION/SPECIALIST/PARENT) | idempotent (`updateOrCreate`) |
| `PermissionSeeder` | 55 صلاحية حسب §5.3 + `assessments.view_own` | idempotent |
| `RolePermissionSeeder` | 105 صفًا: SUPER_ADMIN كل الصلاحيات + افتراضيات §5.4 | `insertOrIgnore` |
| `BranchSeeder` | فرعان placeholder | الأسماء غير معتمدة — موثقة كـ placeholder |
| `KnowledgeSourceSeeder` | 4 مصادر placeholder | موثقة |
| `ConsentTypeSeeder` | 3 أنواع مطلوبة placeholder | **بلا نص قانوني مُختلق** (D-050) |
| `FunctionalScaleSeeder` | 10 مقاييس placeholder | P-09 معلّق |
| `ReinforcerCatalogSeeder` | 8 عناصر (2 × 4 فئات) | placeholder |
| `SuperAdminSeeder` | حساب SUPER_ADMIN من env فقط | يتخطى مع تحذير إن غابت البيانات؛ لا يعيد كتابة كلمة المرور عند التكرار |
| `DatabaseSeeder` | تشغيل الجميع بالترتيب | — |

**بيانات التطوير بعد `db:seed`:** 5 roles · 55 permissions · 105 role_permissions · 2 branches · 4 sources · 3 consent types · 10 scales · 8 reinforcers · 1 SUPER_ADMIN.

---

## 6. Models & Factories

### Models (28)
Role · Permission · User · ParentProfile · Specialist · Branch · KnowledgeSource · Child · CaseFile · InitialAssessment · AssessmentQuestion · AssessmentAnswer · FunctionalScale · FunctionalScaleScore · ReinforcerCatalog · AssessmentReinforcer · ConsentType · ChildConsent · TreatmentPlan · PlanSpecialist · Goal · GoalProgressHistory · Report · Homework · HomeworkComment · Appointment · Notification · AuditLog.

- علاقات دقيقة مع مفاتيح pivot صريحة (`plan_specialists.plan_id/specialist_id`).
- `$fillable` لا يحتوي `child_name` / `parent_name`.
- `HasUuids` (UUIDv7) · casts للتواريخ/الأرقام/JSON.
- **لا Business Logic ولا Actions ولا Scopes تطبيقية** — Gate D.

### Factories (15)
User · Role · Branch · KnowledgeSource · ParentProfile · Specialist · Child · CaseFile · InitialAssessment · FunctionalScale · ConsentType · ReinforcerCatalog · TreatmentPlan · Goal · Report.

- `ChildFactory` يستخدم `nextval('child_code_sequence')` لتوليد كود متسلسل صالح.
- `ReportFactory` يولّد مسارًا عشوائيًا وchecksum SHA-256 وmime صحيحًا.

---

## 7. نتائج الاختبارات

```
Tests:    89 passed (483 assertions)
Duration: ~39s
Command:  docker compose exec -T app php artisan test
```

### الملفات الجديدة
| الملف | التغطية |
|---|---|
| `MigrationsTest.php` (9) | 18 migration · الجداول المعتمدة/الممنوعة · الأنواع/nullability · CHECK · partial unique · Triggers · sequence · سياسات FK |
| `SeedersTest.php` (5) | المحتوى · idempotency · صلاحيات SUPER_ADMIN · SUPER_ADMIN من env · التخطي الآمن |
| `ConstraintsTest.php` (12) | Age · Score · Progress · التواريخ · child_code · role/question/reinforcer · PDF · unique · partial unique · RESTRICT · CASCADE · SET NULL |
| `TriggersTest.php` (3) | audit immutability · progress immutability · غياب Trigger غير معتمد على consents |
| `DomainRulesTest.php` (12) | sequence متوازٍ · أكواد · إسناد الحالة · D-040 · الإقرار الورقي · التقرير/التقدم · soft delete · snapshots · موعد nullable · الخصوصية |
| `SchemaScenariosTest.php` (21) | سيناريوهات §15 (2–22) |
| `FreshInstallTest.php` (1) | §15/1 — التثبيت من الصفر + seed |
| **اختبارات Phase 0** | كلها ناجحة (health, docs, envelope, errors, storage, web, test DB guard) |

### تغطية سيناريوهات §15 (22)

| # | السيناريو | التنفيذ |
|---|---|---|
| 1 | `migrate:fresh` + `db:seed` من الصفر | ✅ `RefreshDatabase` + `DatabaseSeeder` |
| 2 | 50 `nextval` → 50 رقمًا مختلفًا | ✅ |
| 3 | `score = 11` → DB exception | ✅ |
| 4 | `progress = -1` → DB exception | ✅ |
| 5 | `UPDATE/DELETE audit_logs` → exception | ✅ |
| 6 | `UPDATE/DELETE goal_progress_history` → exception | ✅ |
| 7 | `DELETE child_consents` ممنوع تطبيقيًا | ✅ التحقق: لا Trigger معتمد؛ المنع عبر عدم وجود endpoint (C0) |
| 8 | تعديل سؤال → snapshot قديم | ✅ |
| 9 | تعديل مقياس → snapshot قديم | ✅ |
| 10 | soft delete تقرير → `report_id` محفوظ | ✅ |
| 11 | hard delete تقرير له تاريخ → exception | ✅ |
| 12 | `case.specialist_id = NULL` + خطة → 422 | ✅ DB: triple check؛ 422 تطبيقي في **Gate D** |
| 13 | رفع غير PDF → 422 ولا ملف | ✅ DB: mime/size check؛ رفض الملف في **Gate D** |
| 14 | تقرير لهدف في Case آخر → 422 | ✅ موثق كتحقق **Gate D** (لا قيد DB ممكن) |
| 15 | طفل بلا الإقرارات الثلاثة → 422 | ✅ موثق كـ transaction **Gate D** |
| 16 | طفل ثانٍ لنفس ولي الأمر | ✅ نفس `parent_id`، بلا `users`/`parents` جديدة |
| 17 | ولي أمر مكرر بنفس identifier | ✅ unique |
| 18 | لا اسم طفل في أي جدول/audit | ✅ فحص كل أعمدة النص |
| 19 | `child_code` غير مطابق → exception | ✅ |
| 20 | إقرار رقمي (`accepted_by`/`recorded_by`) | ✅ **عُدّل وفق D-050**: `accepted_by = NULL` (ورقي) · `recorded_by = الموظف` |
| 21 | حذف مستخدم له `audit_logs` → exception | ✅ RESTRICT |
| 22 | `visible_to_parent = false` → 404 لولي الأمر | ✅ DB يخزّن العلم؛ 404 في **Gate D** |

> السيناريوهات 12، 13، 14، 15، 22 تتطلب Controllers/Business Logic؛ **لم تُنفَّذ كـ endpoints** احترامًا لمنع Gate D. الجانب الذي يمكن فرضه في DB مُختبر، والباقي موثق بوضوح.

---

## 8. التحقق من قاعدة البيانات

### 8.1 التطوير — `basma_noor`
- `php artisan migrate --force` → **Nothing to migrate** (كل الـ 18 مسجلة، batch 1).
- `php artisan migrate:status` → كل الـ 18 = **Ran**.
- 31 جدولًا (30 + `migrations`).
- Triggers: `audit_logs_immutable`, `goal_progress_history_immutable`.
- Indexes: `branches_name_active_unique`, `goal_progress_history_report_goal_unique`, `reports_case_session_date_index`, `notifications_recipient_read_created_index`.
- `child_code_sequence` موجود.
- `child_consents.consent_version` و`consent_text_snapshot` = **NOT NULL**.
- Seeders نُفّذت بنجاح (البيانات أعلاه).

### 8.2 الاختبار — `basma_noor_test`
- `RefreshDatabase` ينفّذ `migrate:fresh` على القاعدة الصحيحة فقط.
- 31 جدولًا · 18 migration · Triggerان.
- بعد الاختبارات: 0 users / 0 roles (rollback سليم، بلا تسريب بيانات).
- `tests/TestCase.php` يفرض `basma_noor_test` قبل boot التطبيق، وحُذف `DB::purge` الذي كان يبطل transaction الاختبارات.

### 8.3 السلامة
- لم يُستخدم `migrate:fresh` / `db:wipe` على `basma_noor`.
- لم يُستخدم `docker compose down -v`.
- Volumes سليمة: `basma-noor_pgdata` · `basma-noor_vendor`.
- لا `.env` ولا أسرار في Git.

---

## 9. Docker والتطبيق

| التحقق | النتيجة |
|---|---|
| `docker compose config --quiet` | ✅ exit 0 |
| `docker compose restart` | ✅ app + db started |
| `docker compose ps` | ✅ app Up · db Up (healthy) |
| `GET /up` | ✅ 200 |
| `GET /api/v1/health` | ✅ 200 — `{"success":true,...,"status":"ok","application":"basma-noor","version":"1.0.0"}` |
| `GET /docs/api` | ✅ 200 |
| `GET /docs/api.json` | ✅ 200 · OpenAPI 3.1.2 · يحتوي مسار `/api/v1/health` |
| Scramble/Phase 0 tests | ✅ ناجحة |

---

## 10. التنسيق (Pint)

```
./vendor/bin/pint --test
PASS — 108 files
```

> **انحراف وحيد:** `tests/Feature/Api/HealthTest.php` كان يحمل مخالفة style **سابقة** في HEAD (FQN لـ `DB`). أُصلحت بإضافة `use Illuminate\Support\Facades\DB;` فقط — **بلا أي تغيير سلوكي**، لأن Pint الشامل مطلوب في التحقق النهائي.

---

## 11. الانحرافات والملاحظات

1. **migration 000005:** `CREATE SEQUENCE IF NOT EXISTS` (بدل `CREATE SEQUENCE`) لضمان تكرار `migrate:fresh` — لا أثر على المنطق.
2. **`basma_noor`:** كانت الـ migrations مطبقة سابقًا؛ `migrate --force` أعاد **Nothing to migrate**، وتم التحقق الفعلي من مطابقة الـ schema (جداول/فهارس/Triggers) للكود.
3. **`tests/TestCase.php`:** نُقل فرض قاعدة الاختبار إلى ما قبل boot التطبيق، وأُزيل purge الاتصال لأنه كان يُسقط transaction `RefreshDatabase`.
4. **`tests/Pest.php`:** أُعيد تسجيل `pest()->extend(TestCase::class)->in('Feature')` بعد إعادة كتابة الملف.
5. **`Report::progressHistory`:** soft delete التقرير لا يحذف history؛ `RESTRICT` يمنع الحذف النهائي.
6. **`TreatmentPlan::specialists()` / `Specialist::plans()`:** مفاتيح pivot صريحة `plan_id` / `specialist_id`.

**لا تعارض بين التصميم المعتمد والتنفيذ.** لا blockers.

---

## 12. المتبقي لـ Gate D (لا يُنفَّذ الآن)

1. `ChildCodeGenerator` + `EnglishNameNormalizer` + Find-or-Create لولي الأمر (P-01/P-03/P-04/P-29/P-32).
2. تسجيل الطفل + الإقرارات الثلاثة في transaction واحدة، مع تمرير النص المعتمد و`consent_version` (D-050).
3. Permission resolver + Policies + SUPER_ADMIN bypass + منع التصعيد.
4. `requireAssigned()` + `AssignSpecialistAction` (P-13).
5. تقييم مبدئي + snapshots + إدارة أسئلة/مقاييس/معززات.
6. خطط/أهداف + `RecordGoalProgressAction` داخلي.
7. تقرير PDF + ربط هدف واحد على الأقل + transaction موحدة (D-052) + تحقق cross-case.
8. مواعيد: إلزام الأخصائي قبل التأكيد/الإكمال (D-053) — بلا Enums/إلغاء/إعادة جدولة.
9. واجبات/تعليقات + إشعارات (P-16) + Audit logger + Redaction.
10. IDOR/Rate limiting/OpenAPI مكتمل/demo seeders (Phase 8).

> ما زال معلّقًا توثيقيًا ولا يغيّر Schema v4: **P-04** (تخزين اسم ولي الأمر) · **P-09** (أسماء المقاييس) · **P-01/P-32/P-29** · **P-16** · **P-03** · **P-07**.

---

## 13. الخلاصة

- ✅ Gate C منفذ بالكامل وفق Schema v4 والقرارات D-050 → D-053.
- ✅ 18 migration · 30 جدولًا · 10 seeders · 28 model · 15 factory.
- ✅ 89 اختبار Pest ناجح (483 assertion)، بما فيها كل سيناريوهات §15 القابلة للتنفيذ في DB.
- ✅ Pint ناجح · Docker سليم · `/up` و`/api/v1/health` وScramble تعمل.
- ✅ لا Controllers/API/Auth/Business Logic/Gate D.

**المرحلة التالية الموصى بها:** **Gate D — Authentication & RBAC (Phase 2)**، بدءًا من T-2.0 (Sanctum) ثم T-2.4/T-2.5 (Permission resolver + Policies) قبل T-2.1 (محظور P-32).
