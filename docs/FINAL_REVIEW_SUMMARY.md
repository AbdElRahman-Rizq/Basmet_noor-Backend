# بصمة نور — ملخص المراجعة النهائية (Final Review Summary)

> **التاريخ:** 2026-11-14
> **النطاق:** مراجعة الوثائق الخمس + SRS + ERD قبل بدء التنفيذ.
> **الحالة:** ✅ **جاهز لاعتماد Gate A** — القرارات D-040/D-041/D-045 معتمدة نهائيًا، ولا تعارض بين الوثائق.
> **لم يُنشأ أي migration أو كود تطبيقي.** أُنشئ مشروع Laravel 13 بعد اعتماد Gate A (T-0.2).

---

## 1. القرارات المؤكدة (Confirmed)

| # | القرار | المرجع |
|---|---|---|
| C-1 | Laravel 13.x + PHP 8.4 + PostgreSQL 17 عبر Docker Compose. | D-001 |
| C-2 | UUID (v7) لكل المفاتيح الأساسية. | D-002 |
| C-3 | دور أساسي واحد لكل مستخدم عبر `users.role_id`؛ لا `user_roles`. | D-023 |
| C-4 | SUPER_ADMIN ينشئ حسابات CO_ADMIN، ويملك تجاوزًا كاملًا. | §3 Overview |
| C-5 | CO_ADMIN ينشئ/يعدّل حسابات SPECIALIST فقط، ولا ينشئ أي حساب إداري. | D-030 / I-3 |
| C-6 | SUPER_ADMIN يضبط صلاحيات كل CO_ADMIN فرديًا عبر `user_permissions`. | D-005 / D-030 |
| C-7 | SPECIALIST يرى فقط الـ Cases المسندة إليه. | D-020 |
| C-8 | PARENT يرى فقط أطفاله؛ 404 عند عدم الملكية. | D-019 |
| C-9 | RECEPTION معرّف في `roles` لكنه **بلا أي صلاحية** حتى P-02. | D-030 / I-11 |
| C-10 | الطفل يمكن أن يكون له عدة Cases، وكل Case لفرع واحد وأخصائي مسند واحد. | §4-D |
| C-11 | Case يمكن أن يكون له عدة Treatment Plans، وكل Goal لخطة واحدة وأخصائي مسؤول واحد. | §4-H |
| C-12 | `cases.specialist_id` nullable، ولا عمل علاجي قبل الإسناد. | D-024 |
| C-13 | التقرير = ملف PDF مرفوع؛ الأخصائي لا يكتب محتواه داخل النظام. | D-025 |
| C-14 | أثناء رفع التقرير يختار الأخصائي الأهداف التي عمل عليها ويسجل نسبة التقدم. | D-025 |
| C-15 | كل تحديث تقدم يحفظ `previous_percentage` و`new_percentage` ولا يُعاد كتابته. | D-027 |
| C-16 | التقرير يمكن إخفاؤه عن ولي الأمر فرديًا عبر `visible_to_parent`؛ ويبقى متاحًا للإدارة. | D-025 / D-043 |
| C-17 | ملفات التقارير على private disk فقط، والتنزيل عبر Policy. | D-026 |
| C-18 | لا كيان `sessions`؛ التقرير هو سجل الجلسة. | D-009 |
| C-19 | الإقرارات الثلاثة مطلوبة وتُفرض داخل transaction تسجيل الطفل. | D-028 |
| C-20 | كود الطفل: أول حرفين إنجليزيين من اسم الطفل + أول حرفين إنجليزيين من اسم ولي الأمر + رقم تسلسلي global. | D-003 / D-041 |
| C-21 | لا يُخزَّن اسم الطفل الحقيقي في أي جدول/log/audit. | D-007 |
| C-22 | Audit logs غير قابلة للتعديل/الحذف (Trigger + لا endpoints). | D-006 |
| C-23 | لا مالية ولا رسوم في النظام. | D-018 |
| C-24 | لا مرفقات هوية؛ التحقق Boolean فقط. | D-022 |
| C-25 | حساب ولي أمر واحد يدير عدة أطفال؛ لا يُكرر عند تسجيل طفل جديد. | D-033 / D-044 |
| C-26 | `plan_specialists` جدول مؤكد؛ الخطة قد يكون لها أكثر من أخصائي مسؤول. | D-040 |
| C-27 | أسماء الطفل وولي الأمر تُدخل بالإنجليزية؛ لا Transliteration. الرقم التسلسلي global ومستقل عن الحروف. | D-041 / D-003 |
| C-28 | لا حذف نهائي ولا anonymization في v1؛ تُحفظ السجلات التاريخية وP-20 معلّق. | D-042 |
| C-29 | التقرير المخفي عن ولي الأمر يبقى مرئيًا للإدارة؛ الأخصائي لا يملك الإخفاء افتراضيًا. | D-043 |
| C-30 | طريقة دخول ولي الأمر ومفتاح Find-or-Create غير محسومين؛ رقم الجوال وحده ليس المفتاح المعتمد، و`unique(mobile)` قيد تقني لا اعتماد هوية. | D-044 |
| C-31 | تعليق الأخصائي بجانب درجة المقياس الوظيفي اختياري، ولا يُفرض في validation أو قاعدة البيانات. | D-045 |

