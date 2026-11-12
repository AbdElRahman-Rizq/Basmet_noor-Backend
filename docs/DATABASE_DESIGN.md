# بصمة نور — تصميم قاعدة البيانات v3

> **الحالة:** ⏳ بانتظار اعتماد العميل. **لا migrations ولا كود قبل الاعتماد.**
> **الإصدار:** v3 · **التاريخ:** 2026-11-12
> **الأساس:** `ERD.txt` + متطلبات §4 + قرارات العميل + مراجعة العميل على v2.

---

## 0. سجل الإصدارات

| الإصدار | التغيير |
|---|---|
| v1 | مراجعة الـ ERD الأصلي. |
| v2 | تصحيحات C-01…C-07 + إضافات أمنية. |
| **v3** | **دور أساسي واحد عبر `users.role_id`** · **`cases.specialist_id` nullable مع بوابة إسناد** · **Reports = ملف PDF مرفوع + ربط الأهداف** · **تاريخ تقدم بـ previous/new + `report_id`** · **آلية صلاحيات صريحة ومقاومة للتصعيد** · **فرض الإقرارات الثلاثة عند التسجيل** · **توثيق حالات تحويل الأسماء الخاصة**. |

### ملخص التغييرات v2 → v3

| # | v2 | v3 |
|---|---|---|
| 1 | `user_roles` many-to-many | **حُذف** — `users.role_id` (دور أساسي واحد). |
| 2 | `cases.specialist_id` NOT NULL | **nullable** + `assigned_at` + `assigned_by_user_id` + بوابة عمل. |
| 3 | `reports.session_notes` + `details jsonb` | **حُذفا** — استبدالهما ببيانات ملف PDF. |
| 4 | `goal_progress_history.progress_percentage` | `previous_percentage` + `new_percentage` + `report_id` nullable. |
| 5 | المواعيد بلا أخصائي (قرار ضمني) | **3 خيارات موثقة + توصية مؤقتة B**، والقرار للعميل (P-15). |
| 6 | آلية الصلاحيات موصوفة ضمنيًا | **خوارزمية صريحة + 6 ثوابت ضد التصعيد**. |
| 7 | الإقرارات: seed + unique فقط | **Validation إلزامي داخل transaction التسجيل**. |
| 8 | تحويل الأسماء: مبدأ عام | **قاعدة Transliterate-then-truncate + جدول حالات خاصة + fallback يدوي**. |

---

## 1. المبادئ الحاكمة للتصميم

1. **لا اسم طفل في قاعدة البيانات.** `child_name` حقل Request فقط، لا `$fillable`، ولا audit، ولا log.
2. **Clinical data لا يُحذف فعليًا.** Soft delete فقط، والـ FKs تستخدم `RESTRICT` افتراضيًا.
3. **التاريخ لا يُعاد كتابته.** الإجابات، الدرجات، التقدم، الإقرارات، والـ audit سجلات append-only.
4. **القيم غير النهائية تبقى `varchar null`.** لا enums ولا CHECK على حالات لم يعتمدها العميل.
5. **الأمان في الاستعلام، لا بعده.** كل وصول لبيانات طفل يبدأ من Scope يفرض الملكية/الإسناد.
6. **Single source of truth.** الاسم في `users.name`، التقدم الحالي في `goals.progress_percentage`،
   والتاريخ الكامل في `goal_progress_history`.

---

## 2. مراجعة الـ ERD — القرار النهائي

### 2.1 التصحيحات الحرجة (معتمدة)

| # | الملاحظة | الحل في v3 | الحالة |
|---|---|---|---|
| C-01 | لا snapshot لنص السؤال | `assessment_answers`: `question_text_snapshot`, `section_name_snapshot`, `question_type_snapshot` | ✅ |
| C-02 | لا snapshot لاسم المقياس | `functional_scale_scores.scale_name_snapshot` | ✅ |
| C-03 | لا صلاحيات فردية | `user_permissions` (`is_granted`) فوق صلاحيات الدور | ✅ |
| C-04 | لا جداول معززات | `reinforcer_catalog` + `assessment_reinforcers` + snapshot | ✅ |
| C-05 | تعليق واجب واحد يُستبدل | `homework_comments` (append-only) + `completed_by_user_id` | ✅ |
| C-06 | لا نسبة تقدم حالية | `goals.progress_percentage` + `goal_progress_history` | ✅ |
| C-07 | `initial_problem` في `cases` | نُقلت إلى `children.initial_problem` | ✅ |

### 2.2 التصحيحات التصميمية (معتمدة)

| # | الحل في v3 |
|---|---|
| D-01 | `users.name` مصدرًا واحدًا للاسم؛ حُذف `parents.full_name` و`specialists.full_name`. |
| D-02 | العمود `password` بدل `password_hash`. |
| D-03 | `audit_logs`: `user_id` nullable + `actor_role` + `ip_address` + `user_agent`. |
| D-04 | Trigger يمنع `UPDATE`/`DELETE` على `audit_logs`. |
| D-05 | لا soft delete على `initial_assessments` (تقييم واحد لكل طفل للأبد). |
| D-06 | `cases.specialist_id` **nullable** + بوابة إسناد (انظر §6). |
| D-07 | `appointments`: 3 خيارات موثقة، لا حسم (انظر §8). |
| D-08 | `notifications`: in-app فقط، بلا قنوات. |
| D-09 | `age` + `age_recorded_at` (P-04). |
| D-10 | `consent_types.is_required` + `display_order`. |
| D-11 | Reports = PDF مرفوع (يحل محل `details jsonb`). |
| D-12 | Indexes صريحة لكل FK واستعلام متكرر (§10). |
| D-13 | Partial unique على `branches.name WHERE deleted_at IS NULL`. |
| D-14 | `user_roles` **حُذف** — دور واحد لكل مستخدم. |
| D-15 | `users.is_active` مصدر وحيد للتعطيل. |
| D-16 | لا `specialty` للأخصائي (غير مطلوب). |
| D-17 | timestamps على `knowledge_sources`. |
| D-18 | تحقق تطبيقي: Staff يلزمه email، وParent يلزمه mobile (رهن P-01). |
| D-19 | لا ربط بين `cases` و`initial_assessments` (التقييم على مستوى الطفل). |
| D-20 | لا timestamps على pivots. |

