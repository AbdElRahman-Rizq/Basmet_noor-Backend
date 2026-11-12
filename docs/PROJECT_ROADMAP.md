# بصمة نور — خطة التنفيذ (Roadmap)

> **قاعدة العمل:** مهمة واحدة في كل مرة. لا نبدأ مهمة قبل أن تنجح اختبارات السابقة.
> كل مهمة 10–20 دقيقة، وتنتهي بـ commit منطقي.
>
> **حالة الرموز:** `[ ]` لم تبدأ · `[~]` قيد التنفيذ · `[x]` مكتملة · `[!]` محظورة (PENDING)

**آخر تحديث:** 2026-11-12 — متزامن مع Database Design **v3**. لا تنفيذ قبل اعتماد v3.

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

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-0.1 | تشغيل Docker Desktop + `compose.yaml` | `app` (php 8.4-cli + pdo_pgsql) + `db` (postgres:17-alpine) + volume. |
| `[ ]` T-0.2 | إنشاء مشروع Laravel 13 داخل container | `/up` يرجّع 200. |
| `[ ]` T-0.3 | ضبط `.env` واتصال PostgreSQL | `migrate` على `basma_noor` ناجح. |
| `[ ]` T-0.4 | Sanctum + `routes/api.php` + health endpoint | JSON موحّد. |
| `[ ]` T-0.5 | Pest + قاعدة `basma_noor_test` | أول اختبار ناجح. |
| `[ ]` T-0.6 | Response envelope + Exception Handler | 401/403/404/422/429 بصيغة موحّدة. |
| `[ ]` T-0.7 | Scramble + `/docs/api` | OpenAPI بدون أخطاء. |
| `[ ]` T-0.8 | Git init + `.gitignore` + أول commit | `vendor`/`.env` مستثناة. |
| `[ ]` T-0.9 | سكربتات `make up/test/fresh` | تعمل من داخل الـ container. |
| `[ ]` T-0.10 | إعداد private disk للتقارير | `storage/app/private/reports` + اختبار كتابة/قراءة. |

**Gate:** نجاح T-0.1 → T-0.10.

---

## Phase 1 — قاعدة البيانات (Schema v3)

> ترتيب الـ migrations ملزم حسب `DATABASE_DESIGN.md §11`.

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-1.1 | `roles` (بها `level`) + `permissions` + `role_permissions` | `roles.level` CHECK 0..100. |
| `[ ]` T-1.2 | `users` مع `role_id` NOT NULL + `user_permissions` | لا `user_roles`؛ unique على (user, permission). |
| `[ ]` T-1.3 | `parents` + `specialists` | one-to-one مع users. |
| `[ ]` T-1.4 | `branches` + `knowledge_sources` | partial unique على اسم الفرع. |
| `[ ]` T-1.5 | `children` + `CREATE SEQUENCE child_code_sequence` | unique على code/sequence. |
| `[ ]` T-1.6 | `cases` (nullable specialist + `assigned_at`/`assigned_by` + CHECK) | لا حالة بدون بيانات إسناد متسقة. |
| `[ ]` T-1.7 | `initial_assessments` + `assessment_questions` + `assessment_answers` (snapshots) | unique(child_id). |
| `[ ]` T-1.8 | `functional_scales` + `functional_scale_scores` (snapshot + CHECK 0..10) | إدخال 11 يفشل. |
| `[ ]` T-1.9 | `reinforcer_catalog` + `assessment_reinforcers` | CHECK على 4 فئات. |
| `[ ]` T-1.10 | `consent_types` + `child_consents` | unique(child, type). |
| `[ ]` T-1.11 | `treatment_plans` + `plan_specialists` + `goals` | CHECK progress 0..100 + end_date. |
| `[ ]` T-1.12 | `reports` (PDF metadata + CHECK mime/size) | لا `details`/`session_notes`. |
| `[ ]` T-1.13 | `goal_progress_history` (previous/new + report_id SET NULL + partial unique) | بعد `reports`. |
| `[ ]` T-1.14 | `homework` + `homework_comments` | append-only. |
| `[ ]` T-1.15 | `appointments` (+ `specialist_id` nullable مؤقت) | موثق كـ P-15. |
| `[ ]` T-1.16 | `notifications` | index (recipient, is_read). |
| `[ ]` T-1.17 | `audit_logs` + trigger منع UPDATE/DELETE | اختبار DB يرمي exception. |
| `[ ]` T-1.18 | Seeder: roles + permissions + role_permissions | RECEPTION بلا صلاحيات. |
| `[ ]` T-1.19 | Seeder: branches (2) + knowledge_sources + consent_types (3) | idempotent. |
| `[ ]` T-1.20 | Seeder: functional_scales (10 placeholders) | موثقة كـ placeholders (P-09). |
| `[ ]` T-1.21 | Seeder: REINFORCER catalog مبدئي | الفئات الأربع. |
| `[ ]` T-1.22 | Seeder: SUPER_ADMIN (من env) | لا كلمة مرور في الكود. |
| `[ ]` T-1.23 | Models + علاقات + `$fillable`/`$casts` لكل الجداول | لا `child_name` في أي `$fillable`. |
| `[ ]` T-1.24 | Factories للاختبارات | طفل/حالة/تقييم/خطة جاهزة. |

