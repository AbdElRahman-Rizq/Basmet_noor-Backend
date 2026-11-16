# بصمة نور — تصميم قاعدة البيانات v4

> **الحالة:** ⏳ بانتظار اعتماد العميل. **لا migrations ولا كود قبل الاعتماد.**
> **الإصدار:** v4 · **التاريخ:** 2026-11-13 (توضيحات Gate B؛ لا تغيير في الـ Schema)
> **تحديث توثيقي:** 2026-11-17 — تثبيت **D-046** (`age` فقط) و**D-047** (درجات integer)
> و**D-048/D-049** (P-21/P-22 خارج V1). **لا تغيير هيكلي في الـ Schema.**
> **اعتماد Gate C:** **D-050** (P-12: A1+B2+C0) · **D-051** (P-20: Retention Only) ·
> **D-052** (P-31: هدف واحد على الأقل) · **D-053** (P-15: B+). التغيير الهيكلي الوحيد:
> `child_consents.consent_version` و`consent_text_snapshot` أصبحا **NOT NULL**.
> **الأساس:** `ERD.txt` + متطلبات §4 + قرارات العميل + مراجعة العميل على v2/v3.

> **قاعدة الاعتماد:** لا يُنشأ مشروع Laravel ولا migration ولا كود تطبيقي قبل اعتماد هذه الوثيقة.
> ترتيب الاعتماد الملزم: **(A) اعتماد الوثائق → (B) تجهيز البيئة → (C) الـ migrations → (D) الـ Backend**.
> أي بند `PENDING` لا يُنفَّذ ولا يُفترض له سلوك افتراضي.

---

## 0. سجل الإصدارات

| الإصدار | التغيير |
|---|---|
| v1 | مراجعة الـ ERD الأصلي. |
| v2 | تصحيحات C-01…C-07 + إضافات أمنية. |
| v3 | دور أساسي واحد عبر `users.role_id` · `cases.specialist_id` nullable مع بوابة إسناد · Reports = ملف PDF مرفوع + ربط الأهداف · تاريخ تقدم بـ previous/new + `report_id` · آلية صلاحيات صريحة ومقاومة للتصعيد · فرض الإقرارات الثلاثة عند التسجيل · توثيق حالات تحويل الأسماء الخاصة. |
| **v4** | **تثبيت المصادقة كقرار معلّق (P-01/P-32)** · **قاعدة عدم تكرار حساب ولي الأمر** · **تأكيد `plan_specialists` كجدول مشاركي الخطة (D-040)** · **فصل `accepted_by` عن `recorded_by` في الإقرارات (P-12)** · **تصحيح تعارض `SET NULL` مع قيود/Triggers الحماية** · **Trigger لثبات تاريخ التقدم** · **توحيد مصفوفة الحذف** · **تعديل مثال كود الطفل الخاطئ** · **بوابات اعتماد صريحة**. |

> **تحديث توثيقي (2026-11-17، لا يغيّر رقم الإصدار):** قرارات Gate C الجديدة
> **D-046** (`children.age` فقط، لا `date_of_birth`) · **D-047** (`functional_scale_scores.score` integer فقط)
> · **D-048** (P-21 «التقييم النهائي» خارج V1) · **D-049** (P-22 «ملاحظات الجلسة الأولى» خارج V1).
> هذه القرارات **توثّق الوضع الحالي للـ Schema** ولا تضيف/تحذف أي عمود أو جدول.

### ملخص التغييرات v3 → v4

| # | v3 | v4 |
|---|---|---|
| 1 | D-18 يفرض email للـ Staff وmobile للـ Parent | **معلّق** — لا فرض قبل P-01/P-32؛ يلزم identifier واحد على الأقل تطبيقيًا. |
| 2 | لا قاعدة صريحة لمنع تكرار ولي الأمر | **قاعدة Find-or-Create + منع التكرار** داخل transaction التسجيل (D-033). |
| 3 | `plan_specialists` موجود بلا تبرير كافٍ | **مؤكد** كجدول مشاركي الخطة، ويسمح بأكثر من أخصائي مسؤول (D-040). |
| 4 | `child_consents.accepted_by_user_id` = actor | **فصل** `accepted_by_user_id` (nullable) عن `recorded_by_user_id` (NOT NULL) + حقول نسخة/نص اختيارية (D-035). |
| 5 | `audit_logs.user_id` SET NULL مع Trigger يمنع UPDATE | **RESTRICT** + توثيق أن الحذف النهائي للمستخدم غير مدعوم (D-036). |
| 6 | `goal_progress_history.report_id` SET NULL | **RESTRICT** + Trigger يمنع UPDATE/DELETE على تاريخ التقدم (D-036/D-037). |
| 7 | `cases.assigned_by_user_id` SET NULL مع CHECK | **RESTRICT** لحفظ اتساق CHECK. |
| 8 | مصفوفة حذف غير متسقة (`parents`/`specialists`/`knowledge_sources`) | **مصفوفة حذف موحّدة** لكل الجداول (D-038). |
| 9 | عدد أهداف التقرير ≥ 1 كقاعدة مؤكدة | **مؤقت — P-31**؛ لا تُثبّت قبل القرار. |
| 10 | مثال `"محمد   علي" → MO` | **خطأ**؛ الناتج الصحيح `MU` (Muhammad → MU). |

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

> **تجاوز تاريخي:** هذا جدول سجل v2 → v3 فقط. **D-041 (v4) ألغى Transliteration نهائيًا**:
> الأسماء تُدخل بالإنجليزية، والكود = أول حرفين من اسم الطفل + أول حرفين من اسم ولي الأمر
> + رقم تسلسلي Global مستقل. مثال `"محمد علي"` في السطر 10 أصبح غير ذي صلة.
> الحالات الحدّية تبقى في **P-03**.

---

## 1. المبادئ الحاكمة للتصميم

1. **لا اسم طفل في قاعدة البيانات.** `child_name` حقل Request فقط، لا `$fillable`، ولا audit، ولا log.
2. **Clinical data لا يُحذف فعليًا.** Soft delete فقط، والـ FKs تستخدم `RESTRICT` افتراضيًا.
3. **التاريخ لا يُعاد كتابته.** الإجابات، الدرجات، التقدم، الإقرارات، والـ audit سجلات append-only.
4. **القيم غير النهائية تبقى `varchar null`.** لا enums ولا CHECK على حالات لم يعتمدها العميل.
5. **الأمان في الاستعلام، لا بعده.** كل وصول لبيانات طفل يبدأ من Scope يفرض الملكية/الإسناد.
6. **Single source of truth.** اسم المستخدم/الموظف في `users.name` (واسم ولي الأمر إن اعتُمد — P-04)،
   التقدم الحالي في `goals.progress_percentage`، والتاريخ الكامل في `goal_progress_history`.