---

## 3. العلاقات — المراجعة النهائية

### 3.1 المصفوفة

| العلاقة | Cardinality | FK | On Delete | ملاحظات |
|---|---|---|---|---|
| users → roles | N:1 | `users.role_id` | RESTRICT | دور أساسي واحد إلزامي. |
| users → users (creator) | N:1 | `users.created_by_user_id` | SET NULL | تتبع مَن أنشأ الحساب. |
| users → permissions | N:M | `user_permissions` | CASCADE | Override فردي. |
| roles → permissions | N:M | `role_permissions` | CASCADE | الصلاحيات الافتراضية. |
| users → parents | 1:1 | `parents.user_id` | CASCADE | بروفايل ولي الأمر. |
| users → specialists | 1:1 | `specialists.user_id` | CASCADE | بروفايل الأخصائي. |
| parents → children | 1:N | `children.parent_id` | RESTRICT | ولي أمر بعدة أطفال. |
| knowledge_sources → children | 1:N | `children.knowledge_source_id` | RESTRICT | nullable. |
| children → cases | 1:N | `cases.child_id` | RESTRICT | حالات متعددة. |
| branches → cases | 1:N | `cases.branch_id` | RESTRICT | حالات في فروع مختلفة. |
| specialists → cases | 1:N | `cases.specialist_id` | RESTRICT | **nullable** حتى الإسناد. |
| children → initial_assessments | 1:1 | `initial_assessments.child_id` | RESTRICT | unique، مرة واحدة. |
| initial_assessments → answers | 1:N | `assessment_answers.assessment_id` | CASCADE | مملوكة للتقييم. |
| assessment_questions → answers | 1:N | `assessment_answers.question_id` | RESTRICT | snapshot يحفظ المعنى. |
| initial_assessments → scale scores | 1:N | `functional_scale_scores.assessment_id` | CASCADE | |
| functional_scales → scores | 1:N | `functional_scale_scores.scale_id` | RESTRICT | snapshot للاسم. |
| initial_assessments → reinforcers | 1:N | `assessment_reinforcers.assessment_id` | CASCADE | |
| reinforcer_catalog → assessment_reinforcers | 1:N | `reinforcer_id` | RESTRICT | snapshot للاسم/الفئة. |
| children → child_consents | 1:N | `child_consents.child_id` | RESTRICT | سجل قانوني. |
| consent_types → child_consents | 1:N | `consent_type_id` | RESTRICT | 3 أنواع مطلوبة. |
| cases → treatment_plans | 1:N | `treatment_plans.case_id` | RESTRICT | |
| treatment_plans → specialists | N:M | `plan_specialists` | CASCADE (pivot) | أخصائيون متعددون. |
| treatment_plans → goals | 1:N | `goals.plan_id` | RESTRICT | |
| specialists → goals | 1:N | `goals.specialist_id` | RESTRICT | أخصائي واحد لكل هدف. |
| goals → progress_history | 1:N | `goal_progress_history.goal_id` | RESTRICT | append-only. |
| reports → progress_history | 1:N | `goal_progress_history.report_id` | **SET NULL** | اختياري؛ التقدم لا يُفقد بحذف تقرير. |
| cases → reports | 1:N | `reports.case_id` | RESTRICT | |
| specialists → reports | 1:N | `reports.specialist_id` | RESTRICT | مُنشئ التقرير. |
| cases → homework | 1:N | `homework.case_id` | RESTRICT | |
| homework → comments | 1:N | `homework_comments.homework_id` | CASCADE | append-only. |
| children → appointments | 1:N | `appointments.child_id` | RESTRICT | |
| branches → appointments | 1:N | `appointments.branch_id` | RESTRICT | |
| specialists → appointments | 1:N | `appointments.specialist_id` | RESTRICT | **مؤقت — P-15**. |
| users → notifications | 1:N | `notifications.recipient_user_id` | CASCADE | in-app. |
| users → audit_logs | 1:N | `audit_logs.user_id` | SET NULL | السجل يبقى بعد حذف المستخدم. |

### 3.2 فحص العلاقات (Sanity Check)

- لا توجد علاقة N:M إلا `plan_specialists` و`role/user_permissions` → لا تكرار بيانات.
- كل علاقة "سجل تاريخي" تستخدم `RESTRICT`، ولا يوجد Cascade على بيانات طبية.
- `goal_progress_history.report_id` هو **الرابط الوحيد** بين التقرير والأهداف — لا جدول pivot مكرر.
- `reports` لا ترتبط بـ `plan_id`؛ الربط يمر عبر `case_id` ثم `plans.case_id`
  (يُتحقق منه تطبيقيًا داخل transaction).
- التقييم مرتبط بالطفل لا بالـ Case → يطابق §4-E حرفيًا.

---

## 4. Schema v3

> كل الجداول: `id uuid PK` + `created_at`/`updated_at` إلا ما ذُكر.
> الـ FKs صريحة، و`RESTRICT` هو الافتراضي.

### 4.1 Identity & Access

#### `roles`
`name` varchar(40) unique · `description` text null · `is_system_role` bool default false · `level` smallint NOT NULL.

| name | level | الغرض |
|---|---|---|
| SUPER_ADMIN | 100 | تجاوز كامل. |
| CO_ADMIN | 80 | إداري بصلاحيات فردية. |
| RECEPTION | 60 | **غير مفعّل** حتى P-02. |
| SPECIALIST | 40 | أخصائي. |
| PARENT | 20 | ولي أمر. |

