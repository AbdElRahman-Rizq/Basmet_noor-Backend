# بصمة نور — خطة التنفيذ (Roadmap)

> **قاعدة العمل:** مهمة واحدة في كل مرة. لا نبدأ مهمة قبل أن تنجح اختبارات السابقة.
> كل مهمة 10–20 دقيقة، وتنتهي بـ commit منطقي.
>
> **حالة الرموز:** `[ ]` لم تبدأ · `[~]` قيد التنفيذ · `[x]` مكتملة · `[!]` محظورة (PENDING)

**آخر تحديث:** 2026-11-17 — ✅ **اكتمال Gate C**: اعتماد **D-050** (P-12) · **D-051** (P-20) · **D-052** (P-31) · **D-053** (P-15)، وتنفيذ 18 migration + seeders + models + factories + اختبارات §15.
**سابقًا:** D-046 (`age` فقط) · D-047 (درجات integer) · D-048/D-049 (P-21/P-22 خارج V1) · T-0.2 → T-0.10 (Laravel 13 · Pest · API Standards · Scramble · Makefile · Private Storage).

---

## بوابات الاعتماد (Gates)

| البوابة | الشرط | ما يُسمح بعده |
|---|---|---|
| **A — اعتماد الوثائق** | اعتماد العميل لـ `FINAL_REVIEW_SUMMARY.md` + `DATABASE_DESIGN.md` v4 | تجهيز البيئة (Gate B) ثم إنشاء Laravel بعد الاعتماد. |
| **B — تجهيز البيئة** | نجاح التحقق من Docker/PHP/PostgreSQL فقط | **لا Laravel ولا migrations.** إنشاء Laravel بعد Gate A. |
| **C — اعتماد الـ Schema** | نجاح اختبارات §15 + تأكيد أن لا migration يعتمد على PENDING | كتابة الـ migrations + Models + اختبارات §15. |
| **D — Backend** | نجاح Gate C | كود التطبيق (Actions/Controllers/Policies/endpoints) حسب Phase 2+. |

> **حالة الآن:** ✅ **Gate A مكتمل** · ✅ **Gate B مكتمل** · ✅ **Gate C مكتمل** (2026-11-17) · **Gate D: التالي — لم يبدأ**.
> **Gate C:** القرارات D-046 → D-053 موثّقة في `docs/DECISIONS.md`. البنود P-12/P-20/P-31/P-15 **مغلقة**.
> تبقّى معلّقًا في الوثائق: P-04 (Parent Name) · P-09 (Scale Names) · P-01/P-32/P-29 · P-16 · P-03 · P-07.
> هذه بنود **تطبيقية/تشغيلية** لا تغيّر Schema v4 ولا تمنع بدء Gate D في المسارات المستقلة.
>
> **قاعدة صارمة:** أي كود تطبيقي يبدأ بعد Gate C فقط، وتُعلَّم أي مهمة تعتمد على بند `PENDING` بـ `[!]` أو تُقسَّم.

---

## معيار حجم المهمة

- المهمة المثالية: **مخرج واحد + اختبار واحد + 10–20 دقيقة**.
- إن تجاوزت 20 دقيقة أو جمعت أكثر من طبقة (migration + model + endpoint + test) → تُقسَّم.
- التقسيمات الملزمة في هذا الإصدار:
  - T-1.23 → T-1.23a…T-1.23e (مجموعات Models).
  - T-3.6 → T-3.6a (طفل لحساب قائم) + T-3.6b (إنشاء/إعادة استخدام ولي أمر — محظور P-01/P-32/P-04 (Parent Name)/P-29).
  - T-6.1 → T-6.1a (تحقق/تخزين PDF) + T-6.1b (ربط الأهداف — ✅ D-052: transaction واحدة).
  - T-8.2 → اختبارات IDOR لكل module على حدة.

---

## Definition of Done (لكل مهمة)