7. **لا قرار ضمني.** أي سؤال مصادقة/صلاحية/نص قانوني/حالة غير معتمدة يبقى `PENDING`؛
   لا يُختار له سلوك افتراضي في الـ Schema أو الـ Seeder أو الـ Validation.
8. **لا حذف نهائي لبيانات سريرية.** كل FK على بيانات تاريخية/سريرية يستخدم `RESTRICT`،
   و`CASCADE` محصور في الجداول اليتيمة (pivots/answers التابعة لتقييم) أو بيانات غير سريرية.

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

| # | الحل المعتمد (v3/v4) |
|---|---|
| D-01 | `users.name` مصدرًا واحدًا لاسم المستخدم/الموظف؛ حُذف `parents.full_name` و`specialists.full_name`. اسم ولي الأمر في `users.name` **معلّق P-04**. |
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
| D-18 | **لا فرض مسبق** لـ email على Staff ولا لـ mobile على Parent. يلزم identifier واحد على الأقل تطبيقيًا، ونوعه لكل دور **معلّق** (P-01/P-32). |
| D-19 | لا ربط بين `cases` و`initial_assessments` (التقييم على مستوى الطفل). |
| D-20 | لا timestamps على pivots. |
| D-21 | `child_consents`: فصل `accepted_by_user_id` (nullable) عن `recorded_by_user_id` (NOT NULL) + `consent_version`/`consent_text_snapshot` nullable (P-12). |
| D-22 | `goal_progress_history`: Trigger يمنع `UPDATE`/`DELETE`، و`report_id` → `RESTRICT` بدل `SET NULL`. |
| D-23 | `audit_logs.user_id` → `RESTRICT`؛ الحذف النهائي للمستخدم غير مدعوم. |
| D-24 | `parents`/`specialists` لهما `deleted_at` صراحة؛ `knowledge_sources` يعتمد `is_active` فقط بلا soft delete. |
| D-25 | `plan_specialists` **مؤكد** كجدول مشاركي الخطة؛ لا يمنح وصولًا للـ Case ولا يُفرض على `goals.specialist_id` في v1 (D-040). |
| D-26 | `cases.assigned_by_user_id` → `RESTRICT` لحفظ اتساق CHECK الخاص بالإسناد. |

---

## 3. العلاقات — المراجعة النهائية

### 3.1 المصفوفة

| العلاقة | Cardinality | FK | On Delete | ملاحظات |
|---|---|---|---|---|
| users → roles | N:1 | `users.role_id` | RESTRICT | دور أساسي واحد إلزامي. |
| users → users (creator) | N:1 | `users.created_by_user_id` | SET NULL | تتبع مَن أنشأ الحساب. |
| users → permissions | N:M | `user_permissions` | CASCADE | Override فردي. |
| roles → permissions | N:M | `role_permissions` | CASCADE | الصلاحيات الافتراضية. |
| users → parents | 1:1 | `parents.user_id` | CASCADE | بروفايل ولي الأمر؛ soft delete على `users`/`parents`. |
| users → specialists | 1:1 | `specialists.user_id` | CASCADE | بروفايل الأخصائي؛ soft delete على `users`/`specialists`. |
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
| users → child_consents (accepted) | 1:N | `accepted_by_user_id` | RESTRICT | **nullable**؛ حساب ولي الأمر عند القبول الرقمي. |
| users → child_consents (recorded) | 1:N | `recorded_by_user_id` | RESTRICT | **NOT NULL**؛ الموظف/المستخدم الذي سجّل الإقرار. |
| cases → treatment_plans | 1:N | `treatment_plans.case_id` | RESTRICT | |
| treatment_plans → specialists | N:M | `plan_specialists` | CASCADE (pivot) | **مؤكد (D-040)**؛ الخطة قد يكون لها أكثر من أخصائي مسؤول؛ لا يمنح وصولًا للـ Case. |
| treatment_plans → goals | 1:N | `goals.plan_id` | RESTRICT | |
| specialists → goals | 1:N | `goals.specialist_id` | RESTRICT | أخصائي واحد لكل هدف. |
| goals → progress_history | 1:N | `goal_progress_history.goal_id` | RESTRICT | append-only + Trigger. |
| reports → progress_history | 1:N | `goal_progress_history.report_id` | **RESTRICT** | اختياري (nullable)؛ لا حذف نهائي لتقرير له تاريخ. |
| cases → reports | 1:N | `reports.case_id` | RESTRICT | |
| specialists → reports | 1:N | `reports.specialist_id` | RESTRICT | مُنشئ التقرير. |
| cases → homework | 1:N | `homework.case_id` | RESTRICT | |
| homework → comments | 1:N | `homework_comments.homework_id` | CASCADE | append-only. |
| children → appointments | 1:N | `appointments.child_id` | RESTRICT | |
| branches → appointments | 1:N | `appointments.branch_id` | RESTRICT | |
| specialists → appointments | 1:N | `appointments.specialist_id` | RESTRICT | **مؤقت — P-15**. |
| users → cases (assigner) | 1:N | `cases.assigned_by_user_id` | RESTRICT | nullable فقط قبل الإسناد؛ CHECK يمنع null بعد الإسناد. |
| users → notifications | 1:N | `notifications.recipient_user_id` | CASCADE | in-app؛ بيانات غير سريرية. |
| users → audit_logs | 1:N | `audit_logs.user_id` | RESTRICT | الحذف النهائي للمستخدم غير مدعوم؛ `user_id` nullable لعمليات النظام. |

### 3.2 فحص العلاقات (Sanity Check)

- العلاقات N:M محصورة في pivots صريحة: `role_permissions`، `user_permissions`، `plan_specialists` → لا تكرار بيانات.
- كل علاقة "سجل تاريخي" تستخدم `RESTRICT`، ولا يوجد Cascade على بيانات طبية.
- `goal_progress_history.report_id` هو **الرابط الوحيد** بين التقرير والأهداف — لا جدول pivot مكرر.
- `reports` لا ترتبط بـ `plan_id`؛ الربط يمر عبر `case_id` ثم `plans.case_id`
  (يُتحقق منه تطبيقيًا داخل transaction).