`CHECK (level BETWEEN 0 AND 100)`.

#### `permissions`
`name` varchar(100) unique · `description` text null · `module` varchar(50) NOT NULL (للتجميع في الواجهة).

#### `role_permissions`
PK (`role_id`, `permission_id`) · FK cascade · index على `permission_id`.

#### `users`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| role_id | uuid | **NOT NULL** → roles.id RESTRICT |
| name | varchar(150) | null (إلزامي للـ Staff تطبيقيًا) |
| email | varchar(190) | unique · null (lowercase) |
| mobile | varchar(20) | unique · null |
| password | varchar(255) | NOT NULL |
| is_active | bool | default true |
| created_by_user_id | uuid | null → users.id SET NULL |
| last_login_at | timestamptz | null |
| created_at / updated_at | timestamptz | |
| deleted_at | timestamptz | null |

Indexes: `unique(email)`, `unique(mobile)`, `(role_id, is_active)`, `(created_by_user_id)`.

**Invariants تطبيقية:**
- `role_id` لا يُقبل من أي Request عام؛ يُحدَّد بواسطة Service بعد فحص الصلاحية.
- Staff (SUPER_ADMIN/CO_ADMIN/RECEPTION/SPECIALIST) يلزمه `email`.
- تغيير الدور أو الحالة يُسجَّل في `audit_logs` بقيم قديمة/جديدة.

#### `user_permissions`
| Column | Type | Constraints |
|---|---|---|
| user_id | uuid | → users.id CASCADE |
| permission_id | uuid | → permissions.id CASCADE |
| is_granted | bool | NOT NULL |
| granted_by_user_id | uuid | → users.id SET NULL |
| created_at / updated_at | timestamptz | |

PK (`user_id`, `permission_id`) · index على `permission_id`.

- لا صفوف لـ SUPER_ADMIN (يُرفض تطبيقيًا؛ تجاوزه أصلاً غير قابل للتعديل).
- `is_granted = false` = سحب صريح يتجاوز صلاحية الدور.

#### `parents`
`user_id` uuid unique → users.id CASCADE.

#### `specialists`
`user_id` uuid unique → users.id CASCADE.

### 4.2 Organization

#### `branches`
`name` varchar(120) · `is_active` bool default true · timestamps · softDeletes.
Partial unique: `(name) WHERE deleted_at IS NULL`.

#### `knowledge_sources`
`name` varchar(120) unique · `is_active` bool default true · timestamps.

### 4.3 Children & Cases

#### `children`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| parent_id | uuid | NOT NULL → parents.id RESTRICT |
| child_code | varchar(20) | NOT NULL unique |
| sequence_number | bigint | NOT NULL unique (global) |
| age | smallint | NOT NULL · CHECK 0..120 |
| age_recorded_at | date | NOT NULL default CURRENT_DATE |
| nationality | varchar(60) | NOT NULL |
| neighborhood | varchar(120) | NOT NULL |
| initial_problem | text | NOT NULL |
| knowledge_source_id | uuid | null → knowledge_sources.id RESTRICT |
| identity_verified | bool | NOT NULL default false |
| created_at / updated_at | timestamptz | |
| deleted_at | timestamptz | null |

Indexes: `(parent_id)`, `(knowledge_source_id)`.

#### `cases`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| child_id | uuid | NOT NULL → children.id RESTRICT |
| branch_id | uuid | NOT NULL → branches.id RESTRICT |
| specialist_id | uuid | **NULL** → specialists.id RESTRICT |
| assigned_at | timestamptz | NULL |
| assigned_by_user_id | uuid | NULL → users.id SET NULL |
| status | varchar(30) | NULL (قيم غير نهائية) |
| created_at / updated_at | timestamptz | |
| deleted_at | timestamptz | null |

Indexes: `(child_id)`, `(branch_id)`, `(specialist_id)`, `(specialist_id, status)`.

`CHECK`:
```sql
(specialist_id IS NULL     AND assigned_at IS NULL     AND assigned_by_user_id IS NULL)
OR
(specialist_id IS NOT NULL AND assigned_at IS NOT NULL AND assigned_by_user_id IS NOT NULL)
```

### 4.4 Assessment

#### `initial_assessments`
`child_id` uuid **unique** → children.id RESTRICT · `assessment_date` date NOT NULL · `notes` text null.
**بدون soft delete.**

#### `assessment_questions`
`section_name` varchar(120) · `question_text` text · `question_type` varchar(20) CHECK IN (`text`,`boolean`,`single_choice`,`number`) · `options` jsonb null · `display_order` smallint · `is_active` bool · timestamps.
**لا حذف** — `is_active = false` فقط.

#### `assessment_answers`
| Column | Type |
|---|---|
| assessment_id | uuid → initial_assessments.id CASCADE |
| question_id | uuid → assessment_questions.id RESTRICT |
| question_text_snapshot | text NOT NULL |
| section_name_snapshot | varchar(120) NOT NULL |
| question_type_snapshot | varchar(20) NOT NULL |
| answer_value | text |

Unique (`assessment_id`, `question_id`) · Index `(question_id)`.

#### `functional_scales`
`name` varchar(120) unique · `description` text null · `display_order` smallint · `is_active` bool · timestamps.

#### `functional_scale_scores`
`assessment_id` → CASCADE · `scale_id` → RESTRICT · `scale_name_snapshot` varchar(120) NOT NULL · `score` smallint **CHECK 0..10** · `specialist_comment` text null.
Unique (`assessment_id`, `scale_id`) · Index `(scale_id)`.