1. الكود يتبع `docs/DECISIONS.md`.
2. `php artisan test` ينجح بالكامل (بدون skip غير مبرر).
3. `./vendor/bin/pint --test` نظيف.
4. اختبارات Feature تغطي business rule وليس HTTP 200 فقط.
5. تحديث `PROJECT_ROADMAP.md` + أي docs متأثرة.
6. Commit برسالة واضحة (`feat(children): ...` / `test(children): ...` / `docs: ...`).

---

## Phase 0 — البنية التحتية

> **Gate B = T-0.1 + T-0.1b فقط.** لا يُنشأ Laravel داخل Gate B.
> **T-0.2 → T-0.10 تتطلب اعتماد Gate A أولًا**، ثم إنشاء Laravel؛ ولا migrations قبل Gate C.

| ID | المهمة | معيار القبول |
|---|---|---|
| `[x]` T-0.1 | `compose.yaml` + `Dockerfile` (بلا Laravel) | `app` (php 8.4-cli + pdo_pgsql) + `db` (postgres:17-alpine) + volume + healthcheck + `.env`. |
| `[x]` T-0.1b | التحقق من البنية التحتية | PHP/Composer/pdo_pgsql + PostgreSQL healthy + PDO `SELECT 1` — **بلا جداول**. |
| `[x]` T-0.2 | إنشاء مشروع Laravel 13 داخل container — **بعد Gate A** | `/up` يرجّع 200. ✅ Laravel 13.42.0 |
| `[x]` T-0.3 | ضبط `.env` والتحقق من اتصال PostgreSQL عبر Laravel | `SELECT 1` ناجح — **بلا migrations**. ✅ 0 جداول |
| `[x]` T-0.4 | `routes/api.php` + health endpoint | JSON موحّد — **بلا Sanctum migrations**. ✅ `/api/v1/health` = 200 |
| `[x]` T-0.5 | Pest + قاعدة `basma_noor_test` | اختبار Unit ناجح — **بلا migrations**. ✅ Pest 4.7.8 · 13 اختبارًا |
| `[x]` T-0.6 | Response envelope + Exception Handler | 401/403/404/422/429 بصيغة موحّدة. ✅ `ApiResponse` + `ApiExceptionRenderer` |
| `[x]` T-0.7 | Scramble + `/docs/api` | OpenAPI بدون أخطاء. ✅ Scramble 0.13.53 · 20 اختبارًا · Redocly valid |
| `[x]` T-0.8 | Git init + `.gitignore` + أول commit | `vendor`/`.env` مستثناة. |
| `[x]` T-0.9 | سكربتات `make up/down/shell/composer/artisan/test/logs` | تعمل من داخل الـ container. ✅ `make fresh` محمي برسالة تمنع التنفيذ حتى Gate C |
| `[x]` T-0.10 | إعداد private disk للتقارير | ✅ `private_reports` → `storage/app/private/reports` · 6 اختبارات · بلا URL عام |

**Gate B:** نجاح T-0.1 + T-0.1b. **ما بعد Gate A:** T-0.2 → T-0.10 (Laravel ثم بقية الإعداد)، وبعدها Gate C.

---

## Phase 1 — قاعدة البيانات (Schema v4)

> **شرط مسبق:** اعتماد Gate A ثم إنشاء Laravel (T-0.2).
> **Gate C:** لا migrations قبل اعتماد v4. ترتيب الـ migrations ملزم حسب `DATABASE_DESIGN.md §11`.

