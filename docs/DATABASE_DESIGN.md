# بصمة نور — تصميم قاعدة البيانات (Proposed v2)

> **الحالة:** مسودة بانتظار موافقة العميل. لا تُنفَّذ أي migration قبل الموافقة.
> **الأساس:** `ERD.txt` المعتمد + متطلبات §4 + قرارات العميل المؤكدة.
> **قاعدة البيانات:** PostgreSQL 17، جميع المفاتيح `uuid` (UUIDv7 عبر `HasUuids`).

---

## 1. مراجعة الـ ERD — الملاحظات والتصحيحات

### 1.1 ملاحظات حرجة (تخالف متطلبًا صريحًا)

| # | الملاحظة | المتطلب المخالف | التصحيح المقترح |
|---|---|---|---|
| C-01 | `assessment_answers` يحتوي `question_id` فقط بدون snapshot لنص السؤال أو القسم. | §4-E "Historical answers must not change when an administrator edits a question later." | إضافة `question_text_snapshot`, `section_name_snapshot`, `question_type_snapshot` إلى `assessment_answers`، تُملأ لحظة الحفظ. |
| C-02 | `functional_scale_scores` بدون snapshot لاسم المقياس. | §4-F "Historical scale names and results must remain interpretable after scale names change." | إضافة `scale_name_snapshot` (و`display_order` اختياريًا). |
| C-03 | لا يوجد جدول صلاحيات على مستوى المستخدم؛ `role_permissions` فقط. | §4-A "CO_ADMIN permissions are individually configurable" و"Do not assume all CO_ADMIN accounts have identical permissions." | إضافة `user_permissions` (`user_id`, `permission_id`, `is_granted`) كـ override شخصي فوق صلاحيات الدور. |
| C-04 | لا يوجد أي جدول للمعززات (Reinforcers). | §4-E "Reinforcers include material, social, activity-based, and food categories." | إضافة `reinforcer_catalog` + `assessment_reinforcers` مع snapshot للاسم والفئة. |
| C-05 | `homework.parent_comment` عمود واحد يُستبدل في كل تعديل، ولا يوجد `completed_by_user_id`. | §4-K "Parents can add comments" + "preserve completion information and timestamps." | استبداله بجدول `homework_comments` (append-only)، وإضافة `completed_by_user_id`. |
| C-06 | `goals` لا يحتوي نسبة تقدم حالية؛ فقط `goal_progress_history`. | §4-I "Every Goal has a progress percentage." | إضافة `goals.progress_percentage` تُحدَّث داخل نفس transaction مع إدخال صف في الـ history (history = source of truth). |
| C-07 | `initial_problem` موجودة في `cases` وليست في `children`. | §4-C "Each child record must contain … Initial problem description." | نقلها إلى `children.initial_problem` (بيانات استقبال). لا نكرّرها في `cases`. |

### 1.2 ملاحظات تصميمية (أثر وظيفي/أمني)