#### `reinforcer_catalog`
`category` varchar(20) **CHECK IN (`material`,`social`,`activity`,`food`)** · `name` varchar(120) · `is_active` bool · `display_order` smallint · timestamps.
Unique (`category`, `name`).

#### `assessment_reinforcers`
`assessment_id` → CASCADE · `reinforcer_id` → RESTRICT · `reinforcer_name_snapshot` varchar(120) · `category_snapshot` varchar(20) · `notes` text null.
Unique (`assessment_id`, `reinforcer_id`).

#### `consent_types`
`name` varchar(120) unique · `description` text · `is_required` bool · `display_order` smallint · `is_active` bool · timestamps.
Seed فقط (3 أنواع) — **لا CRUD إداري في v1**.

#### `child_consents`
`child_id` → RESTRICT · `consent_type_id` → RESTRICT · `accepted_at` timestamptz NOT NULL · `accepted_by_user_id` → users.id RESTRICT · `created_at`.
Unique (`child_id`, `consent_type_id`) · Index `(consent_type_id)`.
**لا UPDATE ولا DELETE.**

### 4.5 Treatment

#### `treatment_plans`
`case_id` → RESTRICT · `title` varchar(200) · `plan_type` varchar(20) (integration|individual — P-06) · `start_date` date · `end_date` date null · `status` varchar(20) null · `created_by_user_id` → users.id RESTRICT · timestamps · softDeletes.
Index `(case_id)`. `CHECK (end_date IS NULL OR end_date >= start_date)`.

#### `plan_specialists`
PK (`plan_id`, `specialist_id`) · FKs cascade/pivot · index `(specialist_id)`.

#### `goals`
`plan_id` → RESTRICT · `specialist_id` → RESTRICT · `title` varchar(200) · `description` text null · `display_order` smallint · `progress_percentage` numeric(5,2) default 0 **CHECK 0..100** · timestamps · softDeletes.
Indexes `(plan_id)`, `(specialist_id)`, `(plan_id, display_order)`.

#### `goal_progress_history`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| goal_id | uuid | NOT NULL → goals.id RESTRICT |
| report_id | uuid | **NULL** → reports.id **SET NULL** |
| previous_percentage | numeric(5,2) | NOT NULL · CHECK 0..100 |
| new_percentage | numeric(5,2) | NOT NULL · CHECK 0..100 |
| notes | text | null |
| recorded_by_user_id | uuid | NOT NULL → users.id RESTRICT |
| recorded_at | timestamptz | NOT NULL |

Indexes: `(goal_id, recorded_at DESC)`, `(report_id)`.
Partial unique: `(report_id, goal_id) WHERE report_id IS NOT NULL` — هدف واحد مرة واحدة لكل تقرير.
**Append-only** (لا update/delete).

### 4.6 Reports (PDF workflow)

#### `reports`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| case_id | uuid | NOT NULL → cases.id RESTRICT |
| specialist_id | uuid | NOT NULL → specialists.id RESTRICT (مَن رفع) |
| session_date | date | NOT NULL |
| file_path | varchar(255) | NOT NULL · unique (private disk) |
| file_original_name | varchar(255) | NOT NULL |
| file_mime | varchar(100) | NOT NULL · **CHECK = `application/pdf`** |
| file_size | integer | NOT NULL · CHECK > 0 |
| file_checksum | char(64) | NOT NULL (SHA-256) |
| visible_to_parent | bool | NOT NULL default true |
| created_at / updated_at | timestamptz | |
| deleted_at | timestamptz | null |

Indexes: `(case_id, session_date DESC)`, `(specialist_id)`.

**ملاحظات:**
- لا `session_notes` ولا `details jsonb` — التقرير محتواه داخل الـ PDF.
- التقرير **create-only**؛ الحقول القابلة للتعديل إداريًا: `visible_to_parent` فقط.
- استبدال/تصحيح الملف غير محدد → P-07/P-28.
- `file_path` لا يُعاد في أي API Response؛ الـ Resource يرجّع `download_url` داخلي.

### 4.7 Homework

#### `homework`
`case_id` → RESTRICT · `created_by_user_id` → RESTRICT · `title` varchar(200) · `description` text · `due_date` date null · `status` varchar(20) default `pending` · `completed_at` timestamptz null · `completed_by_user_id` → users.id SET NULL · timestamps · softDeletes.
Indexes `(case_id, status)`, `(created_by_user_id)`.

#### `homework_comments`
`homework_id` → CASCADE · `user_id` → RESTRICT · `body` text NOT NULL · `created_at`.
Index `(homework_id, created_at)`. **Append-only.**

### 4.8 Appointments

#### `appointments`
`child_id` → RESTRICT · `branch_id` → RESTRICT · `scheduled_by_user_id` → SET NULL · `appointment_date` timestamptz · `status` varchar(20) null · `specialist_id` uuid **NULL** → RESTRICT *(مؤقت — P-15)* · timestamps · softDeletes.
Indexes `(appointment_date)`, `(child_id)`, `(branch_id)`, `(specialist_id)`.

### 4.9 Notifications & Audit

#### `notifications`
`recipient_user_id` → CASCADE · `title` varchar(200) · `message` text · `notification_type` varchar(50) · `related_entity` varchar(50) null · `related_record_id` uuid null · `is_read` bool default false · `read_at` timestamptz null · `sent_at` timestamptz null · `created_at`.
Index `(recipient_user_id, is_read, created_at DESC)`.

#### `audit_logs`
`user_id` nullable → SET NULL · `actor_role` varchar(40) null · `action` varchar(100) · `entity_name` varchar(100) · `record_id` uuid null · `old_values` jsonb null · `new_values` jsonb null · `ip_address` inet null · `user_agent` varchar(255) null · `created_at`.
Indexes `(entity_name, record_id)`, `(user_id, created_at DESC)`, `(created_at DESC)`.
Trigger `audit_logs_immutable` يمنع `UPDATE`/`DELETE`.