---

## 2. القرارات غير المحسومة (تحتاج تأكيد العميل)

| # | السؤال | الأثر | الحالة |
|---|---|---|---|
| P-01 | طريقة دخول ولي الأمر (child_code+mobile / mobile+password / email+password / OTP). | يحظر ParentAuthController واختبارات العزل. | ⛔ |
| P-32 | طريقة دخول Staff/Specialist: email أم mobile؟ (تعارض SRS مع نقاش سابق). | يحظر Staff login. | ⛔ |
| P-02 | دور RECEPTION وصلاحياته. | يمنع تفعيل الدور أو إنشاء حسابه. | ⛔ |
| P-03 | الحالات الحدّية للأكواد + صلاحية fallback اليدوي. | يؤثر على ChildCodeGenerator والاختبارات. | ⚠️ |
| P-04 | هل يُخزَّن اسم ولي الأمر؟ age أم DOB؟ | يؤثر على Find-or-Create وaudit. | ⛔ |
| P-07 | استبدال/حذف التقرير، الصيغ، تعدد الملفات، مَن يخفي التقرير. | يؤثر على Report workflow. | ⚠️ |
| P-12 | مَن يقبل/يسجّل الإقرار، النص القانوني، النسخة، السحب/التصحيح. | يؤثر على `child_consents` وvalidation. | ⛔ |
| P-13 | مَن يملك إسناد الحالة؟ وإعادة الإسناد؟ | يؤثر على `cases.assign_specialist`. | ⛔ |
| P-15 | المواعيد: أخصائي؟ حالات؟ إلغاء/إعادة جدولة؟ | يؤثر على `appointments`. | ⚠️ |
| P-16 | الإشعارات: القنوات، المستقبلون، الإنشاء التلقائي. | يؤثر على notifications. | ⚠️ |
| P-19 | تعديل بيانات الطفل/نقل الوصاية. | يؤثر على endpoints التعديل. | ⚠️ |
| P-20 | حق المحو النهائي / anonymization. | يؤثر على سياسة الحذف؛ لا سلوك تدميري قبل القرار. | ⛔ |
| P-21 | معنى "التقييم النهائي". | يؤثر على بوابة ولي الأمر. | ⚠️ |
| P-22 | ملاحظات الجلسة الأولى داخل النظام أم داخل PDF؟ | تعارض محتمل مع SRS §4. | ⚠️ |
| P-26 | حجم/retention/فيروسات/تنزيل ملف التقرير. | يؤثر على التخزين. | ⚠️ |
| P-27 | هل يُسمح بتخفيض نسبة التقدم؟ | يؤثر على validation. | ⚠️ |
| P-29 | إنشاء حساب ولي الأمر وتسليم بيانات الدخول. | يؤثر على تدفق التسجيل. | ⛔ |
| P-31 | عدد الأهداف المطلوب في التقرير + تحديث تقدم بلا تقرير. | يؤثر على `UploadReportAction`. | ⚠️ |

> **ملاحظة:** P-28 (استبدال التقرير) دُمج في P-07. **P-14 أُغلق** بتأكيد `plan_specialists` (D-040).
> القرارات **D-040 / D-041 / D-045** معتمدة نهائيًا ولا تُعاد مناقشتها؛ سجلها في
> `PENDING_REQUIREMENTS.md §0`. كل سؤال لا يزال محفوظًا، ولم يُحذف أي سؤال بلا مرجع.

---