- التقييم مرتبط بالطفل لا بالـ Case → يطابق §4-E حرفيًا.
- **`plan_specialists` مؤكد:** يمثّل الأخصائيين المشاركين/المسؤولين عن الخطة، ويسمح بتعددهم
  (D-040). لا يمنح وصولًا للـ Case؛ الوصول يبقى بـ `cases.specialist_id`. لا يُفرض في v1
  أن `goals.specialist_id` عضو فيه.
- **المصادقة غير محسومة:** وجود `email` و`mobile` معًا في `users` لا يعني اعتماد أيهما للدخول.
  كل دور له identifier واحد على الأقل تطبيقيًا، لكن تحديده معلّق (P-01/P-32).
- **ولي الأمر:** `parents.user_id` unique + `children.parent_id` NOT NULL يضمنان أن
  الحساب الواحد يملك عدة أطفال، ولا يُنشأ بروفايل ولي أمر مكرر لنفس المستخدم (D-033).

---

## 4. Schema v4

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
| name | varchar(150) | null (إلزامي للـ Staff تطبيقيًا؛ تخزين اسم ولي الأمر معلّق P-04) |
| email | varchar(190) | unique · null (lowercase) |
| mobile | varchar(20) | unique · null |
| password | varchar(255) | NOT NULL (طريقة الدخول ووجود كلمة المرور معلّقان P-01/P-32) |
| is_active | bool | default true |
| created_by_user_id | uuid | null → users.id SET NULL |
| last_login_at | timestamptz | null |
| created_at / updated_at | timestamptz | |
| deleted_at | timestamptz | null |

Indexes: `unique(email)`, `unique(mobile)`, `(role_id, is_active)`, `(created_by_user_id)`.

> **تنبيه (P-01):** `unique(mobile)` قيد تقني لحماية تكرار بيانات الاتصال، **وليس اعتمادًا
> لمفتاح هوية ولي الأمر**. لا يُستخدم Find-or-Create ولا يُفترض أن الجوال وحده يعرّف ولي الأمر (D-044).
> أي تعديل على هذا القيد يُعرض للاعتماد قبل Gate C.

**Invariants تطبيقية:**
- `role_id` لا يُقبل من أي Request عام؛ يُحدَّد بواسطة Service بعد فحص الصلاحية.
- **لا يُفرض `email` على Staff ولا `mobile` على Parent قبل P-01/P-32.** يلزم identifier واحد
  على الأقل (`email` أو `mobile`) تطبيقيًا، ونوعه لكل دور يُحسم بقرار العميل.
- كلمة المرور مطلوبة حاليًا في الـ Schema، لكن **آلية الدخول نفسها معلّقة**؛
  لا يُبنى Auth endpoint قبل P-01/P-32.
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
`user_id` uuid unique → users.id CASCADE · `created_at`/`updated_at`/`deleted_at` timestamptz.
**لا `full_name`** — الاسم (إن اعتُمد تخزينه) في `users.name` (P-04).
- الحساب الواحد يدير عدة أطفال؛ `children.parent_id` هو الرابط.
- ممنوع إنشاء أكثر من بروفايل ولي أمر لنفس `user_id` (unique).

#### `specialists`
`user_id` uuid unique → users.id CASCADE · `created_at`/`updated_at`/`deleted_at` timestamptz.
- حساب واحد لكل أخصائي؛ التعطيل عبر `users.is_active` أو soft delete.

### 4.2 Organization

#### `branches`
`name` varchar(120) · `is_active` bool default true · timestamps · softDeletes.
Partial unique: `(name) WHERE deleted_at IS NULL`.

#### `knowledge_sources`
`name` varchar(120) unique · `is_active` bool default true · timestamps.
**لا soft delete** — التعطيل عبر `is_active` فقط (D-24)؛ لا يُحذف مصدر مستخدم في طفل.

### 4.3 Children & Cases

#### `children`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| parent_id | uuid | NOT NULL → parents.id RESTRICT |
| child_code | varchar(20) | NOT NULL unique · CHECK `^[A-Z]{4}[0-9]+$` |
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

> **العمر (D-046):** `age` + `age_recorded_at` هما المصدر المعتمد الوحيد لعمر الطفل.
> **لا يُضاف `date_of_birth`** ولا أي عمود مشتق/مرتبط به في V1. `age_recorded_at` يثبّت وقت تسجيل العمر.
> اسم ولي الأمر يبقى ضمن **P-04 (Parent Name)** ولم يُحسم بهذا القرار.

> **قاعدة ولي الأمر:** التسجيل يقبل `parent_id` لحساب قائم أو ينشئ/يعيد استخدام حساب واحد
> حسب identifier مؤكد. ممنوع إنشاء حساب ولي أمر مكرر لنفس الشخص. مفتاح المطابقة
> (email/mobile/غير ذلك) معلّق P-01/P-32؛ **رقم الجوال وحده ليس المفتاح المعتمد** (D-044)،
> وعند التعارض يُرجع 422 ولا يُنشأ تكرار (D-033).

#### `cases`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| child_id | uuid | NOT NULL → children.id RESTRICT |
| branch_id | uuid | NOT NULL → branches.id RESTRICT |
| specialist_id | uuid | **NULL** → specialists.id RESTRICT |
| assigned_at | timestamptz | NULL |
| assigned_by_user_id | uuid | NULL → users.id **RESTRICT** |
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

> `assigned_by_user_id` يستخدم `RESTRICT` وليس `SET NULL`: لو حُذف المستخدم نهائيًا
> فإن `SET NULL` سينتج صفًا يخالف CHECK. الحذف النهائي للمستخدم غير مدعوم أصلًا (D-036).

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
`assessment_id` → CASCADE · `scale_id` → RESTRICT · `scale_name_snapshot` varchar(120) NOT NULL · `score` smallint **CHECK 0..10** · `specialist_comment` text null — **اختياري/غير إلزامي (D-045)**، لا CHECK ولا validation يفرضه.
Unique (`assessment_id`, `scale_id`) · Index `(scale_id)`.

> **نوع الدرجة (D-047):** `score` **integer فقط** (`smallint`) بمدى 0..10؛ **لا `decimal` ولا `float`** ولا كسور في V1.
> أسماء المقاييس العشرة وتوصيفها تبقى ضمن **P-09 (Scale Names)**.