| # | الملاحظة | الأثر | التصحيح المقترح |
|---|---|---|---|
| D-01 | لا يوجد `name` لأي مستخدم إداري (SUPER_ADMIN/CO_ADMIN/RECEPTION)؛ الاسم محفوظ فقط في `parents`/`specialists`. | لا يمكن عرض اسم المدير أو موظف الاستقبال. | إضافة `users.name`، وإزالة `parents.full_name` و`specialists.full_name` (مصدر واحد للاسم). |
| D-02 | `users.password_hash` | لا يخالف Laravel لكنه يخالف conventions. | استخدام `password` (hashed cast). |
| D-03 | `audit_logs.user_id` إلزامي. | عمليات النظام/المستخدم المحذوف/فشل الدخول لا يمكن تسجيلها. | `user_id` nullable + `actor_role` snapshot + `ip_address` + `user_agent`. |
| D-04 | لا يوجد ما يمنع تعديل/حذف `audit_logs` على مستوى قاعدة البيانات. | المتطلب: "must not be editable or deletable". | Trigger في PostgreSQL يرفض `UPDATE`/`DELETE` + صلاحيات DB محدودة لتطبيق التشغيل. |
| D-05 | `initial_assessments` عليه `unique(child_id)` + `deleted_at`. | لو حُذف soft delete، يستحيل إنشاء تقييم بديل. | منع الحذف نهائيًا (لا soft delete على هذا الجدول) — التقييم سجل تاريخي. |
| D-06 | `cases.specialist_id` إلزامي في الـ ERD، لكن الاستقبال قد ينشئ الحالة قبل الإسناد. | فشل إنشاء الحالة بدون أخصائي. | يبقى **NOT NULL** حسب §4-D (كل Case له أخصائي واحد)، ويتم الإسناد لحظة الإنشاء. **يحتاج تأكيد العميل** (P-13). |
| D-07 | `appointments` بدون أي ربط بأخصائي. | لا يمكن معرفة مَن سيجري التقييم ولا إشعاره. | **لا نضيف حقلًا** — §4-L يمنع توسيع المواعيد بدون تأكيد. مسجّل في P-15. |
| D-08 | `notifications.sent_at` + `related_entity` نصّي. | لا تكامل مرجعي، ومفهوم القناة غير محسوم. | إبقاء الجدول كـ in-app فقط الآن، وتوثيق أن `sent_at` للقنوات المستقبلية. لا نضيف قنوات. |
| D-09 | `age` كـ integer فقط. | العمر يصبح غير دقيق مع الوقت. | إبقاء `age` (متطلب صريح) + `age_recorded_at date` لتوضيح مرجعية الرقم. مسجّل في P-04. |
| D-10 | `consent_types` بدون `is_required`. | لا طريقة لضمان أن الثلاثة إقرارات فقط هي الإلزامية. | إضافة `is_required` + `display_order`. |
| D-11 | `reports` لا تحدد الحقول النهائية. | قالب التقرير غير مُسلَّم من العميل. | أعمدة أساسية مؤكدة + `details jsonb` مؤقتًا، مع `docs/REPORTS_SCHEMA.md` عند وصول القالب. |
| D-12 | لا توجد indexes صريحة للـ FKs والاستعلامات المتكررة. | أداء ضعيف عند النمو + N+1 غير محمي. | إضافة indexes (مذكورة لكل جدول أدناه). |
| D-13 | `branches.name` unique مع soft delete. | لا يمكن إعادة استخدام الاسم بعد الحذف. | Partial unique index `WHERE deleted_at IS NULL`. |
| D-14 | `user_roles` many-to-many. | قد يمنح المستخدم دورين متعارضين (مثل PARENT + SUPER_ADMIN). | يبقى many-to-many للبنية، مع قيد تطبيقي: دور واحد "أساسي" من عائلة الأدوار، وفحص عند الإسناد. |
| D-15 | `parents`/`specialists` بلا `is_active`. | التعطيل موجود في `users` فقط. | الاعتماد على `users.is_active` (مصدر واحد) — لا تكرار. |
| D-16 | `specialists` بلا تخصص/رقم موظف. | غير مطلوب. | لا نضيف (P-05 في المستقبل). |
| D-17 | `knowledge_sources` بلا `updated_at`. | اتساق. | إضافة timestamps. |
| D-18 | `users.email` و`mobile` nullable + unique. | صفوف متعددة بـ NULL مقبولة في PG (سليم)، لكن يلزم قيد "طريقة دخول واحدة على الأقل". | تحقق تطبيقي: مستخدم Staff لابد له من email، وParent لابد له من mobile (أو ما يحدده P-01). |
| D-19 | لا يوجد جدول ربط بين `cases` و`initial_assessments`. | صحيح وظيفيًا (§4-E: تقييم واحد لكل طفل). | لا تغيير. |
| D-20 | `plan_specialists` بدون timestamps. | لا حاجة. | لا تغيير. |

### 1.3 ما هو صحيح في الـ ERD ونُبقيه

- `initial_assessments.child_id` **unique** → تقييم واحد لكل طفل. ✅
- `child_consents` unique على (`child_id`, `consent_type_id`) وبدون أي أعمدة سحب. ✅
- `children.sequence_number` و`child_code` unique. ✅
- `reports.visible_to_parent` → إخفاء تقرير فردي. ✅
- `treatment_plans.end_date` nullable → لا مدة ثابتة. ✅
- فصل `goal_progress_history` كسجل append-only. ✅
- `cases.branch_id` مستقل عن الطفل → Cases مختلفة في فروع مختلفة. ✅
- `users.created_by_user_id` self-FK لتتبع مَن أنشأ الحساب. ✅

---

## 2. تصميم كود الطفل (Concurrency-Safe)

### 2.1 الشكل