---

## 5. محرك الصلاحيات (Permission Engine)

### 5.1 الخوارزمية

```php
effective(user):
    if user.role.name === 'SUPER_ADMIN':
        return ALL_PERMISSIONS            // تجاوز كامل

    base = permissions(role_id = user.role_id)   // role_permissions

    grants = user_permissions[user].where(is_granted = true)
    denies = user_permissions[user].where(is_granted = false)

    return (base ∪ grants) − denies
```

- **الترتيب ملزم:** `user_permissions` (grant أو deny) تتفوق على الدور.
- **Deny يتفوق على Grant** لنفس الصلاحية (لو وُجد صف واحد فقط لكل زوج، فلا تعارض).
- النتيجة تُحسب مرة واحدة لكل request (memoized على Model). لا cache دائم في v1.

### 5.2 الثوابت ضد التصعيد (Anti-Escalation Invariants)

| # | القاعدة | مكان التنفيذ |
|---|---|---|
| I-1 | فقط SUPER_ADMIN يملك `permissions.manage` — بما فيه تعديل `user_permissions` و`roles` | Seeder + Policy |
| I-2 | لا يمكن تعديل `role_id` أو `is_active` أو `user_permissions` الخاصة بالمستخدم نفسه | Policy + FormRequest |
| I-3 | CO_ADMIN ينشئ/يعدّل حسابات **SPECIALIST فقط** — حتى لو مُنح صلاحية `users.manage` لاحقًا | `RoleAssignmentPolicy` (قاعدة صريحة، ليست level فقط) |
| I-4 | لا يُمنح مستخدم صلاحية لا يملكها المُنح (Delegation safety) | `GrantPermissionAction` |
| I-5 | لا يمكن تعيين دور `level >= actor.level` إلا لـ SUPER_ADMIN | `RoleAssignmentPolicy` |
| I-6 | لا `user_permissions` على SUPER_ADMIN (تجاوزه ثابت) | Validation + DB guard تطبيقي |
| I-7 | أي تغيير في الدور/الصلاحيات/التعطيل → `audit_logs` بقيم قديمة/جديدة | Observer/Action |

### 5.3 الصلاحيات المبدئية (Seed)

| Module | Permissions |
|---|---|
| users | `users.view`, `users.create`, `users.update`, `users.deactivate` |
| admins | `admins.manage` *(SUPER_ADMIN فقط)* |
| permissions | `permissions.manage` *(SUPER_ADMIN فقط)* |
| specialists | `specialists.view`, `specialists.manage` |
| parents | `parents.view`, `parents.manage` |
| branches | `branches.view`, `branches.manage` |
| lookups | `lookups.view`, `lookups.manage` |
| children | `children.view_all`, `children.view_own`, `children.create`, `children.update` |
| cases | `cases.view_all`, `cases.view_assigned`, `cases.create`, `cases.update`, `cases.assign_specialist` |
| assessments | `assessments.view_all`, `assessments.view_assigned`, `assessments.create`, `assessments.update`, `assessments.manage_questions`, `assessments.manage_scales`, `assessments.manage_reinforcers` |
| consents | `consents.view`, `consents.record` |
| plans | `plans.view`, `plans.create`, `plans.update`, `plans.assign_specialist` |
| goals | `goals.create`, `goals.update`, `goals.record_progress` |
| reports | `reports.view_all`, `reports.view_assigned`, `reports.create`, `reports.hide_from_parent`, `reports.download` |
| homework | `homework.view`, `homework.create`, `homework.update`, `homework.complete`, `homework.comment` |
| appointments | `appointments.view`, `appointments.create`, `appointments.update` |
| notifications | `notifications.view` |
| audit | `audit_logs.view` |

### 5.4 الصلاحيات الافتراضية لكل دور

- **SUPER_ADMIN:** تجاوز كامل (تُسجَّل كل الصلاحيات للعرض فقط).
- **CO_ADMIN:** مجموعة محافظة قابلة للتعديل فرديًا:
  `children.*`, `parents.*`, `cases.*` (بدون `assign_specialist`؟ لا — يملكها),
  `assessments.view_all/create/update/manage_*`, `consents.*`, `plans.*`, `goals.*`,
  `reports.view_all`, `homework.view`, `appointments.*`, `notifications.view`, `specialists.view/manage`.
  **لا يملك:** `admins.manage`, `users.manage`, `permissions.manage`, `audit_logs.view`, `reports.hide_from_parent`.
- **SPECIALIST:** `cases.view_assigned`, `assessments.view_assigned`, `plans.view`,
  `goals.record_progress`, `reports.view_assigned/create/download`,
  `homework.view/create/update`, `appointments.view`, `notifications.view`.
- **PARENT:** `children.view_own`, `assessments.view_own`, `reports.download`,
  `homework.view/complete/comment`, `consents.record`, `notifications.view`.
- **RECEPTION:** **بدون أي صلاحية في v1** حتى اعتماد P-02.

---

## 6. بوابة الإسناد (Case Assignment Gate)

### 6.1 القاعدة

`cases.specialist_id` يبدأ `NULL`، ويُمنع أي "عمل علاجي" قبل الإسناد.

| العملية | مسموحة بدون إسناد؟ |
|---|---|
| إنشاء Case | ✅ |
| تعديل بيانات إدارية للـ Case (فرع/حالة) | ✅ |
| عرض الـ Case للإدارة | ✅ |
| إنشاء خطة علاجية | ❌ `CASE_NOT_ASSIGNED` |
| إضافة/تعديل هدف | ❌ |
| تسجيل تقدم | ❌ |
| رفع تقرير / ربط أهداف | ❌ |
| إنشاء واجب | ❌ |
| وصول الأخصائي للـ Case | ❌ (يظهر فقط بعد الإسناد) |

