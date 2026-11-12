# بصمة نور — خطة التنفيذ (Roadmap)

> **قاعدة العمل:** مهمة واحدة في كل مرة. لا نبدأ مهمة قبل أن تنجح اختبارات السابقة.
> كل مهمة 10–20 دقيقة، وتنتهي بـ commit منطقي.
>
> **حالة الرموز:** `[ ]` لم تبدأ · `[~]` قيد التنفيذ · `[x]` مكتملة · `[!]` محظورة (PENDING)

**آخر تحديث:** 2026-11-12 — انتهت مرحلة التحليل والتوثيق، بانتظار الموافقة على تصميم قاعدة البيانات.

---

## Definition of Done (لكل مهمة)

1. الكود يتبع conventions الموجودة في `docs/DECISIONS.md`.
2. `php artisan test` ينجح بالكامل (بدون skip غير مبرر).
3. `./vendor/bin/pint --test` نظيف.
4. اختبارات Feature تغطي business rule وليس HTTP 200 فقط.
5. تحديث `PROJECT_ROADMAP.md` + أي docs متأثرة.
6. Commit برسالة واضحة (`feat(children): ...` / `test(children): ...` / `docs: ...`).

---

## Phase 0 — البنية التحتية (قبل أي كود أعمال)

| ID | المهمة | المخرجات ومعيار القبول |
|---|---|---|
| `[ ]` T-0.1 | تشغيل Docker Desktop + إنشاء `compose.yaml` | services: `app` (php 8.4-cli + pdo_pgsql), `db` (postgres:17-alpine), volume للـ DB. `docker compose up -d` ينجح. |
| `[ ]` T-0.2 | إنشاء مشروع Laravel 13 عبر container | `composer create-project laravel/laravel .` داخل `app`، وفتح `http://localhost:8000/up` يرجّع 200. |
| `[ ]` T-0.3 | ضبط `.env` واتصال PostgreSQL | `DB_CONNECTION=pgsql`, `APP_TIMEZONE=UTC`, `php artisan migrate` ينجح على قاعدة `basma_noor`. |
| `[ ]` T-0.4 | تثبيت Sanctum + إعداد `routes/api.php` | `install:api`، وأول endpoint `GET /api/v1/health` يرجّع JSON موحّد. |
| `[ ]` T-0.5 | تثبيت Pest + إعداد DB اختبار | `basma_noor_test`، وأول اختبار health ينجح. |
| `[ ]` T-0.6 | هيكل الاستجابة الموحّد + Exception Handler | `{ success, message, data, errors }`، و422/401/403/404/429 بصيغة موحّدة. |
| `[ ]` T-0.7 | تثبيت Scramble + `/docs/api` | OpenAPI يعرض endpoint health بدون أخطاء. |
| `[ ]` T-0.8 | Git init + `.gitignore` + أول commit | المستودع نظيف، `.env` و`vendor` مستثناة. |
| `[ ]` T-0.9 | Makefile / سكربتات مساعدة | `make up`, `make test`, `make fresh` تعمل من داخل الـ container. |

**Gate:** لا نبدأ Phase 1 قبل نجاح T-0.1 → T-0.9.

---