| ID | المهمة | معيار القبول |
|---|---|---|
| `[x]` T-1.1 | `roles` (بها `level`) + `permissions` + `role_permissions` | `roles.level` CHECK 0..100. |
| `[x]` T-1.2 | `users` مع `role_id` NOT NULL + `user_permissions` | لا `user_roles`؛ unique على (user, permission). |
| `[x]` T-1.3 | `parents` + `specialists` | one-to-one مع users. |
| `[x]` T-1.4 | `branches` + `knowledge_sources` | partial unique على اسم الفرع. |
| `[x]` T-1.5 | `children` + `CREATE SEQUENCE child_code_sequence` | unique على code/sequence · **`age` + `age_recorded_at` فقط، لا `date_of_birth` (D-046)**. |
| `[x]` T-1.6 | `cases` (nullable specialist + `assigned_at`/`assigned_by` + CHECK) | لا حالة بدون بيانات إسناد متسقة. |
| `[x]` T-1.7 | `initial_assessments` + `assessment_questions` + `assessment_answers` (snapshots) | unique(child_id). |
| `[x]` T-1.8 | `functional_scales` + `functional_scale_scores` (snapshot + CHECK 0..10) | إدخال 11 يفشل · **integer فقط، لا decimal/float (D-047)**. |
| `[x]` T-1.9 | `reinforcer_catalog` + `assessment_reinforcers` | CHECK على 4 فئات. |
| `[x]` T-1.10 | `consent_types` + `child_consents` | unique(child, type) + `accepted_by`/`recorded_by` + **نسخة/نص NOT NULL (D-050)**. |
| `[x]` T-1.11 | `treatment_plans` + `plan_specialists` + `goals` | CHECK progress 0..100 + end_date. |
| `[x]` T-1.12 | `reports` (PDF metadata + CHECK mime/size) | لا `details` ولا `session_notes` (**D-049 خارج V1**). |
| `[x]` T-1.13 | `goal_progress_history` (previous/new + report_id RESTRICT + partial unique) + Trigger ثبات | بعد `reports`. |
| `[x]` T-1.14 | `homework` + `homework_comments` | append-only. |
| `[x]` T-1.15 | `appointments` (+ `specialist_id` nullable) | **D-053**: الإسناد اختياري عند الحجز وإلزامي قبل التأكيد/الإكمال تطبيقيًا. |
| `[x]` T-1.16 | `notifications` | index (recipient, is_read). |
| `[x]` T-1.17 | `audit_logs` + trigger منع UPDATE/DELETE | اختبار DB يرمي exception. |
| `[x]` T-1.18 | Seeder: roles + permissions + role_permissions | RECEPTION بلا صلاحيات. |
| `[x]` T-1.19 | Seeder: branches (2) + knowledge_sources + consent_types (3) | idempotent. |
| `[x]` T-1.20 | Seeder: functional_scales (10 placeholders) | موثقة كـ placeholders (P-09). |
| `[x]` T-1.21 | Seeder: REINFORCER catalog مبدئي | الفئات الأربع. |
| `[x]` T-1.22 | Seeder: SUPER_ADMIN (من env) | لا كلمة مرور في الكود. |
| `[x]` T-1.23a | Models: Identity (Role/Permission/User/Parent/Specialist) | علاقات + casts؛ لا `child_name`. |
| `[x]` T-1.23b | Models: Children/Cases | علاقات + scopes. |
| `[x]` T-1.23c | Models: Assessments/Consents | snapshots + casts. |
| `[x]` T-1.23d | Models: Treatment/Reports/Progress | علاقات + append-only guards. |
| `[x]` T-1.23e | Models: Homework/Appointments/Notifications/Audit | علاقات + casts. |
| `[x]` T-1.24 | Factories للاختبارات | طفل/حالة/تقييم/خطة جاهزة. |

**Gate C:** ✅ **منفذ** — 18 migration + 10 seeders + 28 model + 14 factory.
`basma_noor` و`basma_noor_test` يحملان نفس الـ schema (30 جدولًا + `migrations`).
89 اختبار Pest ناجح (483 assertion) · Pint ناجح على ملفات Gate C · `migrate:fresh --seed` ناجح على قاعدة الاختبار.
التفاصيل في `GATE_C_IMPLEMENTATION_REPORT.md`.

---

## Phase 2 — Authentication & RBAC