```
[2 أحرف من اسم الطفل][2 أحرف من اسم ولي الأمر][رقم تسلسلي global]
مثال: AMRA1, MOHA2, AMRA3
```

- الحروف **لاتينية كبيرة** (A–Z) — مشتقة من الاسم العربي عبر transliteration.
- الرقم التسلسلي **global** ولا علاقة له بالحروف؛ لذا تكرار البادئة لا يسبب تعارضًا.

### 2.2 التوليد

```sql
CREATE SEQUENCE child_code_sequence START 1;
SELECT nextval('child_code_sequence');   -- داخل الـ transaction
```

- **ممنوع** `MAX(sequence_number) + 1`.
- الـ Sequence في PostgreSQL مضمون الذرّية (atomic) ومقاوم للتزامن.
- الفجوات (gaps) عند rollback **مقبولة** ولا تُعاد الأرقام المستهلكة.
- `child_code` يُبنى ثم يُحفظ؛ القيد `unique(sequence_number)` + `unique(child_code)` هما الحماية النهائية.

### 2.3 الخصوصية

- الاسم الحقيقي يصل في `POST /children` فقط، يُستخدم في الذاكرة لتوليد الكود، **ولا يُحفظ**.
- يجب استثناؤه من: `audit_logs` (redaction)، الـ logs، والـ exception payloads.
- اختبار إلزامي: بعد التسجيل، لا يوجد أي أثر للاسم في الجداول ولا في `audit_logs`.

### 2.4 التطبيع (يحتاج تأكيد العميل — P-03)

1. إزالة التشكيل والتطويل.
2. توحيد `أ إ آ` → `ا`، `ى` → `ي`، `ة` → `ه`.
3. تجاهل المسافات عند أخذ أول حرفين من **الكلمة الأولى** فقط (حسب المثال).
4. رفض الاسم إذا كان أقل من حرفين عربيين (validation).
5. خريطة transliteration في `config/child_code.php` (قابلة للتعديل بدون كود).

> مثال: "أحمد" → `AH`، "محمد" → `MH`، "رامي" → `RA`.

---

## 3. الجداول المقترحة (Schema v2)

> جميع الجداول: `id uuid PK` + `created_at`/`updated_at` إلا ما ذُكر خلاف ذلك.
> جميع الـ FKs صريحة مع سياسة `restrict` افتراضيًا، و`cascade` فقط للبيانات التابعة.

### 3.1 Identity & Access

#### `users`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| name | varchar(150) | null (إلزامي للـ Staff تطبيقيًا) |
| email | varchar(190) | unique, null (lowercase) |
| mobile | varchar(20) | unique, null |
| password | varchar(255) | not null |
| is_active | boolean | default true |
| created_by_user_id | uuid | null → users.id (nullOnDelete) |
| last_login_at | timestamptz | null |
| created_at / updated_at | timestamptz | |
| deleted_at | timestamptz | null |

Indexes: `unique(email)`, `unique(mobile)`, `index(is_active)`, `index(created_by_user_id)`.

#### `roles`
`name` varchar(40) unique, `description` text null, `is_system_role` bool default false.
Seed: `SUPER_ADMIN`, `CO_ADMIN`, `SPECIALIST`, `PARENT`, `RECEPTION`.

#### `permissions`
`name` varchar(100) unique (مثال `cases.assign_specialist`), `description` text null.

#### `user_roles` — PK (`user_id`, `role_id`), FKs cascade.
#### `role_permissions` — PK (`role_id`, `permission_id`), FKs cascade.

#### `user_permissions` **(جديد — C-03)**
| Column | Type | Constraints |
|---|---|---|
| user_id | uuid | → users.id cascade |
| permission_id | uuid | → permissions.id cascade |
| is_granted | boolean | not null |
| granted_by_user_id | uuid | → users.id nullOnDelete |
| created_at / updated_at | timestamptz | |

PK (`user_id`, `permission_id`).
**قاعدة الحل:** `user_permissions` تتفوق على `role_permissions` (grant أو deny صريح).
`SUPER_ADMIN` يتجاوز كل الفحوصات عبر `Gate::before`.

#### `parents`
`user_id` uuid unique → users.id cascade.
(الاسم في `users.name`.)

#### `specialists`
`user_id` uuid unique → users.id cascade.

### 3.2 Organization