**Gate:** `migrate:fresh --seed` من الصفر + كل اختبارات الـ schema (§15 في التصميم).

---

## Phase 2 — Authentication & RBAC

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-2.1 | Staff login (email+password) | Sanctum token + rate limit + `is_active`. |
| `[ ]` T-2.2 | Logout / me / permissions payload | `/me` يرجّع role + effective permissions. |
| `[ ]` T-2.3 | Parent login — **محظور** حتى P-01 | `[!]` |
| `[ ]` T-2.4 | Permission resolver (role ∪ grants − denies) | Unit tests لكل حالة. |
| `[ ]` T-2.5 | Gate/Policy base + SUPER_ADMIN bypass | اختبار تجاوز. |
| `[ ]` T-2.6 | SUPER_ADMIN ينشئ CO_ADMIN / RECEPTION | 403 لغيره. |
| `[ ]` T-2.7 | CO_ADMIN ينشئ SPECIALIST فقط | محاولة CO_ADMIN/RECEPTION → 403 (حتى مع `users.manage`). |
| `[ ]` T-2.8 | تعطيل/تفعيل الحسابات + إبطال التوكنات | منع الدخول. |
| `[ ]` T-2.9 | تعديل صلاحيات CO_ADMIN فرديًا | endpoint + audit. |
| `[ ]` T-2.10 | اختبارات منع التصعيد (I-1…I-7) | 7 حالات على الأقل. |
| `[ ]` T-2.11 | منع تعديل المستخدم لنفسه (role/is_active/permissions) | 403 + اختبار. |
| `[ ]` T-2.12 | Audit logger + redaction list | password/token/child_name لا تُسجَّل. |
| `[ ]` T-2.13 | Audit login/logout/failed login | بلا أسرار. |

---

## Phase 3 — الأطفال وأولياء الأمور والحالات

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-3.1 | Branches CRUD | soft delete + partial unique. |
| `[ ]` T-3.2 | Knowledge sources CRUD | لا حذف لمستخدم. |
| `[ ]` T-3.3 | `ArabicNameNormalizer` + `Transliterator` | Unit: أحمد→AH، شادي→SH، خالد→KH. |
| `[ ]` T-3.4 | اختبارات الحالات الخاصة (§9.4) | الاسم المركب، "ال"، الأجنبي، حرف واحد، فشل التحويل. |
| `[ ]` T-3.5 | `ChildCodeGenerator` (SEQUENCE) | يعمل داخل transaction. |
| `[ ]` T-3.6 | تسجيل طفل + ولي أمر + الإقرارات الثلاثة في transaction | أي إقرار ناقص → rollback كامل. |
| `[ ]` T-3.7 | اختبارات الإقرارات (§10.2) | 8 حالات. |
| `[ ]` T-3.8 | اختبار "لا أثر للاسم" | DB + audit + logs. |
| `[ ]` T-3.9 | اختبار تزامن 50 طلبًا | 50 كودًا مختلفًا. |
| `[ ]` T-3.10 | عرض الأطفال (Admin vs Parent scope) | ولي أمر لا يرى غير أطفاله → 404. |
| `[ ]` T-3.11 | تعديل بيانات الطفل (بدون الاسم) + audit | حقول محدودة. |
| `[ ]` T-3.12 | إنشاء Case + تعديله إداريًا | لا يتطلب أخصائيًا. |
| `[ ]` T-3.13 | `AssignSpecialistAction` + audit | يتحقق من دور SPECIALIST ونشاطه. |
| `[ ]` T-3.14 | `requireAssigned()` + 5 اختبارات للبوابة | خطة/هدف/تقدم/تقرير/واجب. |
| `[ ]` T-3.15 | سياسات Child/Case + IDOR | 404/403 متوقعة. |

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
| `[ ]` T-4.8 | تعليق الأخصائي (اختياري) | null مسموح. |
| `[ ]` T-4.9 | Endpoint chart data | مرتب حسب display_order. |
| `[ ]` T-4.10 | عرض/تدقيق الإقرارات | للقراءة فقط، لا تعديل. |
| `[ ]` T-4.11 | اختبارات التقييم الكاملة | snapshots + 0..10 + one-per-child. |