| ID | المهمة | معيار القبول | Blocked by |
|---|---|---|---|
| `[x]` T-2.0 | تثبيت Sanctum + migration `personal_access_tokens` | `personal_access_tokens` موجود ويُستخدم للتوكنات | Gate C |
| `[!]` T-2.1 | Staff login — **محظور** حتى تحديد identifier | Sanctum token + rate limit + `is_active` | **P-32** |
| `[x]` T-2.2 | Logout / me / permissions payload | `/me` يرجّع role + effective permissions | — |
| `[!]` T-2.3 | Parent login — **محظور** حتى P-01 | `[!]` | **P-01** |
| `[x]` T-2.4 | Permission resolver (role ∪ grants − denies) | Unit tests لكل حالة | — |
| `[x]` T-2.5 | Gate/Policy base + SUPER_ADMIN bypass | اختبار تجاوز | — |
| `[x]` T-2.6a | SUPER_ADMIN ينشئ CO_ADMIN | 403 لغيره + audit | — |
| `[!]` T-2.6b | SUPER_ADMIN ينشئ RECEPTION | لا يُنفّذ قبل تفعيل الدور | **P-02** |
| `[x]` T-2.7 | CO_ADMIN ينشئ/يعدّل SPECIALIST فقط | محاولة إنشاء أي دور آخر → 403 (حتى مع أي صلاحية إدارية) | — |
| `[x]` T-2.8 | تعطيل/تفعيل الحسابات + إبطال التوكنات | منع الدخول | — |
| `[x]` T-2.9 | تعديل صلاحيات CO_ADMIN فرديًا | endpoint + audit | — |
| `[x]` T-2.10 | اختبارات منع التصعيد (I-1…I-11) | 11 حالة على الأقل + `docs/SECURITY_INVARIANTS_TRACEABILITY.md` | — |
| `[x]` T-2.11 | منع تعديل المستخدم لنفسه (role/is_active/permissions) | 403 + اختبار | — |
| `[x]` T-2.12 | Audit logger + redaction list | password/token/child_name/parent_name لا تُسجَّل | — |
| `[ ]` T-2.13 | Audit login/logout/failed login | بلا أسرار | — |

---

## Phase 3 — الأطفال وأولياء الأمور والحالات

| ID | المهمة | معيار القبول | Blocked by |
|---|---|---|---|
| `[ ]` T-3.1 | Branches CRUD | soft delete + partial unique | — |
| `[ ]` T-3.2 | Knowledge sources CRUD | `is_active` فقط، لا حذف لمستخدم | — |
| `[ ]` T-3.3 | `EnglishNameNormalizer` (Uppercase + أول حرفين `A-Z`) | Unit: Muhammad→MU، Ali→AL، Sarah→SA | — |
| `[!]` T-3.4 | اختبارات الحالات الحدّية (§9.3) | اسم أقل من حرفين، رموز، fallback | **P-03** |
| `[ ]` T-3.5 | `ChildCodeGenerator` (SEQUENCE) للاختبارات المؤكدة | يعمل داخل transaction؛ لا fallback | — |
| `[ ]` T-3.6a | تسجيل طفل لحساب ولي أمر **قائم** + الإقرارات الثلاثة | أي إقرار ناقص → rollback كامل · ✅ **P-12 محسوم (D-050)** — التنفيذ في Gate D | — |
| `[!]` T-3.6b | إنشاء/إعادة استخدام ولي أمر جديد (Find-or-Create) | لا تكرار + 422 عند التعارض | **P-01/P-32/P-04 (Parent Name)/P-29** |
| `[ ]` T-3.7 | اختبارات الإقرارات (§10.2) | 10 حالات · ✅ **P-12 محسوم (D-050)** — الاختبارات التطبيقية في Gate D | — |
| `[ ]` T-3.8 | اختبار "لا أثر للاسم" | DB + audit + logs + validation errors | — |
| `[ ]` T-3.9 | اختبار تزامن 50 طلبًا | 50 كودًا مختلفًا | — |
| `[ ]` T-3.10 | عرض الأطفال (Admin vs Parent scope) | ولي أمر لا يرى غير أطفاله → 404 | — |
| `[!]` T-3.11 | تعديل بيانات الطفل (بدون الاسم) + audit | حقول محدودة | **P-19** |
| `[ ]` T-3.12 | إنشاء Case + تعديله إداريًا | لا يتطلب أخصائيًا | — |
| `[!]` T-3.13 | `AssignSpecialistAction` + audit | يتحقق من دور SPECIALIST ونشاطه | **P-13** (مَن يملك الإسناد) |
| `[ ]` T-3.14 | `requireAssigned()` + 5 اختبارات للبوابة | خطة/هدف/تقدم/تقرير/واجب | — |
| `[ ]` T-3.15 | سياسات Child/Case + IDOR | 404/403 متوقعة | — |