#### `reinforcer_catalog`
`category` varchar(20) **CHECK IN (`material`,`social`,`activity`,`food`)** · `name` varchar(120) · `is_active` bool · `display_order` smallint · timestamps.
Unique (`category`, `name`).

#### `assessment_reinforcers`
`assessment_id` → CASCADE · `reinforcer_id` → RESTRICT · `reinforcer_name_snapshot` varchar(120) · `category_snapshot` varchar(20) · `notes` text null.
Unique (`assessment_id`, `reinforcer_id`).

#### `consent_types`
`name` varchar(120) unique · `description` text · `is_required` bool · `display_order` smallint · `is_active` bool · timestamps.
Seed فقط (3 أنواع) — **لا CRUD إداري في v1**، ولا نص قانوني مُختلق داخل الكود/الـ Seeder.
**محسوم (D-050):** القبول ورقي ويُسجَّل يدويًا بواسطة موظفي المركز؛ النص الفعلي يُعتمد من المركز/القانوني.

#### `child_consents`
| Column | Type | Constraints |
|---|---|---|
| child_id | uuid | NOT NULL → children.id RESTRICT |
| consent_type_id | uuid | NOT NULL → consent_types.id RESTRICT |
| accepted_at | timestamptz | NOT NULL |
| accepted_by_user_id | uuid | **NULL** → users.id RESTRICT (يبقى NULL للقبول الورقي في V1 — D-050) |
| recorded_by_user_id | uuid | **NOT NULL** → users.id RESTRICT (مَن سجّل الإقرار فعليًا) |
| consent_version | varchar(20) | **NOT NULL** (D-050) |
| consent_text_snapshot | text | **NOT NULL** (نص معتمد من المركز — D-050) |
| created_at | timestamptz | |

Unique (`child_id`, `consent_type_id`) · Indexes `(consent_type_id)`, `(accepted_by_user_id)`, `(recorded_by_user_id)`.
**لا UPDATE ولا DELETE** (ممنوع تطبيقيًا — C0؛ لا Trigger في V1). أي سحب/تصحيح مستقبلي يحتاج ADR جديدًا.

> **الفصل مهم:** `accepted_by_user_id` يجيب "مَن قبِل؟" (NULL لقبول ورقي)،
> و`recorded_by_user_id` يجيب "مَن أدخل السجل؟" (الموظف/المستخدم المنفّذ).
> لا يُستخدم `accepted_by` كبديل عن المنفّذ، ولا العكس.
>
> **محسوم (D-050 — A1/B2/C0):** الإقرار ورقي، ويُسجّله موظف مخوّل:
> `accepted_by_user_id = NULL` و`recorded_by_user_id = الموظف`.
> يُلزم حفظ `consent_version` + `consent_text_snapshot` من نص معتمد (NOT NULL).
> لا قبول رقمي ولا سحب/تصحيح في V1.

### 4.5 Treatment

#### `treatment_plans`
`case_id` → RESTRICT · `title` varchar(200) · `plan_type` varchar(20) (integration|individual — P-06) · `start_date` date · `end_date` date null · `status` varchar(20) null · `created_by_user_id` → users.id RESTRICT · timestamps · softDeletes.
Index `(case_id)`. `CHECK (end_date IS NULL OR end_date >= start_date)`.

#### `plan_specialists`
PK (`plan_id`, `specialist_id`) · `plan_id` → treatment_plans.id CASCADE · `specialist_id` → specialists.id CASCADE · index `(specialist_id)`.
**مؤكد — D-040:** يمثّل الأخصائيين المشاركين/المسؤولين عن الخطة، ويسمح بأكثر من أخصائي.
لا يمنح وصولًا للـ Case، ولا يُفرض في v1 أن `goals.specialist_id` ضمنه.
إدارة المشاركين تخضع لصلاحيات الخطط، دون افتراض صلاحية جديدة.

#### `goals`
`plan_id` → RESTRICT · `specialist_id` → RESTRICT · `title` varchar(200) · `description` text null · `display_order` smallint · `progress_percentage` numeric(5,2) default 0 **CHECK 0..100** · timestamps · softDeletes.
Indexes `(plan_id)`, `(specialist_id)`, `(plan_id, display_order)`.
- `specialist_id` يجب أن يشير إلى مستخدم بدور SPECIALIST ونشط (تطبيقيًا).
- **لا يُفرض** أن الأخصائي عضو في `plan_specialists` في v1 (D-040).
- `progress_percentage` لا يُحدَّث إلا عبر `RecordGoalProgressAction` (D-027/D-037).

#### `goal_progress_history`
| Column | Type | Constraints |
|---|---|---|
| id | uuid | PK |
| goal_id | uuid | NOT NULL → goals.id RESTRICT |
| report_id | uuid | **NULL** → reports.id **RESTRICT** |
| previous_percentage | numeric(5,2) | NOT NULL · CHECK 0..100 |
| new_percentage | numeric(5,2) | NOT NULL · CHECK 0..100 |
| notes | text | null |
| recorded_by_user_id | uuid | NOT NULL → users.id RESTRICT |
| recorded_at | timestamptz | NOT NULL |

Indexes: `(goal_id, recorded_at DESC)`, `(report_id)`.
Partial unique: `(report_id, goal_id) WHERE report_id IS NOT NULL` — هدف واحد مرة واحدة لكل تقرير.
**Append-only:** Trigger `goal_progress_history_immutable` يمنع `UPDATE`/`DELETE`.
- `report_id` nullable لأن P-31 قد يسمح بتحديث تقدم بلا تقرير؛ لكنه `RESTRICT` عند وجوده،
  فلا يُحذف تقرير نهائيًا وله تاريخ تقدم. الحذف العادي للتقرير soft delete فقط.
- أي تصحيح يتم بإدخال صف جديد، وليس بتعديل القديم.

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
- **التقرير المخفي يبقى متاحًا للإدارة** عبر `reports.view_all` (وSUPER_ADMIN تجاوزه كامل)؛
  `visible_to_parent = false` يخص ولي الأمر فقط (D-043).
- **الأخصائي لا يملك `reports.hide_from_parent` افتراضيًا**؛ الدور الدقيق الذي يملك التغيير
  ما زال P-07 إن لم يُؤكد صراحة.
