# بصمة نور — سجل القرارات المعمارية (ADR)

> كل قرار هنا مُبرَّر ومربوط بمتطلب. أي تغيير لاحق يتم بإضافة إدخال جديد، وليس بتعديل القديم.

---

## D-001 — Laravel 13.x + PHP 8.4 + PostgreSQL 17

**السياق:** لا يوجد PHP/PostgreSQL مثبّتان على جهاز التطوير، وDocker Desktop مثبّت لكنه متوقف.

**القرار:** Laravel 13.x (آخر إصدار مستقر، يتطلب PHP ^8.3) مع PHP 8.4 وPostgreSQL 17،
والتشغيل عبر Docker Compose.

**البدائل المرفوضة:**
- Laravel 12: إصدار أقدم بدون سبب.
- SQLite للتطوير: لا يدعم `SEQUENCE` ولا `jsonb` ولا `CHECK` بنفس السلوك → اختبارات غير موثوقة.

**العواقب:** يجب تشغيل Docker Desktop قبل أي task. جميع الأوامر تُنفَّذ داخل container.

---

## D-002 — UUID (v7) كمفتاح أساسي لكل الجداول

**السياق:** الـ ERD يعرّف `id uuid`، والبيانات حساسة.

**القرار:** `HasUuids` في Eloquent (UUIDv7 مرتب زمنيًا في Laravel 13) + نوع `uuid` في PostgreSQL.

**المبرر:** لا يكشف عدد السجلات (مثل auto-increment)، ويسمح بتوليد الـ id قبل الحفظ،
وUUIDv7 يحافظ على أداء الـ B-tree indexes مقارنة بـ UUIDv4.

**العواقب:** الـ URLs والمفاتيح الخارجية uuid؛ ممنوع قبول id غير صالح بدون `uuid` validation rule.

---

## D-003 — توليد `child_code` عبر PostgreSQL SEQUENCE

**السياق:** مطلوب رقم تسلسلي global ومقاوم للتزامن، وممنوع `MAX+1`.

**القرار:** `CREATE SEQUENCE child_code_sequence` + `nextval()` داخل transaction،
ثم بناء الكود: `mb_substr(childInitials,0,2) . mb_substr(parentInitials,0,2) . nextval`.

**المبرر:** `nextval` ذرّي ومضمون في PostgreSQL، وأسرع من أي جدول counters مع locks.

**العواقب:**
- فجوات في الترقيم عند rollback — مقبولة ولا تُعاد.
- الـ Sequence لا يمكن أن يعيد رقمًا، لذا `unique(sequence_number)` هو حماية إضافية فقط.
- الـ Sequence لا يُنشأ بالـ `Blueprint` → migration يستخدم `DB::statement`.

---

## D-004 — Snapshot لنصوص الأسئلة والمقاييس والمعززات

**السياق:** الأسئلة والمقاييس قابلة للتعديل بواسطة الإدارة، لكن الإجابات التاريخية يجب ألا تتغير.

**القرار:** تخزين `*_snapshot` داخل صفوف الإجابة/الدرجة/المعزز لحظة الحفظ
(نص السؤال، القسم، النوع، اسم المقياس، اسم المعزز، فئته).

**المبرر:** يلبي §4-E و§4-F بدون تعقيد نسخ (versioning) كامل للأسئلة.

**العواقب:** عند تعديل سؤال، الإجابات القديمة تظل مفهومة؛ السؤال الجديد لا يُطبَّق بأثر رجعي.

---

## D-005 — صلاحيات فردية لـ CO_ADMIN عبر `user_permissions`

**السياق:** المتطلب يمنع افتراض تساوي صلاحيات حسابات CO_ADMIN.

**القرار:** `role_permissions` تعطي الصلاحيات الافتراضية للدور، و`user_permissions`
(`is_granted` true/false) تمنح/تسحب **لمستخدم بعينه**. الأولوية: user override > role.

**البدائل المرفوضة:** إنشاء Role لكل CO_ADMIN (تلوث جدول الأدوار وتعقيد إدارة)،
أعمدة boolean في `users` لكل صلاحية (غير قابلة للتوسع).

**العواقب:** كل فحص صلاحية يمر عبر `Gate` واحد موحّد؛ ممنوع فحص `role ===` مباشرة في الكود.

---