### 6.2 التنفيذ

- `AssignSpecialistAction`:
  - يتحقق من صلاحية `cases.assign_specialist`.
  - يتحقق أن المستخدم الهدف دوره SPECIALIST و`is_active = true`.
  - يضبط `specialist_id`, `assigned_at = now()`, `assigned_by_user_id = actor`.
  - يسجّل audit (`case.assigned`) بالقيم القديمة/الجديدة.
- `Case::requireAssigned()` أو `EnsureCaseIsAssigned` Action تُستدعى في كل Action علاجي.
- إعادة الإسناد (Reassignment): مسموحة إداريًا مع audit، لكن **غير محسومة** (P-13):
  - هل تُنقل الخطط والتقارير السابقة؟ (التقارير تحتفظ بـ `specialist_id` القديم — لا تتغير.)
  - هل يُمنع الأخصائي القديم من الوصول فورًا؟ (نعم افتراضيًا.)
- الحالات غير النهائية لا تُستخدم كبوابة (لأن قيمها غير معتمدة).

---

## 7. التقرير = PDF + تحديث تقدم الأهداف

### 7.1 سير العمل (Confirmed)

```
1. الأخصائي المسند للـ Case يرفع ملف PDF.
2. يحدد session_date.
3. يختار هدفًا واحدًا أو أكثر من أهداف خطط نفس الـ Case
   التي تم العمل عليها في الجلسة.
4. يسجّل النسبة الحالية (0–100) لكل هدف مختار.
5. النظام داخل transaction واحدة:
   أ. يحفظ reports + metadata الملف
   ب. لكل هدف: يقرأ previous = goals.progress_percentage
      يحفظ history(previous, new, report_id, recorded_by)
      يحدّث goals.progress_percentage = new
   ج. يسجّل audit(report.created)
```

### 7.2 قواعد التحقق

| # | القاعدة | الخطأ |
|---|---|---|
| R-1 | `specialist_id = actor.specialist->id` وأن الـ Case مسند إليه | 403 |
| R-2 | `case.specialist_id IS NOT NULL` | 422 `CASE_NOT_ASSIGNED` |
| R-3 | كل `goal_id` تابع لخطة `plan.case_id = report.case_id` | 422 `GOAL_NOT_IN_CASE` |
| R-4 | لا تكرار لنفس `goal_id` في نفس الطلب | 422 |
| R-5 | عدد الأهداف ≥ 1 | 422 |
| R-6 | `session_date` ليست في المستقبل | 422 |
| R-7 | الملف PDF حقيقي (`mime` + magic bytes)، الحجم ≤ الحد المعتمد | 422 `INVALID_FILE` |
| R-8 | الملف يُحفظ باسم عشوائي على **private disk** | — |
| R-9 | لا تعديل على progress history بعد الحفظ | 405/403 |
| R-10 | في v1 لا يوجد endpoint لتحديث التقدم مباشرة؛ التقدم يُسجَّل عبر التقرير فقط | — |

### 7.3 تخزين الملفات (Security)

| البند | القرار |
|---|---|
| Disk | `local` / `reports` خاص — **ليس** `public`. |
| المسار | `reports/{case_uuid}/{uuid}.pdf` — لا يحتوي أي اسم. |
| اسم الملف الأصلي | يُحفظ كنص للعرض فقط، لا يُستخدم في المسار. |
| التحقق | `mimetypes:application/pdf` + فحص `%PDF-` magic bytes + الحجم. |
| التنزيل | `GET /reports/{id}/download` عبر Policy، `Content-Disposition: attachment`. |
| الوصول المباشر | ممنوع (لا `storage:link` للمجلد). |
| Checksum | SHA-256 لمنع التلاعب وللكشف عن الملفات المكررة. |
| الحذف | Soft delete للسجل؛ الملف يبقى (retention غير محدد P-26). |
| الفيروسات | غير منفّذ (لا خدمة متاحة) — موثّق كـ F-11. |

### 7.4 الأثر على العلاقات

- `goal_progress_history.report_id` (nullable, `SET NULL`) → لا يُفقد تاريخ التقدم.
- `reports` لا تحتاج pivot مع `goals`؛ صفوف التقدم هي الرابط.
- `goals.progress_percentage` يبقى القيمة الحالية للقراءة السريعة/charts.

---

## 8. المواعيد — الخيارات وأثرها (P-15)

| الخيار | Schema | المزايا | العيوب | أثر التغيير لاحقًا |
|---|---|---|---|---|
| **A** — بلا أخصائي | لا عمود | مطابق للـ ERD، أقل افتراضات | لا يمكن إشعار/إسناد مَن سيقوم بالتقييم، ولا معرفة المسؤول | إضافة عمود nullable لاحقًا (سهل) |
| **B** — أخصائي اختياري *(مؤقت)* | `specialist_id uuid NULL` | يدعم الحالتين (معلوم/غير معلوم)، لا يفرض سيناريو | يحتاج قاعدة "متى يصبح إلزاميًا؟" | لا تغيير هيكلي |
| **C** — أخصائي إلزامي | `specialist_id NOT NULL` | مسؤولية واضحة | يمنع الحجز قبل تحديد الأخصائي، وقد يفرض إسنادًا خاطئًا | تحويل nullable → NOT NULL مؤلم لاحقًا |

**التوصية المؤقتة:** **B**، مع منع "تأكيد" الموعد بدون أخصائي إن قرر العميل ذلك.
**لا تُبنى أي حالة موعد أو إشعار جماعي قبل P-15.**

---

## 9. كود الطفل

### 9.1 التوليد

```
child_code = first2(transliterate(child_name)) . first2(transliterate(parent_name)) . nextval(sequence)
```