---

## Phase 5 — الخطط والأهداف

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-5.1 | إنشاء خطة (بدون مدة ثابتة) | `end_date` nullable + CHECK. |
| `[ ]` T-5.2 | إسناد أخصائيين متعددين | pivot + لا تكرار. |
| `[ ]` T-5.3 | CRUD أهداف | أخصائي واحد لكل هدف. |
| `[ ]` T-5.4 | تحقق أخصائي الهدف ضمن أخصائيي الخطة (P-14) | حسب القرار. |
| `[ ]` T-5.5 | `RecordGoalProgressAction` (previous/new + history + تحديث current) | transaction واحدة. |
| `[ ]` T-5.6 | منع تحديث تقدم هدف خارج الـ Case/الخطة | 422 `GOAL_NOT_IN_CASE`. |
| `[ ]` T-5.7 | عرض تاريخ التقدم (pagination) | لا تعديل على الصفوف. |
| `[ ]` T-5.8 | Policies للأخصائي | 404 لغير المسند. |
| `[ ]` T-5.9 | اختبارات: خطط متعددة + 3 تحديثات تقدم | previous/new صحيحة. |

---

## Phase 6 — التقارير (PDF) والواجبات

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-6.1 | `UploadReportAction` (ملف + session_date + goals + progress) | transaction واحدة. |
| `[ ]` T-6.2 | التحقق من PDF (mime + magic bytes + size) | ملف مزيف → 422، ولا يُحفظ. |
| `[ ]` T-6.3 | تخزين private + اسم عشوائي + checksum | لا اسم طفل في المسار. |
| `[ ]` T-6.4 | رفض هدف لا يتبع Case التقرير | 422 لكل حالة. |
| `[ ]` T-6.5 | رفض تقرير بلا أهداف / بهدف مكرر | 422. |
| `[ ]` T-6.6 | عرض التقارير (Admin vs Specialist scope) | 404 خارج النطاق. |
| `[ ]` T-6.7 | إخفاء تقرير عن ولي الأمر | Admin فقط + audit. |
| `[ ]` T-6.8 | تنزيل PDF عبر Policy | تقرير مخفي → 404 لولي الأمر. |
| `[ ]` T-6.9 | ربط التقدم بالتقرير + عدم فقدانه عند حذف التقرير | SET NULL. |
| `[ ]` T-6.10 | إنشاء واجب بواسطة أخصائي | مربوط بالـ Case والبوابة. |
| `[ ]` T-6.11 | إنجاز الواجب من ولي الأمر | `completed_at` + `completed_by`. |
| `[ ]` T-6.12 | تعليقات الواجب (append-only) | ولي أمر لا يعلّق على واجب غيره. |
| `[ ]` T-6.13 | اختبارات IDOR للتقرير/ملفه/الواجب | 12 حالة. |

---

## Phase 7 — المواعيد والإشعارات

| ID | المهمة | معيار القبول |
|---|---|---|
| `[!]` T-7.1 | موعد مرتبط بأخصائي | **محظور — P-15**. |
| `[ ]` T-7.2 | إنشاء موعد (child + branch + date) | بدون حالات غير معتمدة. |
| `[ ]` T-7.3 | عرض/تعديل الموعد | حسب P-15 فقط. |
| `[ ]` T-7.4 | إشعار in-app عند الحجز | بدون قنوات خارجية. |
| `[ ]` T-7.5 | قائمة الإشعارات + تعليم مقروء | مقصورة على المستخدم. |
| `[ ]` T-7.6 | Audit للمواعيد | إنشاء/تعديل/إلغاء. |

---

## Phase 8 — التقوية والتسليم

| ID | المهمة | معيار القبول |
|---|---|---|
| `[ ]` T-8.1 | مراجعة Policies مقابل مصفوفة الصلاحيات | اختبار لكل خلية حساسة. |
| `[ ]` T-8.2 | اختبارات IDOR شاملة | 20+ حالة. |
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
| T-2.3 Parent login | P-01 |
| تفعيل صلاحيات RECEPTION | P-02 |
| اعتماد الحالات الخاصة للأكواد | P-03 |
| تعديل التقييم بعد الحفظ | P-08 |
| Case status workflow | P-05 |
| استبدال/حذف ملف التقرير | P-07 / P-28 |
| المواعيد المرتبطة بأخصائي | P-15 |
| Notification channels | P-16 |
| Final assessment | P-21 |
| حق المحو النهائي | P-20 |

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