#### `branches`
`name` varchar(120), `is_active` boolean default true (لإيقاف فرع دون حذف).
Index: partial unique `(name) WHERE deleted_at IS NULL`.

#### `knowledge_sources`
`name` varchar(120) unique, `is_active` bool default true.

### 3.3 Children & Cases

#### `children`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| parent_id | uuid | not null → parents.id restrict |
| child_code | varchar(20) | not null unique |
| sequence_number | bigint | not null unique |
| age | smallint | not null, check 0–120 |
| age_recorded_at | date | not null default current_date |
| nationality | varchar(60) | not null |
| neighborhood | varchar(120) | not null |
| initial_problem | text | not null |
| knowledge_source_id | uuid | null → knowledge_sources.id restrict |
| identity_verified | boolean | not null default false |
| created_at / updated_at | timestamptz | |
| deleted_at | timestamptz | null |

Indexes: `index(parent_id)`, `index(knowledge_source_id)`.

#### `cases`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| child_id | uuid | not null → children.id cascade |
| branch_id | uuid | not null → branches.id restrict |
| specialist_id | uuid | not null → specialists.id restrict *(P-13)* |
| status | varchar(30) | null (القيم غير نهائية — لا enum) |
| created_at / updated_at / deleted_at | | |

Indexes: `(child_id)`, `(branch_id)`, `(specialist_id, status)`.

### 3.4 Assessment

#### `initial_assessments`
`child_id` uuid **unique** → children.id cascade, `assessment_date` date not null, `notes` text null.
**بدون soft delete** (D-05).

#### `assessment_questions`
`section_name` varchar(120), `question_text` text, `question_type` varchar(20) (text|boolean|single_choice|number), `options` jsonb null, `display_order` smallint, `is_active` bool default true.
**بدون delete** — الإيقاف عبر `is_active`.

#### `assessment_answers`
| Column | Type |
|---|---|
| assessment_id | uuid → initial_assessments.id cascade |
| question_id | uuid → assessment_questions.id restrict |
| question_text_snapshot | text |
| section_name_snapshot | varchar(120) |
| question_type_snapshot | varchar(20) |
| answer_value | text |

Unique (`assessment_id`, `question_id`).

#### `functional_scales`
`name` varchar(120) unique, `description` text null, `display_order` smallint, `is_active` bool.
Seed: 10 مقاييس (الأسماء النهائية P-09).

#### `functional_scale_scores`
`assessment_id` → cascade، `scale_id` → restrict، `scale_name_snapshot` varchar(120)، `score` smallint **CHECK 0..10**، `specialist_comment` text null.
Unique (`assessment_id`, `scale_id`).

#### `reinforcer_catalog` **(جديد — C-04)**
`category` varchar(20) CHECK IN (`material`,`social`,`activity`,`food`)، `name` varchar(120)، `is_active` bool، `display_order` smallint.
Unique (`category`, `name`).

#### `assessment_reinforcers` **(جديد)**
`assessment_id` → cascade، `reinforcer_id` → restrict، `reinforcer_name_snapshot` varchar(120)، `category_snapshot` varchar(20)، `notes` text null.
Unique (`assessment_id`, `reinforcer_id`). وجود الصف = معزز فعّال/مُختار.

#### `consent_types`
`name` varchar(120) unique، `description` text، `is_required` bool default true، `display_order` smallint، `is_active` bool.
Seed: `MEDICATION`, `EMERGENCY`, `PHOTOGRAPHY` (النصوص العربية في الـ seeder، وتحتاج مراجعة قانونية).

#### `child_consents`
`child_id` → cascade، `consent_type_id` → restrict، `accepted_at` timestamptz، `accepted_by_user_id` → users.id restrict.
Unique (`child_id`, `consent_type_id`). **لا UPDATE ولا DELETE.**

### 3.5 Treatment

#### `treatment_plans`
`case_id` → cascade، `title` varchar(200)، `plan_type` varchar(20) (integration|individual — P-06)، `start_date` date، `end_date` date null (CHECK `end_date >= start_date`)، `status` varchar(20) null، `created_by_user_id` → users.id restrict، soft deletes.
Index `(case_id)`.

#### `plan_specialists` — PK (`plan_id`, `specialist_id`).
#### `goals`
`plan_id` → cascade، `specialist_id` → restrict، `title` varchar(200)، `description` text null، `display_order` smallint، `progress_percentage` numeric(5,2) default 0 **CHECK 0..100**، soft deletes.