## Phase 1 — قاعدة البيانات والـ Lookups

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-1.1 | Migration: users, roles, permissions, pivots, user_permissions | `migrate:fresh` ناجح + اختبار أن `user_permissions` تتفوق على role. |
| `[ ]` T-1.2 | Migration: parents, specialists + علاقات users | one-to-one مع قيد unique. |
| `[ ]` T-1.3 | Migration: branches, knowledge_sources | partial unique على اسم الفرع. |
| `[ ]` T-1.4 | Migration: children + `CREATE SEQUENCE child_code_sequence` | `nextval` يعمل، وunique على code/sequence. |
| `[ ]` T-1.5 | Migration: cases | FKs + indexes + soft delete. |
| `[ ]` T-1.6 | Migration: initial_assessments, assessment_questions, assessment_answers (snapshots) | unique(child_id) + snapshot columns. |
| `[ ]` T-1.7 | Migration: functional_scales, functional_scale_scores + CHECK 0..10 | إدخال 11 يفشل على مستوى DB. |
| `[ ]` T-1.8 | Migration: reinforcer_catalog, assessment_reinforcers | CHECK على الفئات الأربع. |
| `[ ]` T-1.9 | Migration: consent_types, child_consents | unique(child_id, consent_type_id). |
| `[ ]` T-1.10 | Migration: treatment_plans, plan_specialists, goals, goal_progress_history | CHECK 0..100 + index على التاريخ. |
| `[ ]` T-1.11 | Migration: reports | indexes + `details jsonb` nullable. |
| `[ ]` T-1.12 | Migration: homework, homework_comments | FK cascade + index. |
| `[ ]` T-1.13 | Migration: appointments | indexes على child/date. |
| `[ ]` T-1.14 | Migration: notifications | index (recipient, is_read). |
| `[ ]` T-1.15 | Migration: audit_logs + Trigger منع UPDATE/DELETE | اختبار DB: التعديل يرمي exception. |
| `[ ]` T-1.16 | Seeders: roles + permissions + ربط role_permissions | SUPER_ADMIN يملك كل الصلاحيات. |
| `[ ]` T-1.17 | Seeders: branches (2)، knowledge_sources، consent_types (3) | idempotent (`updateOrCreate`). |
| `[ ]` T-1.18 | Seeders: functional_scales (10 placeholders) | واضحة كـ placeholders حسب P-09. |
| `[ ]` T-1.19 | Seeders: SUPER_ADMIN أول حساب (env-based password) | كلمة المرور لا تُكتب في الكود. |
| `[ ]` T-1.20 | Models + العلاقات لكل الجداول | اختبار علاقات أساسي + `$fillable`/`$casts` مضبوطة. |
| `[ ]` T-1.21 | Factories للاختبارات | يمكن إنشاء طفل/حالة/تقييم بسهولة. |

**Gate:** تصميم DB مُعتمد + كل migrations تعمل + seeders تعمل.

---

## Phase 2 — Authentication & RBAC

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-2.1 | Staff login (`POST /auth/login`) بـ email+password | Sanctum token + rate limit + `is_active` check. |
| `[ ]` T-2.2 | Logout / me / token refresh policy | إبطال التوكن الحالي، `/me` يرجّع role+permissions. |
| `[ ]` T-2.3 | Parent login — **محظور** حتى P-01 | `[!]` |
| `[ ]` T-2.4 | Permission resolution service (`user_permissions` > role) | Unit test لكل حالة (grant/deny/لاغي). |
| `[ ]` T-2.5 | Gate/Policy base + `Gate::before` لـ SUPER_ADMIN | اختبار أن SUPER_ADMIN يمر وCO_ADMIN لا يمر بدون صلاحية. |
| `[ ]` T-2.6 | Admin management: SUPER_ADMIN ينشئ CO_ADMIN | 403 لمن ليس SUPER_ADMIN. |
| `[ ]` T-2.7 | CO_ADMIN ينشئ SPECIALIST فقط | محاولة إنشاء CO_ADMIN/RECEPTION → 403. |
| `[ ]` T-2.8 | تعطيل/تفعيل الحسابات | منع دخول المعطّل + إبطال توكناته. |
| `[ ]` T-2.9 | ضبط صلاحيات CO_ADMIN فرديًا | Endpoint + audit log لكل تغيير. |
| `[ ]` T-2.10 | Audit logger service + redaction | Unit test: password/token/child_name لا تُسجَّل. |
| `[ ]` T-2.11 | تسجيل عمليات الدخول/الخروج/الفشل في audit | لا تُسجَّل كلمات المرور. |

---