- استبدال/تصحيح الملف غير محدد → P-07.
- `file_path` لا يُعاد في أي API Response؛ الـ Resource يرجّع `download_url` داخلي.
- **حد الحجم غير محسوم P-26**؛ يُقرأ من config ولا يُثبّت رقم في الكود قبل القرار.
- **عدد الأهداف المطلوب غير محسوم P-31**؛ لا تُفرض قاعدة "≥ 1" قبل القرار.
- الحذف soft delete فقط؛ الملف يبقى (retention P-26). لا حذف نهائي لتقرير له تاريخ تقدم
  بسبب `goal_progress_history.report_id RESTRICT`.

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
`user_id` nullable → **RESTRICT** · `actor_role` varchar(40) null · `action` varchar(100) · `entity_name` varchar(100) · `record_id` uuid null · `old_values` jsonb null · `new_values` jsonb null · `ip_address` inet null · `user_agent` varchar(255) null · `created_at`.
Indexes `(entity_name, record_id)`, `(user_id, created_at DESC)`, `(created_at DESC)`.
Trigger `audit_logs_immutable` يمنع `UPDATE`/`DELETE`.

> **تعارض تم تصحيحه:** `SET NULL` كان ينفّذ `UPDATE` على `audit_logs` عند حذف المستخدم،
> فيصطدم بـ Trigger المنع. لذلك `user_id` = `RESTRICT`، والحذف النهائي للمستخدم غير مدعوم.
> `user_id` يبقى nullable لعمليات النظام/المجهولة، و`actor_role` يحفظ الدور تاريخيًا (D-036).

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
| I-3 | CO_ADMIN ينشئ/يعدّل حسابات **SPECIALIST فقط** — حتى لو مُنح أي صلاحية إدارية لاحقًا. إنشاء الحساب يمر عبر `specialists.manage` وليس `users.*` | `RoleAssignmentPolicy` (قاعدة صريحة، ليست level فقط) |
| I-4 | لا يُمنح مستخدم صلاحية لا يملكها المُنح (Delegation safety) | `GrantPermissionAction` |
| I-5 | لا يمكن تعيين دور `level >= actor.level` إلا لـ SUPER_ADMIN | `RoleAssignmentPolicy` |
| I-6 | لا `user_permissions` على SUPER_ADMIN (تجاوزه ثابت) | Validation + DB guard تطبيقي |
| I-7 | أي تغيير في الدور/الصلاحيات/التعطيل → `audit_logs` بقيم قديمة/جديدة | Observer/Action |
| I-8 | CO_ADMIN لا يغيّر `role_id` لأي مستخدم، ولا يمنح/يسحب صلاحيات، ولا ينشئ PARENT/RECEPTION/CO_ADMIN | Policy + Service |
| I-9 | لا يستطيع أي مستخدم تعديل دوره أو حالته أو صلاحياته بنفسه (يشمل SUPER_ADMIN) | Policy + FormRequest |
| I-10 | PARENT لا يصل إلى أي endpoint إداري، ولا يرى إلا أطفاله (404 عند عدم الملكية) | ChildPolicy + Query Scope |
| I-11 | RECEPTION بلا أي صلاحية حتى P-02؛ لا يُفعّل دوره ضمنيًا بأي Seeder/Policy | Seeder + Policy |

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
  `children.*`, `parents.*`, `cases.view_all/create/update` (بدون `cases.assign_specialist` افتراضيًا — P-13),
  `assessments.view_all/create/update/manage_*`, `consents.view` (بدون `consents.record` افتراضيًا — P-12),
  `plans.*`, `goals.*`, `reports.view_all`, `homework.view`, `appointments.*`, `notifications.view`,
  `specialists.view/manage` (إنشاء/تعديل حسابات SPECIALIST فقط، وليس `users.*`).
  **لا يملك:** `admins.manage`, `permissions.manage`, `users.*` الإدارية, `audit_logs.view`,
  `reports.hide_from_parent`, و`consents.record`/`cases.assign_specialist` قبل P-12/P-13.
- **SPECIALIST:** `cases.view_assigned`, `assessments.view_assigned`, `plans.view`,
  `goals.record_progress`, `reports.view_assigned/create/download`,
  `homework.view/create/update`, `appointments.view`, `notifications.view`.
- **PARENT:** `children.view_own`, `assessments.view_own`, `reports.download`,
  `homework.view/complete/comment`, `notifications.view`.
  **بدون `consents.record`** حتى P-12 (لا نفترض أن ولي الأمر يسجّل الإقرار بنفسه).
- **RECEPTION:** **بدون أي صلاحية في v1** حتى اعتماد P-02.

> **ملاحظة:** أي صلاحية افتراضية معلّمة بـ PENDING ليست جزءًا من العقد؛
> SUPER_ADMIN يستطيع منحها فرديًا بعد القرار فقط. لا يعتمد أي كود على وجودها مسبقًا.

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
- **مَن يملك `cases.assign_specialist` غير محسوم (P-13).** الافتراضي المؤقت: SUPER_ADMIN فقط؛
  CO_ADMIN لا يملكها افتراضيًا، لكن SUPER_ADMIN قد يمنحها فرديًا بعد القرار.
- `Case::requireAssigned()` أو `EnsureCaseIsAssigned` Action تُستدعى في كل Action علاجي.
- إعادة الإسناد (Reassignment): **غير محسومة** (P-13):
  - هل تُسمح؟ مَن يملكها؟
  - هل تُنقل الخطط والتقارير السابقة؟ (التقارير تحتفظ بـ `specialist_id` القديم — لا تتغير.)
  - هل يُمنع الأخصائي القديم من الوصول فورًا؟
- **`plan_specialists` لا يمنح وصولًا للـ Case.** أي أخصائي غير مسند لا يرى الحالة حتى P-13.
- الحالات غير النهائية لا تُستخدم كبوابة (لأن قيمها غير معتمدة).

---

## 7. التقرير = PDF + تحديث تقدم الأهداف

### 7.1 سير العمل (Confirmed)

```
1. الأخصائي المسند للـ Case يرفع ملف PDF.
2. يحدد session_date.
3. يختار الأهداف التي عمل عليها في الجلسة من أهداف خطط نفس الـ Case.
   **محسوم (D-052):** هدف واحد على الأقل لكل تقرير نهائي.
4. يسجّل النسبة الحالية (0–100) لكل هدف مختار — إلزامية (D-052).
5. النظام داخل transaction واحدة:
   أ. يحفظ reports + metadata الملف
   ب. لكل هدف: يقرأ previous = goals.progress_percentage
      يحفظ history(previous, new, report_id, recorded_by)
      يحدّث goals.progress_percentage = new
   ج. يسجّل audit(report.created)
```