## 3. التصحيحات التي تمت (Corrections)

| # | التصحيح | الملف |
|---|---|---|
| 1 | إزالة الفرض الضمني لـ email للـ Staff وmobile للـ Parent؛ المصادقة أصبحت معلّقة P-01/P-32. | DATABASE_DESIGN, DECISIONS, PENDING, OVERVIEW, ROADMAP |
| 2 | إضافة قاعدة Find-or-Create ومنع تكرار حساب ولي الأمر (D-033). | DATABASE_DESIGN, DECISIONS, OVERVIEW, ROADMAP |
| 3 | تأكيد `plan_specialists` كجدول مشاركي الخطة، والسماح بأكثر من أخصائي مسؤول (D-040). | DATABASE_DESIGN, DECISIONS, PENDING, ROADMAP |
| 4 | فصل `accepted_by_user_id` (nullable) عن `recorded_by_user_id` (NOT NULL)، وإضافة `consent_version`/`consent_text_snapshot` nullable (D-035). | DATABASE_DESIGN, DECISIONS, PENDING, ROADMAP |
| 5 | تصحيح `audit_logs.user_id` و`cases.assigned_by_user_id` و`goal_progress_history.report_id` من `SET NULL` إلى `RESTRICT` لحل التعارض مع Triggers/CHECK (D-036). | DATABASE_DESIGN, DECISIONS |
| 6 | إضافة Trigger `goal_progress_history_immutable` ومنع UPDATE/DELETE (D-036/D-037). | DATABASE_DESIGN, DECISIONS, ROADMAP |
| 7 | توحيد مصفوفة الحذف: `parents`/`specialists` soft delete، `knowledge_sources` is_active فقط، والجداول التاريخية بلا حذف (D-038). | DATABASE_DESIGN, DECISIONS |
| 8 | اعتماد الأسماء الإنجليزية وإلغاء Transliteration: الكود = أول حرفين إنجليزيين من كل اسم + sequence (D-041). | DATABASE_DESIGN, DECISIONS, PENDING, ROADMAP |
| 9 | إزالة `consents.record` من صلاحيات PARENT الافتراضية، و`cases.assign_specialist` من CO_ADMIN الافتراضية، حتى P-12/P-13. | DATABASE_DESIGN |
| 10 | تصحيح مراجع `Q-01`/`Q-21` إلى `P-01`/`P-21`. | PROJECT_OVERVIEW |
| 11 | تحويل "سحب الإقرارات غير مسموح" من قرار مؤكد إلى سؤال قانوني معلّق P-12. | PROJECT_OVERVIEW |
| 12 | إضافة بوابات اعتماد صريحة A/B/C/D وفصل الوثائق عن البيئة عن الـ migrations عن الـ Backend (D-039). | DECISIONS, ROADMAP, OVERVIEW |
| 13 | تقسيم المهام الكبيرة (T-1.23، T-3.6، T-6.1، T-8.2) لمهام 10–20 دقيقة. | ROADMAP |
| 14 | تعليم المهام المعتمدة على PENDING بـ `[!]` وإضافة عمود Blocked by. | ROADMAP |
| 15 | إزالة التكرار بين P-07/P-26/P-27/P-28/P-31 مع الإبقاء على كل سؤال. | PENDING |
| 16 | توضيح أن `plan_specialists` لا يمنح وصولًا للـ Case، وأن عدد أهداف التقرير غير محسوم. | DATABASE_DESIGN, PENDING, ROADMAP |
| 17 | تأكيد أن الحذف النهائي/anonymization غير معتمدين، مع الحفاظ على السجلات (D-042). | DECISIONS, PENDING, DATABASE_DESIGN |
| 18 | توضيح أن التقرير المخفي يبقى متاحًا للإدارة، وأن الأخصائي لا يملك الإخفاء افتراضيًا (D-043). | DATABASE_DESIGN, DECISIONS, PENDING, ROADMAP |
| 19 | توضيح أن رقم الجوال وحده ليس مفتاح هوية ولي الأمر، ومنع تسجيل/مصادقة ولي الأمر الآن (D-044). | DATABASE_DESIGN, DECISIONS, PENDING, ROADMAP |
| 20 | تأكيد أن تعليق الأخصائي على درجة المقياس الوظيفي اختياري (D-045). | DATABASE_DESIGN, DECISIONS, PENDING, OVERVIEW |
| 21 | تقييد Gate B بالبنية التحتية فقط: لا Laravel ولا migrations؛ إنشاء Laravel بعد Gate A. | ROADMAP, OVERVIEW, GATE_B_REPORT |