> **ملاحظة P-12:** ✅ **محسوم (D-050 — A1+B2+C0).** البنية جاهزة في Gate C:
> `consent_version` و`consent_text_snapshot` إلزاميان، والقبول ورقي يُسجّله موظف مخوّل.
> يبقى على Gate D تمرير النص المعتمد من المركز ورفض الطلب إن غاب؛ لا سحب/تصحيح في V1.

---

## Phase 4 — التقييم المبدئي

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-4.1 | إدارة أسئلة التقييم (CRUD + is_active) | لا حذف فعلي. |
| `[ ]` T-4.2 | إنشاء تقييم (مرة واحدة لكل طفل) | الثانية → 409/422. |
| `[ ]` T-4.3 | حفظ الإجابات + snapshots | تعديل السؤال لا يغيّر الإجابة القديمة. |
| `[ ]` T-4.4 | إدارة كتالوج المعززات | 4 فئات فقط. |
| `[ ]` T-4.5 | اختيار معززات الطفل + snapshot | لا تكرار. |
| `[ ]` T-4.6 | إدارة المقاييس (اسم/ترتيب/تفعيل) | الاسم القديم محفوظ في snapshot. |
| `[ ]` T-4.7 | إدخال درجات 0–10 | -1/11 مرفوضان. |
| `[ ]` T-4.8 | تعليق الأخصائي (اختياري) | null مسموح — مؤكد D-045، بلا validation إلزامي. |
| `[ ]` T-4.9 | Endpoint chart data | مرتب حسب display_order. |
| `[ ]` T-4.10 | عرض/تدقيق الإقرارات | للقراءة فقط، لا تعديل. |
| `[ ]` T-4.11 | اختبارات التقييم الكاملة | snapshots + 0..10 + one-per-child. |

---

## Phase 5 — الخطط والأهداف

| ID | المهمة | معيار القبول | Blocked by |
|---|---|---|---|
| `[ ]` T-5.1 | إنشاء خطة (بدون مدة ثابتة) | `end_date` nullable + CHECK | — |
| `[ ]` T-5.2 | إسناد أخصائيين متعددين للخطة | pivot + لا تكرار؛ الخطة قد يكون لها أكثر من مسؤول (D-040) | — |
| `[ ]` T-5.3 | CRUD أهداف | أخصائي واحد لكل هدف | — |
| `[ ]` T-5.4 | تحقق أخصائي الهدف ضمن أخصائيي الخطة | **لا Enforcement في v1** (D-040)؛ لا كود إضافي حتى قرار لاحق | — |
| `[ ]` T-5.5 | `RecordGoalProgressAction` **داخلي** (previous/new + history + current) | transaction واحدة؛ لا endpoint مباشر | — |
| `[ ]` T-5.6 | منع تحديث تقدم هدف خارج الـ Case/الخطة | 422 `GOAL_NOT_IN_CASE` | — |
| `[ ]` T-5.7 | عرض تاريخ التقدم (pagination) | لا تعديل على الصفوف | — |
| `[ ]` T-5.8 | Policies للأخصائي | 404 لغير المسند | — |
| `[ ]` T-5.9 | اختبارات: خطط متعددة + 3 تحديثات تقدم | previous/new صحيحة | — |