#### `goal_progress_history`
`goal_id` → cascade، `progress_percentage` numeric(5,2) CHECK 0..100، `notes` text null، `recorded_by_user_id` → users.id restrict، `recorded_at` timestamptz.
Index `(goal_id, recorded_at DESC)`. **Append-only.**

### 3.6 Reports

#### `reports`
`case_id` → cascade، `specialist_id` → restrict، `session_date` date، `session_notes` text null، `details` jsonb null *(مؤقت لحين وصول القالب — D-11)*، `visible_to_parent` bool default true، soft deletes.
Indexes: `(case_id, session_date DESC)`, `(specialist_id)`.

### 3.7 Homework

#### `homework`
`case_id` → cascade، `created_by_user_id` → users.id restrict، `title` varchar(200)، `description` text، `due_date` date null، `status` varchar(20) default `pending`، `completed_at` timestamptz null، `completed_by_user_id` → users.id nullOnDelete، soft deletes.
Index `(case_id, status)`.

#### `homework_comments` **(جديد — C-05)**
`homework_id` → cascade، `user_id` → users.id restrict، `body` text.
Index `(homework_id, created_at)`. **Append-only.**

### 3.8 Appointments

#### `appointments`
`child_id` → cascade، `branch_id` → restrict، `scheduled_by_user_id` → users.id nullOnDelete، `appointment_date` timestamptz، `status` varchar(20) null *(P-16)*، soft deletes.
Indexes: `(appointment_date)`, `(child_id)`, `(branch_id)`.

### 3.9 Notifications & Audit

#### `notifications`
`recipient_user_id` → cascade، `title` varchar(200)، `message` text، `notification_type` varchar(50)، `related_entity` varchar(50) null، `related_record_id` uuid null، `is_read` bool default false، `read_at` timestamptz null، `sent_at` timestamptz null (للقنوات مستقبلًا)، `created_at`.
Index `(recipient_user_id, is_read, created_at DESC)`.

#### `audit_logs`
| Column | Type |
|---|---|
| id | uuid PK |
| user_id | uuid null → users.id nullOnDelete |
| actor_role | varchar(40) null |
| action | varchar(100) |
| entity_name | varchar(100) |
| record_id | uuid null |
| old_values | jsonb null |
| new_values | jsonb null |
| ip_address | inet null |
| user_agent | varchar(255) null |
| created_at | timestamptz |

Indexes: `(entity_name, record_id)`, `(user_id, created_at DESC)`, `(created_at DESC)`.
Trigger `audit_logs_immutable` يمنع UPDATE/DELETE.

---

## 4. مصفوفة الصلاحيات (مبدئية)

> الصلاحيات تُخزَّن كأسماء `module.action`. SUPER_ADMIN يتجاوز كل شيء.