> **ليس Module لإدارة الجلسات:** لا `sessions` table ولا `session_id` ولا محرر تقارير.
> التقرير ملف PDF جاهز + بيانات وصفية + ربط أهداف.

### 7.2 قواعد التحقق

| # | القاعدة | الخطأ |
|---|---|---|
| R-1 | `specialist_id = actor.specialist->id` وأن الـ Case مسند إليه | 403 |
| R-2 | `case.specialist_id IS NOT NULL` | 422 `CASE_NOT_ASSIGNED` |
| R-3 | كل `goal_id` تابع لخطة `plan.case_id = report.case_id` | 422 `GOAL_NOT_IN_CASE` |
| R-4 | لا تكرار لنفس `goal_id` في نفس الطلب | 422 |
| R-5 | **عدد الأهداف: محسوم D-052 — هدف واحد على الأقل** لكل تقرير نهائي، ونسبة تقدم لكل هدف | 422 `GOALS_REQUIRED` |
| R-6 | `session_date` ليست في المستقبل | 422 |
| R-7 | الملف PDF حقيقي (`mime` + magic bytes)، الحجم ≤ الحد المعتمد (P-26) | 422 `INVALID_FILE` |
| R-8 | الملف يُحفظ باسم عشوائي على **private disk** | — |
| R-9 | لا تعديل/حذف على progress history بعد الحفظ (Trigger + لا endpoint) | 405/403 |
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

- `goal_progress_history.report_id` (nullable, `RESTRICT`) → لا يُحذف تقرير نهائيًا وله تاريخ تقدم؛
  الحذف العادي soft delete، والتاريخ يبقى مرتبطًا بالتقرير.
- `reports` لا تحتاج pivot مع `goals`؛ صفوف التقدم هي الرابط.
- `goals.progress_percentage` يبقى القيمة الحالية للقراءة السريعة/charts.
- لا يوجد Endpoint مباشر لتحديث `goals.progress_percentage` في v1؛
  `RecordGoalProgressAction` داخلي يُستدعى من رفع التقرير (D-037).

---

## 8. المواعيد — الخيارات وأثرها (P-15)

| الخيار | Schema | المزايا | العيوب | أثر التغيير لاحقًا |
|---|---|---|---|---|
| **A** — بلا أخصائي | لا عمود | مطابق للـ ERD، أقل افتراضات | لا يمكن إشعار/إسناد مَن سيقوم بالتقييم، ولا معرفة المسؤول | إضافة عمود nullable لاحقًا (سهل) |
| **B** — أخصائي اختياري *(مؤقت)* | `specialist_id uuid NULL` | يدعم الحالتين (معلوم/غير معلوم)، لا يفرض سيناريو | يحتاج قاعدة "متى يصبح إلزاميًا؟" | لا تغيير هيكلي |
| **C** — أخصائي إلزامي | `specialist_id NOT NULL` | مسؤولية واضحة | يمنع الحجز قبل تحديد الأخصائي، وقد يفرض إسنادًا خاطئًا | تحويل nullable → NOT NULL مؤلم لاحقًا |

**محسوم (D-053 — B+):** `specialist_id` يبقى nullable، والإسناد اختياري عند الحجز،
ويصبح **إلزاميًا قبل تأكيد/إكمال الموعد** (قاعدة تطبيقية). لا حالات/إلغاء/إعادة جدولة في V1.
**لا تُبنى أي حالة موعد أو إشعار جماعي قبل P-15/P-16.**

---

## 9. كود الطفل

### 9.1 التوليد (مؤكد — D-041)

```
child_code = first2(A-Z from child_name) . first2(A-Z from parent_name) . nextval(child_code_sequence)
```

- أسماء الأطفال **وولي الأمر** تُدخل **بالإنجليزية**.
- **لا Transliteration** من العربية، ولا خريطة تحويل.
- الرقم التسلسلي **Global ومستقل عن الحروف** (D-003)؛ لا يُشتق من الأسماء ولا يتأثر بها.
- `child_code varchar(20)` unique · `sequence_number bigint` unique.
- `CREATE SEQUENCE child_code_sequence START 1` — **لا MAX+1**.
- الفجوات مقبولة. الكود لا يُعاد استخدامه حتى بعد soft delete.

### 9.2 الخوارزمية الأساسية

1. `trim` ثم `Uppercase`.
2. أخذ أول **حرفين إنجليزيين `A-Z`** من اسم الطفل، وأول حرفين من اسم ولي الأمر.
3. دمج الحرفين + `nextval(child_code_sequence)`.

> مثال: `"Muhammad"` + `"Ali"` → `MUAL` + الرقم التسلسلي.
> التعامل مع الرموز/المسافات غير المعتادة يبقى ضمن الحالات الحدّية P-03.

### 9.3 الحالات الحدّية (P-03 — غير محسومة)

| الحالة | الافتراضي المقترح | ملاحظة |
|---|---|---|
| اسم أقل من حرفين إنجليزيين | رفض + fallback يدوي | لا يُخترع سلوك قبل P-03 |
| اسم يحتوي رموزًا/مسافات | تجاهل غير `A-Z` ثم أخذ أول حرفين | مقترح |
| اسم مختلط عربي/إنجليزي | غير محسوم | العميل أكد الإنجليزية |
| إدخال `initials` يدويًا | مقترح: نعم + audit | الصلاحية غير محددة |
| رسالة الخطأ | ممنوع إظهار الاسم | إلزامي |

**Fallback اليدوي (مقترح — P-03):** يسمح النظام لموظف مخوّل بإرسال
`child_initials` و`parent_initials` (حرفان `A-Z` لكل منهما) عند فشل التحقق،
مع تسجيل العملية في `audit_logs` **بدون** الاسم.
**الصلاحية المطلوبة للـ fallback غير محددة** (هل تكفي `children.create` أم صلاحية منفصلة؟)
ولا يُنفَّذ الـ endpoint قبل اعتماد P-03.

**مصدر اسم ولي الأمر:** إن كان الحساب موجودًا، يُستخدم `users.name` فقط إذا اعتُمد تخزينه (P-04)؛
وإلا يُرسَل `parent_name` في الطلب ويُستخدم لتوليد الكود ثم يُهمَل. لا يُفترض أي منهما.