## D-006 — Audit Logs غير قابلة للتعديل على مستوى قاعدة البيانات

**السياق:** المتطلب: "must not be editable or deletable through ordinary application functionality."

**القرار:**
1. جدول `audit_logs` بدون `updated_at` وبدون soft delete.
2. Trigger `BEFORE UPDATE OR DELETE` يرفض العملية.
3. لا يوجد أي Route/Controller للتعديل أو الحذف.
4. Redaction list إلزامية: `password`, `password_confirmation`, `token`, `remember_token`,
   وأي حقل اسم طفل (`child_name`, `parent_name` المستخدم في توليد الكود).

**المبرر:** حماية من التلاعب حتى لو حدث خطأ برمجي أو وصول مباشر للتطبيق.

**العواقب:** أي حاجة لتصحيح سجل تكون بإدخال سجل جديد (compensating entry)، وليس بتعديل القديم.

---

## D-007 — لا نُخزّن اسم الطفل نهائيًا

**السياق:** الاسم مطلوب فقط لتوليد أول حرفين.

**القرار:** `child_name` حقل request-only (غير موجود في `$fillable` ولا في أي جدول).
يُستخدم داخل `ChildCodeGenerator` ثم يُهمَل. يُستثنى من audit/logs.

**العواقب:** لا يمكن البحث بالاسم (بالتصميم)؛ البحث بالكود أو بيانات ولي الأمر.

---

## D-008 — التقارير: أعمدة أساسية + `details jsonb` مؤقتًا

**السياق:** قالب التقرير النهائي غير مُسلَّم من العميل، ومطلوب عدم اختراع النموذج.

**القرار:** أعمدة مؤكدة فقط الآن (`case_id`, `specialist_id`, `session_date`,
`session_notes`, `visible_to_parent`) + `details jsonb` nullable.
عند وصول القالب: توثيق الحقول في `docs/REPORTS_SCHEMA.md`، وإضافة `report_template_version`.

**المبرر:** يسمح بتسجيل التقارير من اليوم الأول دون قفل التصميم، وبدون بناء Form Builder.

**العواقب:** الـ validation على `details` يكون عبر schema class عند وصول القالب؛
حتى ذلك الحين `details` يبقى فارغًا في الإنتاج (مسموح `null` فقط في الاختبارات).

---

## D-009 — لا كيان `sessions`

**القرار:** التقرير هو سجل الجلسة. لا جدول `sessions` ولا `session_id` في أي مكان.
**المرجع:** §4-J + قرار العميل.

---

## D-010 — المواعيد للتقييم المبدئي فقط

**القرار:** `appointments` بلا `specialist_id` وبلا أنواع جلسات وبلا تكرار (recurrence).
أي توسعة تحتاج تأكيد العميل (P-15).

**العواقب:** لا يوجد جدولة لجلسات العلاج؛ التقارير تُنشأ بعد الجلسة مباشرة.

---

## D-011 — إشعارات in-app فقط الآن

**القرار:** جدول `notifications` + endpoints للقراءة/التعليم كمقروء.
لا Email/SMS/WhatsApp/Push، ولا Jobs، ولا Queues.

**المبرر:** القنوات والمستقبلون غير نهائيين (§4-M). البنية تسمح بإضافة `channel` وJob لاحقًا.

**العواقب:** `sent_at` يبقى null؛ لا توجد وعود تسليم للمستخدم.

---

## D-012 — Pest للاختبارات + PostgreSQL حقيقي

**القرار:** Pest 3 على قاعدة بيانات PostgreSQL منفصلة (`basma_noor_test`)،
مع `RefreshDatabase`.

**المبرر:** الاختبارات تغطي `SEQUENCE`, `jsonb`, `CHECK constraints`, وpartial indexes —
وهي سلوكيات لا يمكن اختبارها بثقة على SQLite.

**العواقب:** تشغيل الاختبارات يحتاج الـ DB container. اختبار التزامن يستخدم
عمليات متوازية (`paratest`) أو `pg_sleep`/locks لمحاكاة race.

---

## D-013 — Scramble لتوليد OpenAPI

**القرار:** `dedoc/scramble` يولّد OpenAPI 3.1 من Routes + FormRequests + API Resources،
مع `/docs/api` UI.