| Permission | SUPER_ADMIN | CO_ADMIN (قابل للضبط) | SPECIALIST | PARENT | RECEPTION |
|---|---|---|---|---|---|
| `admins.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `users.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `specialists.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `parents.manage` | ✅ | ✅ (قابل للضبط) | ❌ | ❌ | ❌ |
| `branches.manage` | ✅ | قابل للضبط | ❌ | ❌ | ❌ |
| `lookups.manage` | ✅ | قابل للضبط | ❌ | ❌ | ❌ |
| `children.view_all` / `create` / `update` | ✅ | قابل للضبط | ❌ | ❌ (يرى أطفاله) | قابل للضبط |
| `cases.view_all` / `create` / `assign_specialist` | ✅ | قابل للضبط | ❌ (المسندة فقط) | ❌ | قابل للضبط |
| `assessments.view_all` / `create` / `update` | ✅ | قابل للضبط | ✅ (المسندة) | ❌ (نتائج مبسطة فقط) | قابل للضبط |
| `consents.record` | ✅ | قابل للضبط | ❌ | ✅ (أطفاله) | قابل للضبط |
| `plans.*` / `goals.*` | ✅ | قابل للضبط | ✅ (cases المسندة) | ❌ | ❌ |
| `reports.view_all` / `hide_from_parent` | ✅ | قابل للضبط | ❌ (تقاريره/cases المسندة) | ❌ (المرئي فقط) | ❌ |
| `homework.create` / `update` | ✅ | قابل للضبط | ✅ (cases المسندة) | ❌ | ❌ |
| `homework.complete` / `comment` | ❌ | ❌ | ❌ | ✅ (أطفاله فقط) | ❌ |
| `appointments.*` | ✅ | قابل للضبط | ✅ (عرض) | ❌ | قابل للضبط |
| `audit_logs.view` | ✅ | قابل للضبط | ❌ | ❌ | ❌ |
| `notifications.view` | ✅ | ✅ | ✅ | ✅ | ✅ |

**قواعد صارمة:**
- CO_ADMIN لا يستطيع إنشاء/تعديل/تعطيل أي حساب إداري (فقط SPECIALIST).
- لا يمكن لأي مستخدم رفع صلاحياته أو تعديل `user_permissions` الخاصة به.
- Parent ownership: `child.parent.user_id === auth()->id()` في كل طلب.
- Specialist scope: `case.specialist_id === auth()->user()->specialist->id` في كل طلب.

---

## 5. سياسات الحذف (Soft Delete)

| الجدول | softDeletes | السبب |
|---|---|---|
| users, parents, specialists | ✅ | تعطيل/أرشفة مع الحفاظ على السجلات. |
| branches, knowledge_sources | ✅ / `is_active` | لا تُحذف الفروع التي لها Cases. |
| children, cases | ✅ | منع فقدان التاريخ. |
| treatment_plans, goals, reports, homework, appointments | ✅ | سجلات تشغيلية. |
| initial_assessments, assessment_answers, child_consents, goal_progress_history, homework_comments, audit_logs | ❌ | سجلات تاريخية غير قابلة للتعديل. |

> **ملاحظة أمنية:** أي endpoint يستخدم route-model-binding يجب أن يستثني soft-deleted
> تلقائيًا (سلوك Laravel الافتراضي) + Policy تمنع الوصول لأبناء آخرين.

---

## 6. استعلامات حرجة (N+1 Prevention)

| الشاشة | Eager Loading |
|---|---|
| قائمة الأطفال لإداري | `parent.user:id,name` + `cases:id,child_id,branch_id,specialist_id` |
| Case details | `child.parent.user`, `branch`, `specialist.user`, `latestAssessment` |
| Assessment chart | `functionalScaleScores` + `answers.question` (snapshot) |
| Plan | `goals.latestProgress`, `specialists.user` |
| Report list (parent) | `case.child` (مفلتر بالملكية) + `specialist.user` |
| Homework list | `case.child`, `comments.user` |

---

## 7. مخاطر أمنية يجب اختبارها

1. **IDOR على الأطفال**: `GET /children/{id}` من ولي أمر آخر → 404 (لا 403، لتقليل تسريب الوجود).
2. **IDOR على التقارير**: تقرير `visible_to_parent = false` لا يظهر حتى لو عرف الـ parent الـ id.
3. **Specialist scope**: أخصائي لا يستطيع قراءة/تعديل Case غير مسند إليه.
4. **تسريب الاسم**: الاسم الحقيقي لا يظهر في أي response/audit/log.
5. **تصعيد الصلاحيات**: CO_ADMIN يحاول إنشاء CO_ADMIN آخر → 403.
6. **Audit tampering**: محاولة `UPDATE audit_logs` تفشل على مستوى DB.
7. **Rate limiting** على `/auth/login` و`/auth/parent/login`.
8. **Mass assignment**: `$fillable` محددة، و`role`/`is_active`/`permissions` غير قابلة للتمرير من الطلب.
9. **Consent forgery**: لا يمكن تسجيل consent لطفل ليس تحت ملكية الولي.
10. **Homework tampering**: ولي أمر يعدّل واجب طفل آخر → 404/403 + audit log.

---

## 8. ما لم يُدرج في التصميم ولماذا

| غير مُدرج | السبب |
|---|---|
| `sessions` table | التقرير هو سجل الجلسة (§4-J). |
| `payments`, `invoices`, `discounts` | خارج النطاق. |
| `attachments`, `media` | غير مطلوب. |
| `final_assessments` | غير معرّف (P-21). |
| `case_status_history` | قيم الحالة غير نهائية (P-05). |
| `specialty` للأخصائي | غير مطلوب. |
| `settings` table | لا يوجد متطلب إعدادات. |
| `password_reset_tokens` | يعتمد على طريقة دخول ولي الأمر (P-01). |