### 9.4 الخصوصية

- `child_name` حقل Request فقط — **لا يوجد عمود له في أي جدول**.
- `parent_name` يُستخدم لتوليد الكود؛ مصدره وتخزينه معلّق P-04.
- مستثنيان من `$fillable` والـ audit والـ logs والـ exception payloads ورسائل أخطاء الـ Validation.
- ممنوع تسجيل Request payload كاملًا في أي log/queue/audit.
- اختبار إلزامي: بعد التسجيل، `SELECT` على كل الجداول + `audit_logs` + ملفات الـ log
  لا تحتوي اسم الطفل، ولا قيمة `parent_name` إن كانت request-only.

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

  create child
  create consents:
    accepted_at            = provided ?? now
    accepted_by_user_id    = NULL (قبول ورقي — D-050)
    recorded_by_user_id    = actor (الموظف/المستخدم الذي سجّل)
    consent_version        = النص المعتمد من المركز (D-050)
    consent_text_snapshot  = النص المعتمد من المركز (D-050؛ لا نص مُختلق)
```

- جميع الإقرارات تُنشأ **داخل نفس transaction** الخاصة بتسجيل الطفل.
- أي فشل → rollback كامل (لا طفل بدون إقرارات).
- `consent_types` الثلاثة تُحمى: لا deactivate ولا delete ولا CRUD إداري.
- **الفرض يشمل الأنواع المطلوبة النشطة فقط**؛ لا أنواع اختيارية في V1 (C0).
- **لا نص قانوني في الكود/الـ Seeder:** `description` مؤقت وصفي فقط، والنص الفعلي معتمد من المركز/القانوني.
- **لا سحب/تصحيح للإقرار في V1** (C0)؛ المنع تطبيقي، ولا Trigger على `child_consents`.

### 10.2 الاختبارات الإلزامية

1. طفل بدون consents → 422.
2. طفل بـ 2 من 3 → 422 مع تحديد الناقص.
3. طفل بـ 4 أنواع (منها غير مطلوب) → 422 **مؤقت** (P-12 يحدد سياسة الأنواع الاختيارية).
4. `consent_type_id` مكرر → 422.
5. `accepted_at` في المستقبل → 422.
6. نجاح → 3 صفوف بـ `accepted_at` غير null و`recorded_by_user_id` = المنفّذ.
7. قبول رقمي → `accepted_by_user_id` = ولي الأمر؛ قبول ورقي → `accepted_by_user_id = NULL`.
8. محاولة `PUT/DELETE` على consent → 405.
9. ولي أمر يحاول تسجيل إقرار لطفل غيره → 404.
10. لا نص قانوني مُختلق في `consent_text_snapshot` قبل P-12.

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
| 14 | `goal_progress_history` + Trigger `goal_progress_history_immutable` | 12, 13 |
| 15 | `homework`, `homework_comments` | 6 |
| 16 | `appointments` | 4, 5 |
| 17 | `notifications` | 2 |
| 18 | `audit_logs` + Trigger `audit_logs_immutable` | 2 |

> **بوابة:** لا يُنفَّذ أي migration قبل اعتماد الوثيقة (Gate A)، ثم تجهيز البيئة (Gate B)،
> ثم إنشاء Laravel، ثم اعتماد الـ Schema (Gate C). هذا الترتيب ملزم حتى لو بدت بعض الجداول مستقلة.

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
`children.age 0..120` · `children.child_code ~ '^[A-Z]{4}[0-9]+$'` ·
`functional_scale_scores.score 0..10` ·
`treatment_plans.end_date >= start_date` · `goals.progress_percentage 0..100` ·
`goal_progress_history.previous/new 0..100` · `reports.file_mime = 'application/pdf'` ·
`reports.file_size > 0` · `reinforcer_catalog.category IN (...)` ·
`roles.level 0..100` · `cases` assignment consistency.

### 12.3 NOT NULL الحاكمة
`users.role_id` · `users.password` · `children.parent_id` · `children.child_code` ·
`children.sequence_number` · `children.initial_problem` · `cases.child_id/branch_id` ·
`reports.case_id/specialist_id/session_date/file_*` · `child_consents.accepted_at` ·
`child_consents.recorded_by_user_id`.

### 12.4 Triggers الحاكمة
`audit_logs_immutable` (UPDATE/DELETE ممنوع) ·
`goal_progress_history_immutable` (UPDATE/DELETE ممنوع).
**لا Trigger على `child_consents` في V1** — C0 يمنع السحب/التصحيح تطبيقيًا (لا endpoints)، ولا يُضاف Trigger غير معتمد.

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
| child_consents | `(accepted_by_user_id)` | تتبع القبول الرقمي. |
| child_consents | `(recorded_by_user_id)` | مَن سجّل الإقرار. |
| cases | `(assigned_by_user_id)` | تتبع مَن أسند. |
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

## 14. سياسات الحذف (موحّدة)

> **القاعدة:** لا حذف نهائي لبيانات سريرية/قانونية. `soft delete` للسجلات التشغيلية،
> و`is_active = false` للـ lookups. `CASCADE` مسموح فقط للـ pivots والصفوف التابعة
> التي لا معنى لها بدون الأب (answers/scores/reinforcers)، وليس لسجلات مستقلة.

| الجدول | softDeletes | is_active | حذف نهائي | ملاحظة |
|---|---|---|---|---|
| `roles` | ❌ | ❌ | ❌ | بيانات نظام. |
| `permissions` | ❌ | ❌ | ❌ | بيانات نظام. |
| `role_permissions` | ❌ | ❌ | CASCADE مع الدور/الصلاحية | pivot. |
| `users` | ✅ | ✅ | ❌ | تعطيل/أرشفة؛ الحذف النهائي غير مدعوم. |
| `user_permissions` | ❌ | ❌ | CASCADE مع المستخدم/الصلاحية | pivot. |
| `parents` | ✅ | ❌ | ❌ | بروفايل ولي الأمر. |
| `specialists` | ✅ | ❌ | ❌ | بروفايل الأخصائي. |
| `branches` | ✅ | ✅ | ❌ إذا له Cases | partial unique. |
| `knowledge_sources` | ❌ | ✅ | ❌ إذا مستخدم | تعطيل فقط (D-24). |
| `children` | ✅ | ❌ | ❌ | سجل سريري. |
| `cases` | ✅ | ❌ | ❌ | سجل سريري. |
| `initial_assessments` | ❌ | ❌ | ❌ | تقييم واحد، immutable. |
| `assessment_questions` | ❌ | ✅ | ❌ | تعطيل فقط. |
| `assessment_answers` | ❌ | ❌ | CASCADE مع التقييم (تنظيف اختبارات فقط) | snapshot. |
| `functional_scales` | ❌ | ✅ | ❌ | تعطيل فقط. |
| `functional_scale_scores` | ❌ | ❌ | CASCADE مع التقييم | snapshot. |
| `reinforcer_catalog` | ❌ | ✅ | ❌ | تعطيل فقط. |
| `assessment_reinforcers` | ❌ | ❌ | CASCADE مع التقييم | snapshot. |
| `consent_types` | ❌ | ✅ | ❌ | Seed نظام؛ لا CRUD. |
| `child_consents` | ❌ | ❌ | ❌ | سجل قانوني؛ لا UPDATE/DELETE. |
| `treatment_plans` | ✅ | ❌ | ❌ | سجل تشغيلي. |
| `plan_specialists` | ❌ | ❌ | CASCADE مع الخطة/الأخصائي | pivot مؤكد (D-040). |
| `goals` | ✅ | ❌ | ❌ | سجل تشغيلي. |
| `goal_progress_history` | ❌ | ❌ | ❌ | Trigger يمنع UPDATE/DELETE. |
| `reports` | ✅ | ❌ | ❌ | الملف يبقى (P-26). |
| `homework` | ✅ | ❌ | ❌ | سجل تشغيلي. |
| `homework_comments` | ❌ | ❌ | CASCADE مع الواجب (حذف نهائي غير مستخدم) | append-only. |
| `appointments` | ✅ | ❌ | ❌ | سجل تشغيلي. |
| `notifications` | ❌ | ❌ | CASCADE مع المستخدم | بيانات غير سريرية. |
| `audit_logs` | ❌ | ❌ | ❌ | Trigger يمنع UPDATE/DELETE. |

> **ملاحظة:** `reports` soft delete لا يغيّر `goal_progress_history.report_id`.
> الحذف النهائي لتقرير له تاريخ تقدم ممنوع بـ `RESTRICT`؛ إن تطلب العميل حق المحو (P-20)
> فسيُصمم إجراء منفصل، وليس حذفًا عاديًا (D-042).

---

## 15. اختبارات القبول قبل التنفيذ (Schema Tests)

1. `migrate:fresh` + `db:seed` تنجح من الصفر.
2. `nextval` متوازٍ (50 طلب) → 50 رقمًا مختلفًا.
3. إدخال `score = 11` → DB exception.
4. إدخال `progress = -1` → DB exception.
5. `UPDATE audit_logs` / `DELETE audit_logs` → DB exception.
6. `UPDATE goal_progress_history` / `DELETE goal_progress_history` → DB exception.
7. `DELETE child_consents` → ممنوع تطبيقيًا (لا endpoint).
8. تعديل سؤال → الإجابة القديمة تحتفظ بالنص القديم.
9. تعديل اسم مقياس → الدرجة القديمة تحتفظ بالاسم القديم.
10. حذف تقرير soft → `goal_progress_history.report_id` يبقى كما هو.
11. حذف تقرير hard وله تاريخ تقدم → DB exception (`RESTRICT`).
12. `case.specialist_id = NULL` + محاولة إنشاء خطة → 422.
13. رفع غير PDF → 422، ولا يُحفظ أي ملف.
14. تقرير لهدف في Case آخر → 422.
15. طفل بدون الإقرارات الثلاثة → 422 + لا صفوف في `children`.
16. تسجيل طفل ثانٍ لنفس ولي الأمر → نفس `parent_id`، ولا `users`/`parents` جديد.
17. محاولة إنشاء ولي أمر مكرر بنفس identifier → 422.
18. `child_name` غير موجود في أي جدول/`audit_logs`/ملفات الـ log.
19. `child_code` لا يطابق `^[A-Z]{4}[0-9]+$` → DB exception.
20. إقرار رقمي: `accepted_by_user_id` = ولي الأمر، `recorded_by_user_id` = المنفّذ.
21. حذف مستخدم نهائيًا وله `audit_logs` → DB exception (`RESTRICT`).
22. تقرير `visible_to_parent = false` → 404 لولي الأمر.

---

## 16. خارج نطاق v4 (مؤكد)

`user_roles` · `payments` · `attachments` (عدا تقارير PDF) · `details jsonb` ·
`case_status_history` · `specialty` · `settings` ·
`password_reset_tokens` · قنوات الإشعارات · Form Builder · Consent revocation · Consent wording.

### 16.1 خارج نطاق V1 نهائيًا (D-048/D-049)

| البند | القرار | الأثر |
|---|---|---|
| `final_assessments` / «التقييم النهائي» (P-21) | **D-048 — خارج V1** | لا جدول/أعمدة/Endpoints/Business Logic. |
| `session_notes` / `sessions` / «ملاحظات الجلسة الأولى» (P-22) | **D-049 — خارج V1** | التقرير PDF هو سجل الجلسة الوحيد. |

> أي تنفيذ مستقبلي لأي منهما يتطلب ADR جديدًا وmigration جديدة.
> لا يُشتق أي منهما ضمنيًا من `initial_assessments` أو `reports`.

**معلّق وليس خارج النطاق:** Parent auth (P-01) · Staff auth identifier (P-32) ·
Parent account delivery (P-29) · Parent name storage (P-04) ·
Scale names (P-09) · Notifications (P-16) · Child-code edge cases (P-03) · Report hide role (P-07).

### 16.2 قرارات Gate C المطبقة على الـ Schema

| القرار | الأثر |
|---|---|
| **D-050** (P-12) | `child_consents.consent_version` و`consent_text_snapshot` → **NOT NULL**؛ لا قبول رقمي ولا سحب في V1. |
| **D-051** (P-20) | لا حذف نهائي/إمحاء؛ `RESTRICT` وTriggers كما هي. |
| **D-052** (P-31) | لا تغيير Schema؛ «هدف واحد على الأقل + نسبة لكل هدف» تحقق تطبيقي داخل transaction. |
| **D-053** (P-15) | لا تغيير Schema؛ `specialist_id` nullable، والإلزام قبل التأكيد/الإكمال تطبيقي. |