---

## Phase 6 — التقارير (PDF) والواجبات

| ID | المهمة | معيار القبول | Blocked by |
|---|---|---|---|
| `[ ]` T-6.1a | رفع/تخزين PDF + metadata + session_date | transaction واحدة؛ لا اسم في المسار | — |
| `[ ]` T-6.1b | ربط الأهداف + تسجيل التقدم داخل نفس الـ transaction | previous/new + history · ✅ **P-31 محسوم (D-052)** | — |
| `[ ]` T-6.2 | التحقق من PDF (mime + magic bytes + size) | ملف مزيف → 422، ولا يُحفظ | — |
| `[ ]` T-6.3 | تخزين private + اسم عشوائي + checksum | لا اسم طفل في المسار | — |
| `[ ]` T-6.4 | رفض هدف لا يتبع Case التقرير | 422 لكل حالة | — |
| `[ ]` T-6.5 | رفض تقرير بلا أهداف / بهدف مكرر | 422 · هدف واحد على الأقل + نسبة لكل هدف · ✅ **P-31 محسوم (D-052)** | — |
| `[ ]` T-6.6 | عرض التقارير (Admin vs Specialist scope) | 404 خارج النطاق | — |
| `[!]` T-6.7 | إخفاء تقرير عن ولي الأمر | التقرير المخفي يبقى مرئيًا للإدارة؛ الدور المالك غير محسوم + audit | **P-07** |
| `[ ]` T-6.8 | تنزيل PDF عبر Policy | تقرير مخفي → 404 لولي الأمر، ووصول للإدارة | — |
| `[ ]` T-6.9 | ربط التقدم بالتقرير + عدم فقدانه عند الحذف | soft delete فقط؛ `RESTRICT` | — |
| `[ ]` T-6.10 | إنشاء واجب بواسطة أخصائي | مربوط بالـ Case والبوابة | — |
| `[ ]` T-6.11 | إنجاز الواجب من ولي الأمر | `completed_at` + `completed_by` | — |
| `[ ]` T-6.12 | تعليقات الواجب (append-only) | ولي أمر لا يعلّق على واجب غيره | — |
| `[ ]` T-6.13 | اختبارات IDOR للتقرير/ملفه/الواجب | 12 حالة | — |

> **ملاحظة P-31:** ✅ **محسوم (D-052 — Option B).** رفع PDF + ربط هدف واحد على الأقل + نسبة تقدم
> لكل هدف + `goal_progress_history` + تحديث `goals.progress_percentage` في **transaction واحدة**.
> لا `report_goals` pivot ولا `reports.status`.

---

## Phase 7 — المواعيد والإشعارات

| ID | المهمة | معيار القبول | Blocked by |
|---|---|---|---|
| `[ ]` T-7.1 | موعد مرتبط بأخصائي | `specialist_id` nullable · الإسناد إلزامي قبل التأكيد/الإكمال · ✅ **P-15 محسوم (D-053)** | — |
| `[ ]` T-7.2 | إنشاء موعد (child + branch + date) | بدون حالات غير معتمدة | — |
| `[ ]` T-7.3 | عرض/تعديل الموعد | حسب P-15 فقط | — |
| `[!]` T-7.4 | إشعار in-app عند الحجز | المستقبلون/الحدث غير محسومين | **P-16** |
| `[ ]` T-7.5 | قائمة الإشعارات + تعليم مقروء | مقصورة على المستخدم | — |
| `[!]` T-7.6 | Audit للمواعيد (إلغاء/إعادة جدولة) | **خارج V1 (D-053)** — لا إلغاء/إعادة جدولة ولا حالات معتمدة | — |

> **ملاحظة P-15:** ✅ **محسوم (D-053 — Option B+).** `specialist_id` nullable، والإسناد اختياري
> عند الحجز وإلزامي قبل التأكيد/الإكمال **تطبيقيًا**. لا Enums ولا إلغاء/إعادة جدولة في V1.