- `child_code varchar(20)` unique · `sequence_number bigint` unique.
- `CREATE SEQUENCE child_code_sequence START 1` — **لا MAX+1**.
- الفجوات مقبولة. الكود لا يُعاد استخدامه حتى بعد soft delete.

### 9.2 الخوارزمية

1. تنظيف الاسم: إزالة التشكيل والتطويل، توحيد المسافات، إزالة الأرقام والرموز.
2. Transliteration إلى Latin (خريطة في `config/child_code.php`).
3. Uppercase + إزالة أي حرف غير `A-Z`.
4. أخذ أول **حرفين لاتينيين** من الناتج الكامل (وليس من أول حرفين عربيين).

> هذا يحل مشكلة الحروف المزدوجة: "شادي" → `SHADY` → `SH`، و"خالد" → `KHALED` → `KH`.

### 9.3 خريطة أساسية (قابلة للتعديل)

| عربي | لاتيني | عربي | لاتيني | عربي | لاتيني |
|---|---|---|---|---|---|
| ا / أ / آ | A | ح | H | ع | A |
| إ | E | خ | KH | غ | GH |
| ب | B | د | D | ف | F |
| ت | T | ذ | DH | ق | Q |
| ث | TH | ر | R | ك | K |
| ج | J | ز | Z | ل | L |
| س | S | ش | SH | م | M |
| ص | S | ض | D | ن | N |
| ط | T | ظ | Z | ه | H |
| و | W | ي / ى | Y | ة | H |
| ؤ | W | ئ | Y | ء | (يُحذف) |

### 9.4 الحالات الخاصة (موثقة ومسجلة في P-03)

| الحالة | الافتراضي في v3 | مثال | ملاحظة |
|---|---|---|---|
| اسم بأل التعريف | **لا تُحذف** | "الحسين" → `AL` | قد يفضّل العميل `HU` |
| اسم مركّب | لا معالجة خاصة | "عبد الرحمن" → `AB` | أول حرفين من الناتج اللاتيني |
| اسم غير عربي | تحويل مباشر بعد Uppercase | "Adam" → `AD` | يعمل |
| اسم بحرف واحد | فشل الإدخال | "ع" | يحتاج `child_initials` يدويًا |
| حرف بلا مقابل | فشل الإدخال | حروف فارسية/أردية | fallback يدوي |
| همزات | A / E / A | "إيمان" → `EM` (IMAN) | حسب الخريطة |
| مسافات متعددة | تُطوى | "محمد   علي" → `MO` | — |

**Fallback اليدوي (مقترح):** يسمح النظام لموظف مخوّل بإرسال
`child_initials` و`parent_initials` (حرفان `A-Z` لكل منهما) عند فشل التحويل،
مع تسجيل العملية في `audit_logs` **بدون** الاسم. يحتاج اعتماد P-03.

### 9.5 الخصوصية

- `child_name` و`parent_name` (عند إنشاء ولي أمر جديد) حقول Request فقط.
- مستثناة من `$fillable` والـ audit والـ logs والـ exception payloads.
- اختبار إلزامي: بعد التسجيل، `SELECT` على كل الجداول + `audit_logs` لا يحتوي الاسم.

---

## 10. الإقرارات — الفرض عند التسجيل (وليس Seed فقط)

### 10.1 التحقق (Application Layer)

```
POST /children  (transaction)
  required_types = consent_types.where(is_required = true, is_active = true)
  provided       = request.consents (distinct consent_type_id)

  if any required type not in provided        → 422 CONSENTS_INCOMPLETE
  if provided contains an unknown/extra type  → 422 CONSENT_TYPE_INVALID
  if duplicate consent_type_id               → 422 CONSENT_DUPLICATE
  if accepted_at > now()                     → 422 CONSENT_DATE_INVALID

  create child + consents (accepted_at = provided ?? now, accepted_by = actor)
```

- جميع الإقرارات تُنشأ **داخل نفس transaction** الخاصة بتسجيل الطفل.
- أي فشل → rollback كامل (لا طفل بدون إقرارات).
- `consent_types` الثلاثة تُحمى: لا deactivate ولا delete ولا CRUD إداري.

### 10.2 الاختبارات الإلزامية

1. طفل بدون consents → 422.
2. طفل بـ 2 من 3 → 422 مع تحديد الناقص.
3. طفل بـ 4 أنواع (منها غير مطلوب) → 422.
4. `consent_type_id` مكرر → 422.
5. `accepted_at` في المستقبل → 422.
6. نجاح → 3 صفوف بـ `accepted_at` غير null.
7. محاولة `PUT/DELETE` على consent → 405.
8. ولي أمر يحاول تسجيل إقرار لطفل غيره → 404.

---

## 11. ترتيب الـ Migrations

| # | Migration | يعتمد على |
|---|---|---|
| 1 | `roles`, `permissions`, `role_permissions` | — |
| 2 | `users` (+`role_id`), `user_permissions` | 1 |
| 3 | `parents`, `specialists` | 2 |
| 4 | `branches`, `knowledge_sources` | — |
| 5 | `children` + `CREATE SEQUENCE child_code_sequence` | 3, 4 |
| 6 | `cases` | 3, 4, 5 |
| 7 | `initial_assessments` | 5 |
| 8 | `assessment_questions`, `assessment_answers` | 7 |
| 9 | `functional_scales`, `functional_scale_scores` | 7 |
| 10 | `reinforcer_catalog`, `assessment_reinforcers` | 7 |
| 11 | `consent_types`, `child_consents` | 5 |
| 12 | `treatment_plans`, `plan_specialists`, `goals` | 6 |
| 13 | `reports` | 6 |
| 14 | `goal_progress_history` | 12, 13 |
| 15 | `homework`, `homework_comments` | 6 |
| 16 | `appointments` | 4, 5 |
| 17 | `notifications` | 2 |
| 18 | `audit_logs` + trigger | 2 |

---