## Phase 3 — الأطفال وأولياء الأمور والحالات

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-3.1 | Branches CRUD (SUPER_ADMIN/مصرّح) | soft delete + partial unique. |
| `[ ]` T-3.2 | Knowledge sources CRUD + is_active | لا حذف لو مستخدم. |
| `[ ]` T-3.3 | `ArabicNameTransliterator` + `ChildCodeInitials` | Unit tests: أحمد→AH، محمد→MH، عبد الرحمن حسب P-03. |
| `[ ]` T-3.4 | `ChildCodeGenerator` (SEQUENCE) | يرجّع code + sequence داخل transaction. |
| `[ ]` T-3.5 | تسجيل طفل + ولي أمر في transaction واحد | لا يُحفظ الاسم؛ اختبار "لا أثر للاسم في DB/audit". |
| `[ ]` T-3.6 | اختبار تزامن توليد الأكواد | 50 طلب متوازٍ → 50 كودًا مختلفًا بدون تكرار. |
| `[ ]` T-3.7 | تعديل بيانات الطفل (بدون الاسم) + audit | الحقول المسموحة فقط. |
| `[ ]` T-3.8 | عرض الأطفال (Admin vs Parent scope) | Parent لا يرى غير أطفاله (404). |
| `[ ]` T-3.9 | ولي أمر بعدة أطفال | اختبار: 3 أطفال لنفس `parent_id`. |
| `[ ]` T-3.10 | إنشاء Case + إسناد فرع/أخصائي | 403 لأخصائي يحاول إنشاء case لغيره. |
| `[ ]` T-3.11 | قائمة Cases للأخصائي (scope) | لا يظهر أي case غير مسند. |
| `[ ]` T-3.12 | Policies: Child, Case + اختبارات IDOR | كل حالة 404/403 متوقعة. |

---

## Phase 4 — التقييم المبدئي

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-4.1 | إدارة أسئلة التقييم (Admin CRUD + is_active) | لا حذف فعلي؛ الأسئلة غير النشطة لا تظهر في النموذج. |
| `[ ]` T-4.2 | إنشاء التقييم (مرة واحدة لكل طفل) | المحاولة الثانية → 409/422. |
| `[ ]` T-4.3 | حفظ الإجابات مع snapshots | تعديل نص السؤال لا يغيّر الإجابة القديمة (اختبار). |
| `[ ]` T-4.4 | إدارة كتالوج المعززات (Admin) | الفئات الأربع فقط. |
| `[ ]` T-4.5 | اختيار معززات الطفل مع snapshot | اختيار مكرر مرفوض. |
| `[ ]` T-4.6 | إدارة المقاييس الوظيفية (أسماء + ترتيب + is_active) | تعديل الاسم لا يغيّر snapshot القديم. |
| `[ ]` T-4.7 | إدخال درجات 0–10 | -1 و11 مرفوضان (validation + DB check). |
| `[ ]` T-4.8 | تعليق الأخصائي على الدرجة (اختياري) | يمكن تركه null. |
| `[ ]` T-4.9 | Endpoint بيانات الرسم البياني | يرجّع `{scale, score}` مرتبًا للـ frontend. |
| `[ ]` T-4.10 | الإقرارات الثلاثة (required) | لا يكتمل التسجيل بدون الثلاثة + `accepted_at`. |
| `[ ]` T-4.11 | منع سحب/تعديل الإقرار | لا endpoint، ولا update على الجدول. |
| `[ ]` T-4.12 | اختبارات Assessment كاملة | One-per-child, snapshots, 0–10, consents. |

---

## Phase 5 — الخطط والأهداف والتقدم

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-5.1 | إنشاء خطة (بدون مدة ثابتة) | `end_date` nullable. |
| `[ ]` T-5.2 | إسناد أخصائيين متعددين للخطة | pivot + منع التكرار. |
| `[ ]` T-5.3 | CRUD أهداف الخطة | هدف له أخصائي واحد. |
| `[ ]` T-5.4 | التحقق أن أخصائي الهدف ضمن أخصائيي الخطة (P-14) | حسب قرار العميل. |
| `[ ]` T-5.5 | تحديث التقدم + إدخال history في transaction | كل تحديث يضيف صفًا ولا يحذف القديم. |
| `[ ]` T-5.6 | عرض تاريخ التقدم مرتبًا | Pagination + لا تعديل على الصفوف القديمة. |
| `[ ]` T-5.7 | Policies للأخصائي على الخطة/الهدف | أخصائي غير مسند → 404. |
| `[ ]` T-5.8 | اختبارات متعددة الخطط + تاريخ التقدم | خطة/حالة، أهداف متعددة، 3 تحديثات تقدم. |