---

## 4. المخاطر المعمارية المتبقية (Remaining Risks)

| # | الخطر | التخفيف |
|---|---|---|
| R-1 | **المصادقة غير محسومة** قد تفرض تغيير الـ Auth flow لاحقًا. | Schema تستوعب email/mobile/password؛ لا Auth endpoints قبل P-01/P-32. |
| R-2 | **اسم ولي الأمر** قد يُمنع تخزينه، فيتغير تدفق توليد الكود. | `users.name` موجود لكن الاستخدام لولي الأمر معلّق P-04؛ fallback request-only موثق. |
| R-3 | **`plan_specialists`** مؤكد، لكن مسائل الوصول/الفرض على أخصائي الهدف لم تُحسم. | لا يمنح وصولًا للـ Case، ولا Enforcement على `goals.specialist_id` في v1 (D-040). |
| R-4 | **نص الإقرارات القانوني** غير موجود. | حقول النسخة/النص nullable؛ لا نص مُختلق. |
| R-5 | **عدد أهداف التقرير** غير محسوم؛ قد يتغير validation. | لا قاعدة مثبتة؛ ربط الأهداف محظور P-31. |
| R-6 | **P-22** قد يتطلب حقول ملاحظات جلسة داخل النظام (تعارض SRS). | لا `sessions` ولا `session_notes` قبل القرار. |
| R-7 | **RECEPTION** قد يحتاج صلاحيات تسجيل/مواعيد. | الدور بلا صلاحيات؛ البنية جاهزة للإضافة بعد P-02. |
| R-8 | **حق المحو P-20** قد يتعارض مع `RESTRICT` وimmutability. | أي محو يتطلب إجراء anonymization منفصل، لا حذف عادي. |
| R-9 | **إخفاء التقرير** قد يُسند لدور غير SUPER_ADMIN. | `reports.hide_from_parent` موجودة؛ الافتراضي SUPER_ADMIN فقط حتى P-07، والمخفي يبقى مرئيًا للإدارة (D-043). |
| R-10 | **الاسم في رسائل الأخطاء/الـ logs** قد يتسرب رغم استثناء الحقول. | اختبار إلزامي يفحص DB + audit + logs + validation payloads. |

---

## 5. هل تصميم قاعدة البيانات جاهز للاعتماد؟

**نعم، كأساس Schema v4** — بشرط فهم أن الاعتماد لا يعني بدء التنفيذ:

- البنية تستوعب كل الخيارات المعلّقة (email/mobile، اسم ولي الأمر، consent version، plan specialists، appointment specialist، notification channels) دون تغيير هيكلي كبير.
- لا توجد migrations أو كود تطبيقي، ولم يُنشأ مشروع Laravel.
- **لا يُنفَّذ أي feature مرتبط بـ PENDING** حتى قراره؛ المهام معلّمة `[!]` في Roadmap.

**لكن:** الاعتماد النهائي للـ migrations (Gate C) يتطلب تأكيد أن لا migration يعتمد على قرار غير محسوم.
الـ Schema الحالية مصممة لتجنب ذلك، لكن قرارات مثل P-12 قد تضيف/تحذف أعمدة/جداول قبل Gate C.

---

## 6. الخطوة التنفيذية التالية بعد الاعتماد (Exact Next Step)

**بعد موافقتك الصريحة فقط:**

1. **Gate A (معتمد):** `FINAL_REVIEW_SUMMARY.md` + `DATABASE_DESIGN.md` v4.
2. **Gate B (مكتمل — بنية تحتية فقط):** `compose.yaml` + `Dockerfile` + التحقق من PHP 8.4/pdo_pgsql،
   PostgreSQL 17، Healthcheck، Volume، واتصال PDO — **بلا Laravel وبلا جداول**.
3. **T-0.2 (مكتمل):** مشروع Laravel 13 داخل الـ container؛ `/up` = 200؛ `SELECT 1` ناجح — **بلا migrations**.
4. **لا migrations، لا models، لا endpoints** قبل Gate C.

> **حدود المرحلة:** اعتماد الوثائق لا يعني اعتماد الـ Schema. لا migration قبل Gate C.