## 12. ملخص الـ Constraints

### 12.1 Unique
`roles.name` · `permissions.name` · `users.email` · `users.mobile` · `parents.user_id` ·
`specialists.user_id` · `children.child_code` · `children.sequence_number` ·
`initial_assessments.child_id` · `assessment_answers(assessment_id, question_id)` ·
`functional_scales.name` · `functional_scale_scores(assessment_id, scale_id)` ·
`reinforcer_catalog(category, name)` · `assessment_reinforcers(assessment_id, reinforcer_id)` ·
`consent_types.name` · `child_consents(child_id, consent_type_id)` ·
`plan_specialists(plan_id, specialist_id)` · `reports.file_path` ·
بتقنية partial: `branches.name WHERE deleted_at IS NULL` ·
`goal_progress_history(report_id, goal_id) WHERE report_id IS NOT NULL`.

### 12.2 CHECK
`children.age 0..120` · `functional_scale_scores.score 0..10` ·
`treatment_plans.end_date >= start_date` · `goals.progress_percentage 0..100` ·
`goal_progress_history.previous/new 0..100` · `reports.file_mime = 'application/pdf'` ·
`reports.file_size > 0` · `reinforcer_catalog.category IN (...)` ·
`roles.level 0..100` · `cases` assignment consistency.

### 12.3 NOT NULL الحاكمة
`users.role_id` · `users.password` · `children.parent_id` · `children.child_code` ·
`children.sequence_number` · `children.initial_problem` · `cases.child_id/branch_id` ·
`reports.case_id/specialist_id/session_date/file_*` · `child_consents.accepted_at`.

---

## 13. ملخص الـ Indexes

| الجدول | Index | السبب |
|---|---|---|
| users | `(role_id, is_active)` | قوائم المستخدمين بالدور. |
| users | `(created_by_user_id)` | تتبع المنشئ. |
| user_permissions | `(permission_id)` | reverse lookup. |
| children | `(parent_id)` | أطفال ولي الأمر. |
| cases | `(child_id)` | حالات الطفل. |
| cases | `(branch_id)` | تقارير الفرع. |
| cases | `(specialist_id, status)` | لوحة الأخصائي. |
| assessment_answers | `(question_id)` | تحليل الأسئلة. |
| functional_scale_scores | `(scale_id)` | charts/إحصاءات. |
| assessment_reinforcers | `(reinforcer_id)` | استخدام المعززات. |
| child_consents | `(consent_type_id)` | تدقيق الإقرارات. |
| treatment_plans | `(case_id)` | خطط الحالة. |
| plan_specialists | `(specialist_id)` | خطط الأخصائي. |
| goals | `(plan_id, display_order)` | ترتيب الأهداف. |
| goals | `(specialist_id)` | أهداف الأخصائي. |
| goal_progress_history | `(goal_id, recorded_at DESC)` | أحدث تقدم. |
| goal_progress_history | `(report_id)` | تقرير → أهداف. |
| reports | `(case_id, session_date DESC)` | قائمة تقارير الحالة. |
| reports | `(specialist_id)` | تقارير الأخصائي. |
| homework | `(case_id, status)` | واجبات معلّقة. |
| homework_comments | `(homework_id, created_at)` | تعليقات مرتبة. |
| appointments | `(appointment_date)` | التقويم. |
| appointments | `(child_id)` / `(branch_id)` / `(specialist_id)` | فلاتر. |
| notifications | `(recipient_user_id, is_read, created_at DESC)` | جرس الإشعارات. |
| audit_logs | `(entity_name, record_id)` | تاريخ سجل معيّن. |
| audit_logs | `(user_id, created_at DESC)` | نشاط مستخدم. |

---

## 14. سياسات الحذف

| الجدول | softDeletes | ملاحظة |
|---|---|---|
| users, parents, specialists | ✅ | تعطيل/أرشفة. |
| branches, knowledge_sources | ✅ / `is_active` | لا حذف لفرع له Cases. |
| children, cases | ✅ | لا حذف نهائي. |
| treatment_plans, goals, reports, homework, appointments | ✅ | سجلات تشغيلية. |
| initial_assessments, answers, scores, consents, progress history, comments, audit | ❌ | تاريخية/append-only. |
| reports (files) | الملف يبقى بعد soft delete | retention P-26. |

---

## 15. اختبارات القبول قبل التنفيذ (Schema Tests)

1. `migrate:fresh` + `db:seed` تنجح من الصفر.
2. `nextval` متوازٍ (50 طلب) → 50 رقمًا مختلفًا.
3. إدخال `score = 11` → DB exception.
4. إدخال `progress = -1` → DB exception.
5. `UPDATE audit_logs` → DB exception.
6. `DELETE child_consents` → ممنوع تطبيقيًا (لا endpoint).
7. تعديل سؤال → الإجابة القديمة تحتفظ بالنص القديم.
8. تعديل اسم مقياس → الدرجة القديمة تحتفظ بالاسم القديم.
9. حذف تقرير soft → `goal_progress_history.report_id` يبقى كما هو (soft delete لا يفعّل SET NULL).
10. حذف تقرير hard (اختبار فقط) → `report_id = NULL` والتقدم يبقى.
11. `case.specialist_id = NULL` + محاولة إنشاء خطة → 422.
12. رفع غير PDF → 422، ولا يُحفظ أي ملف.
13. تقرير لهدف في Case آخر → 422.
14. طفل بدون الإقرارات الثلاثة → 422 + لا صفوف في `children`.

---

## 16. خارج نطاق v3 (مؤكد)

`user_roles` · `sessions` · `payments` · `attachments` (عدا تقارير PDF) · `details jsonb` ·
`session_notes` · `final_assessments` · `case_status_history` · `specialty` · `settings` ·
`password_reset_tokens` · قنوات الإشعارات · Form Builder.