---

## Phase 6 — التقارير والواجبات

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-6.1 | توثيق حقول التقرير المؤكدة/المؤقتة | `docs/REPORTS_SCHEMA.md` (حتى لو الحقول الأساسية فقط). |
| `[ ]` T-6.2 | إنشاء تقرير بواسطة أخصائي الـ Case | 403 لغيره. |
| `[ ]` T-6.3 | إخفاء تقرير عن ولي الأمر | Admin فقط + audit log. |
| `[ ]` T-6.4 | عرض التقارير للولي (المرئية فقط) | تقرير مخفي → 404 حتى مع معرفة الـ id. |
| `[ ]` T-6.5 | إنشاء واجب بواسطة أخصائي | مربوط بالـ Case. |
| `[ ]` T-6.6 | ولي الأمر يعلّم "تم الإنجاز" | `completed_at` + `completed_by_user_id`. |
| `[ ]` T-6.7 | تعليقات الواجب (append-only) | ولي أمر لا يعلّق على واجب طفل آخر. |
| `[ ]` T-6.8 | اختبارات الواجبات والتقارير | 6 سيناريوهات IDOR + visibility. |

---

## Phase 7 — المواعيد والإشعارات

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-7.1 | إنشاء موعد تقييم (بدون توسيع الحقول) | child + branch + date. |
| `[ ]` T-7.2 | عرض/تعديل/إلغاء الموعد | الحالات حسب P-15 فقط. |
| `[ ]` T-7.3 | توليد إشعار in-app عند حجز موعد | بدون قنوات خارجية. |
| `[ ]` T-7.4 | قائمة الإشعارات + تعليم مقروء | مقصورة على المستخدم الحالي. |
| `[ ]` T-7.5 | Audit للعمليات الحساسة | من أنشأ/عدّل/ألغى. |

---

## Phase 8 — التقوية والتسليم

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-8.1 | مراجعة كل الـ Policies مقابل مصفوفة الصلاحيات | اختبار لكل خلية حساسة. |
| `[ ]` T-8.2 | اختبارات IDOR شاملة | 20+ حالة. |
| `[ ]` T-8.3 | Rate limiting + إعدادات أمنية | login/parent-login محدودان. |
| `[ ]` T-8.4 | مراجعة N+1 عبر `DB::listen` في الاختبارات | لا استعلامات غير متوقعة. |
| `[ ]` T-8.5 | OpenAPI مكتمل + أمثلة | كل endpoint موثّق. |
| `[ ]` T-8.6 | README تشغيل/deployment | خطوات Docker + seed + test. |
| `[ ]` T-8.7 | مراجعة `PENDING_REQUIREMENTS` قبل التسليم | كل بند إما مُنفَّذ أو موثّق كخارج النطاق. |
| `[ ]` T-8.8 | Demo seeders لبيانات واقعية | 2 فرع، 3 أخصائيين، 5 أطفال، تقييمات، خطط. |

---

## المهام المحظورة حاليًا

| المهمة | السبب |
|---|---|
| Parent login (T-2.3) | P-01 |
| Reception role activation | P-02 |
| Child code transliteration النهائي | P-03 |
| Case status workflow | P-05 |
| Report fields النهائية | P-07 |
| Final assessment | P-21 |
| Notification channels | P-16 |
| Appointment statuses | P-15 |

---

## Future Improvements (لا تُنفَّذ الآن)

- Dashboard إحصائي للإدارة (قسم SRS 6 فارغ).
- تصدير PDF للتقارير.
- 2FA لحسابات SUPER_ADMIN.
- إشعارات متعددة القنوات + Queue.
- بحث متقدم وفلاتر محفوظة.
- نسخ احتياطي مجدول + monitoring.
- تدويل رسائل API.
- Versioning لنصوص الإقرارات.