**البدائل المرفوضة:** l5-swagger مع annotations يدوية (تعارض مستمر مع الكود)،
ملف YAML يدوي (ينحرف بسرعة).

**العواقب:** أي endpoint جديد يجب أن يكون له FormRequest وResource — وهو ما نريده أصلًا.

---

## D-014 — تنظيم بالـ Modules داخل Laravel القياسي

**القرار:** `app/Modules/{Module}/Models|Http|Actions|Policies|Data`.
لا Repository Pattern، ولا Services عامة ضخمة.

**المبرر:** المتطلب يمنع overengineering، ويطلب modules واضحة.

**العواقب:** الحل عبر Actions صغيرة (class واحد + `execute()`)، والاستعلامات في Models/Scopes.

---

## D-015 — كل الأوقات UTC والتواريخ Business-local

**القرار:** `APP_TIMEZONE=UTC`، `timestamptz` لكل الأوقات، والعرض بتوقيت `Africa/Cairo`
في طبقة الـ Resource. الحقول `date` (مثل `session_date`) تبقى `date` بدون timezone.

**المبرر:** تجنّب أخطاء التحويل في التخزين، مع دعم التوقيت الصيفي المصري عند العرض.

---

## D-016 — Soft Delete مع قيود Unique

**القرار:**
- `children.child_code` و`sequence_number`: unique **global** حتى مع soft delete (الكود لا يُعاد استخدامه أبدًا).
- `branches.name`: partial unique `WHERE deleted_at IS NULL`.
- `initial_assessments`, `assessment_answers`, `child_consents`, `goal_progress_history`,
  `audit_logs`, `homework_comments`: **بدون soft delete** (سجلات تاريخية).

**المبرر:** يمنع إعادة استخدام كود طفل (خطر خلط سجلات) ويسمح بإعادة استخدام اسم فرع محذوف.

---

## D-017 — ممنوع Enum في PostgreSQL

**القرار:** كل الحالات/الأنواع `varchar` + `CHECK` محدود حيث القيم نهائية،
و`varchar` بدون CHECK حيث القيم غير نهائية (Case status, plan status, appointment status).
القيم النهائية تُدار في PHP `Enum` + `config` عند تأكيدها.

**المبرر:** تعديل PG enum مكلف؛ والقيم غير نهائية أصلًا.

---

## D-018 — لا مالية ولا رسوم

**القرار:** لا جداول ولا حقول ولا endpoints مالية. لا يوجد "حالة سداد".
**المرجع:** قرار العميل الصريح (يتجاوز SRS §2).

---

## D-019 — ولاية الوالد تُفحص في Policy + Query Scope

**القرار:** `ChildPolicy::view` = `$child->parent->user_id === $user->id`،
وكل استعلام Parent يبدأ من `Child::whereHas('parent', fn($q) => $q->where('user_id', $user->id))`.
الـ 404 يُستخدم بدل 403 عند عدم الملكية لمنع enumeration.

**المبرر:** حماية IDOR على مستوى الاستعلام وليس الفلترة بعد الجلب.

---

## D-020 — الأخصائي يرى فقط Cases المسندة إليه

**القرار:** Global Scope اختياري غير مستخدم (خطر نسيان `withoutGlobalScope`)؛
بدلًا منه `CasePolicy` + `Case::visibleTo($user)` scope يُستخدم صراحة في كل Controller.
`Gate::before` لـ SUPER_ADMIN فقط.

**المبرر:** الأمان الصريح أوضح من scope خفي، ويسهل اختباره.

---

## D-021 — ملفات الـ docs هي مصدر الحقيقة الوحيد

**القرار:** أي feature غير موجودة في `PROJECT_OVERVIEW.md` + `DATABASE_DESIGN.md`
لا تُنفَّذ. أي سؤال مفتوح يُسجَّل في `PENDING_REQUIREMENTS.md` قبل الكود.

**العواقب:** عند تغيير requirement: تحديث docs أولًا ثم migration ثم كود ثم tests.

---

## D-022 — الصور/المرفقات ممنوعة في هذه المرحلة

**القرار:** لا `uploaded_files` ولا `attachments`. التحقق من الهوية Boolean.
**المبرر:** المتطلب صريح (Offline identity check)، وأي رفع يحتاج سياسة تخزين/فيروسات/خصوصية غير محددة.