---

## Phase 8 — التقوية والتسليم

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-8.1 | مراجعة Policies مقابل مصفوفة الصلاحيات | اختبار لكل خلية حساسة. |
| `[ ]` T-8.2a | IDOR: Children/Parents/Cases | 6+ حالات. |
| `[ ]` T-8.2b | IDOR: Assessments/Consents | 6+ حالات. |
| `[ ]` T-8.2c | IDOR: Reports/Files/Homework | 8+ حالات. |
| `[ ]` T-8.3 | اختبارات الوصول للملفات | رابط مباشر لا يعمل، والتنزيل محمي. |
| `[ ]` T-8.4 | Rate limiting + إعدادات أمنية | login محدود. |
| `[ ]` T-8.5 | مراجعة N+1 | لا استعلامات غير متوقعة. |
| `[ ]` T-8.6 | OpenAPI مكتمل | كل endpoint موثّق. |
| `[ ]` T-8.7 | README تشغيل/deployment | Docker + seed + test. |
| `[ ]` T-8.8 | مراجعة PENDING قبل التسليم | كل بند محسوم أو موثق. |
| `[ ]` T-8.9 | Demo seeders | 2 فرع، 3 أخصائيين، 5 أطفال، خطط، تقارير. |

---

## المهام المحظورة حاليًا

| المهمة | السبب |
|---|---|
| T-2.1 Staff login | **P-32** (email أم mobile) |
| T-2.3 Parent login | **P-01** |
| T-2.6b إنشاء RECEPTION | **P-02** |
| تفعيل أي صلاحية RECEPTION | **P-02** |
| T-3.4/T-3.6b حالات الأكواد وfallback | **P-03** + **P-01/P-32/P-04 (Parent Name)/P-29** |
| T-3.11 تعديل بيانات الطفل | **P-19** |
| T-3.13 AssignSpecialistAction/صلاحية الإسناد | **P-13** |
| T-6.7 مَن يخفي التقرير | **P-07** |
| تعديل التقييم بعد الحفظ | P-08 |
| Case status workflow | P-05 |
| استبدال/حذف ملف التقرير | **P-07** (P-28 مدمج) |
| Notification channels / إنشاء تلقائي | **P-16** |

> **مغلقة بقرارات Gate C:** P-12 (**D-050**) · P-20 (**D-051**) · P-31 (**D-052**) · P-15 (**D-053**).
> ما تبقّى منها هو **تنفيذ تطبيقي في Gate D** (نص الإقرار المعتمد، تحقق «≥ هدف»، إلزام الأخصائي
> قبل التأكيد)، وليس حظرًا على الـ Schema. لا سحب/تصحيح إقرار، ولا حذف/إمحاء، ولا إلغاء/إعادة
> جدولة مواعيد في V1.

---

## خارج نطاق V1 نهائيًا (🚫)

| البند | القرار | الأثر على المهام |
|---|---|---|
| **P-21 — «التقييم النهائي»** | **D-048** | لا Schema ولا Business Logic ولا Endpoint. لا تُضاف مهمة له في V1. |
| **P-22 — ملاحظات الجلسة الأولى / `session_notes` / `sessions`** | **D-049** | التقرير PDF هو سجل الجلسة الوحيد. لا تُضاف مهمة له في V1. |

> أي تنفيذ مستقبلي لأي منهما يتطلب ADR جديدًا + migration جديدة، ولا يُشتق ضمنيًا من `initial_assessments` أو `reports`.

---

## Future Improvements (لا تُنفَّذ الآن)

- Dashboard إحصائي للإدارة.
- إشعارات متعددة القنوات + Queue.
- 2FA لحسابات SUPER_ADMIN.
- بحث متقدم وفلاتر محفوظة.
- فحص فيروسات للملفات.
- معاينة PDF داخل المتصفح.
- تدويل رسائل API.
- Versioning لنصوص الإقرارات.
- نسخ احتياطي مجدول + monitoring.
