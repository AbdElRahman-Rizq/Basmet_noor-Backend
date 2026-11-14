# تقرير Gate B — تجهيز البنية التحتية فقط

**المشروع:** بصمة نور (Basmat Nour)
**التاريخ:** 2026-11-13
**النطاق:** Gate B = Docker + PHP + PostgreSQL + التحقق. **لا Laravel، لا migrations، لا كود تطبيقي.**
**الحالة:** ✅ مكتمل. متوقف بانتظار موافقة صريحة على Gate A قبل إنشاء Laravel.

---

## 1. ملخص تغييرات الوثائق

| الملف | التغيير |
|---|---|
| `docs/DECISIONS.md` | تأكيد D-041 (أسماء الطفل **وولي الأمر** بالإنجليزية + الرقم التسلسلي Global مستقل). إضافة **D-045** (تعليق الأخصائي اختياري). تقوية D-033/D-044 (الهوية محظورة حتى التأكيد، `unique(mobile)` ليس اعتماد هوية). تصحيح D-039 (Gate B بلا Laravel). |
| `docs/DATABASE_DESIGN.md` | `specialist_comment` موثّق كـ **اختياري (D-045)**. §9 يوضح أن اسم ولي الأمر إنجليزي وأن التسلسل Global مستقل. تنبيه أن `unique(mobile)` قيد تقني لا مفتاح هوية. تصحيح ترتيب البوابات. |
| `docs/PENDING_REQUIREMENTS.md` | P-01 يضم أسئلة الهوية الصريحة (ما يعرّف ولي الأمر، كشف الحساب القائم، كفاية الجوال، تغيير الجوال). P-09 يوضح أن تعليق الأخصائي مؤكد اختياري. P-04 يوضح إدخال اسم ولي الأمر بالإنجليزية دون افتراض تخزينه. |
| `docs/PROJECT_OVERVIEW.md` | Gate B بنية تحتية فقط، وإنشاء Laravel بعد Gate A. إضافة مصطلح Specialist Comment. تحديث حالة Docker. |
| `docs/PROJECT_ROADMAP.md` | فصل Gate B عن إنشاء Laravel: T-0.1/T-0.1b فقط، وT-0.2→T-0.10 محظورة حتى Gate A. تحديث جدول البوابات وترتيب Phase 0/1. |
| `docs/FINAL_REVIEW_SUMMARY.md` | إضافة C-31 (تعليق اختياري)، تحديث C-27 (الطفل وولي الأمر)، تصحيح الخطوة التالية، وتوضيح أن اعتماد البنية التحتية لا يعني اعتماد الـ Schema. |

لم يُحذف أي سؤال قديم بلا مرجع، ولم يُحوَّل أي بند معلّق إلى قرار مؤكد.

---

## 2. القرارات المؤكدة التي أُدمجت

### 2.1 أسماء إنجليزية وكود الطفل (D-041)
- اسم الطفل **واسم ولي الأمر** يُدخلان بالإنجليزية.
- الكود = أول حرفين إنجليزيين من اسم الطفل + أول حرفين إنجليزيين من اسم ولي الأمر + رقم تسلسلي.
- الرقم التسلسلي **Global ومستقل عن الحروف** (D-003).
- لا Transliteration ولا خريطة تحويل عربية.
- لم تُضف افتراضات جديدة عن الرموز/الأسماء القصيرة/fallback؛ بقيت في P-03.
- **لم يُنفَّذ** أي مولد كود.

### 2.2 تعليق الأخصائي على المقاييس الوظيفية (D-045)
- `functional_scale_scores.specialist_comment` يبقى `text NULL`.
- **اختياري** ولا يُفرض في validation أو قيد قاعدة بيانات.
- لم يُغيّر P-09 (أسماء المقاييس وطبيعة الدرجة)؛ ما زال معلّقًا.
- **لم يُنفَّذ** أي endpoint أو validation.

---

## 3. هوية ولي الأمر — ما زالت معلّقة

مؤكد:
- حساب ولي أمر واحد يدير عدة أطفال (D-033).
- رقم الجوال وحده **ليس** مفتاح الهوية المعتمد (D-044).
- `unique(mobile)` في v4 قيد تقني لمنع تكرار بيانات الاتصال، **وليس اعتمادًا للهوية**.

غير محسوم (P-01/P-04/P-29/P-32):
- ما المعلومات التي تُعرِّف ولي الأمر بشكل فريد؟
- كيف يُكتشف الحساب القائم عند تسجيل طفل آخر؟
- هل الجوال كافٍ أم يلزم identifier آخر؟
- ماذا يحدث عند تغيير رقم الجوال؟

**النتيجة:** لا uniqueness منطقية ولا Find-or-Create ولا Parent Authentication ولا أي endpoint.

---

## 4. الملفات التي رُوجعت/عُدّلت

### وثائق
- `docs/DATABASE_DESIGN.md`
- `docs/DECISIONS.md`
- `docs/PENDING_REQUIREMENTS.md`
- `docs/PROJECT_OVERVIEW.md`
- `docs/PROJECT_ROADMAP.md`
- `docs/FINAL_REVIEW_SUMMARY.md`

### بنية تحتية
- `compose.yaml`
- `Dockerfile`
- `.env.example`
- `.gitignore`
- `.dockerignore`
- `Makefile`
- `.env` (محلي فقط، غير متتبع)

### Laravel
- **لا يوجد أي مشروع Laravel.** أُزيلت سقالة كانت قد أُنشئت في محاولة سابقة (قبل توجيه منع إنشاء Laravel)، ولم يبقَ لها أثر:
  `artisan` · `composer.json` · `composer.lock` · `app/` · `bootstrap/` · `config/` · `database/` · `public/` · `resources/` · `routes/` · `storage/` · `tests/` · `vendor/` · ملفات الجذر الخاصة بـ Laravel.

---

## 5. التحقق من Docker Compose

```powershell
docker compose config --quiet
```

**النتيجة:** `compose config: OK`

الخدمات:
- `app`: PHP 8.4 CLI + `pdo_pgsql` + Composer، تحميل `.env`، bind mount.
- `db`: `postgres:17-alpine`، healthcheck عبر `pg_isready`، volume `pgdata`.
- `DB_HOST=db` مضبوط في `.env` و`.env.example`.

---

## 6. بناء صورة PHP

```powershell
docker compose build app
```

**النتيجة:** ✅ `basma-noor-app:dev` (البناء استُخدم من الكاش بعد البناء الأول).
- الصورة تعتمد `php:8.4-cli`.
- تُثبّت `git` و`unzip` و`libpq-dev`.
- تُجمّع `pdo_pgsql`.
- تنسخ Composer 2 من صورة `composer:2`.

---

## 7. PHP والإضافات

```powershell
docker compose exec -T app php -v
docker compose exec -T app php -m
```

**PHP:** 8.4.27 (NTS)

الإضافات المتوفرة:
`Core, ctype, curl, date, dom, fileinfo, filter, hash, iconv, json, libxml, mbstring, mysqlnd, openssl, pcre, PDO, pdo_pgsql, pdo_sqlite, Phar, posix, random, readline, Reflection, session, SimpleXML, sodium, SPL, sqlite3, standard, tokenizer, xml, xmlreader, xmlwriter, Zend OPcache, zlib`

- **`pdo_pgsql`:** ✅ موجود.
- إضافات Laravel 13 المطلوبة (`ctype`, `filter`, `hash`, `mbstring`, `openssl`, `session`, `tokenizer`): ✅ موجودة.
- **`intl`:** غير مثبّت. غير مطلوب لتشغيل Laravel أو `/up`، لكنه مطلوب لبعض أوامر العرض مثل `php artisan db:show`. لم يُضف في Gate B.
- لم يُنشأ أي مشروع Laravel، لذا لم يُختبر `composer install` لمشروع Laravel؛ **Composer نفسه متاح** (انظر §8).

---

## 8. Composer

```powershell
docker compose exec -T app composer --version
```

**النتيجة:** Composer 2.11.2 — متاح داخل الحاوية.

> اختبار تثبيت اعتماديات Laravel **غير قابل للتنفيذ** في Gate B لأنه لا يوجد `composer.json` لمشروع Laravel، ولا يُسمح بإنشائه.

---

## 9. PostgreSQL والحالة

```powershell
docker compose ps
docker compose restart db
```

**النتيجة:**
- الحاوية: `basma-noor-db-1`
- الصورة: `postgres:17-alpine`
- الإصدار: **PostgreSQL 17.12**
- الحالة بعد إعادة التشغيل: `Up ... (healthy)`
- Healthcheck: `pg_isready -U $POSTGRES_USER -d $POSTGRES_DB`
- المدة: احتاجت ~30 ثانية بعد إعادة التشغيل حتى تصبح `healthy` (bind/disk بطيء).

---

## 10. اختبار اتصال قاعدة البيانات

بدون Laravel — عبر PDO مباشرة داخل حاوية `app`:

```text
host=db
db=basma_noor
SELECT 1 = 1
server = PostgreSQL 17.12 on x86_64-pc-linux-musl
public tables = 0
```

**النتيجة:** ✅ الاتصال ناجح، و**لا توجد أي جداول** في `public`.

---

## 11. التحقق من الـ Volume الدائم

```powershell
docker volume inspect basma-noor_pgdata
docker compose restart db
```

**النتيجة:**
- الـ volume: `basma-noor_pgdata`، driver: `local`
- Mountpoint: `/var/lib/docker/volumes/basma-noor_pgdata/_data`
- بعد إعادة تشغيل الـ db: `healthy`، و`SELECT 1` أعاد `1`، وعدد الجداول ما زال `0`.
- هذا يثبت أن قاعدة البيانات تعمل على volume دائم وليس ephemeral.

---

## 12. الأسرار وGit

- `.env` محلي فقط، ومتجاهَل: `git check-ignore .env` → `.gitignore:8:/.env`.
- `git ls-files .env` → **فارغ** (غير متتبع).
- `.env.example` يستخدم `DB_PASSWORD=change-me-in-local-env`، ولا يحتوي كلمة المرور الفعلية.
- `compose.yaml` يقرأ `DB_PASSWORD` من البيئة مع `:?` وليس مكتوبًا في الملف.
- `APP_KEY` في `.env` المحلي فقط إن وُجد؛ لا يُلتزم.
- لا توجد credentials في `Dockerfile` أو `compose.yaml` أو `.env.example`.

---

## 13. المشاكل والقيود

| # | البند | الحالة |
|---|---|---|
| 1 | `/up` | **غير قابل للاختبار** — يتطلب مشروع Laravel وخادم HTTP. لم يُدَّعَ نجاحه. |
| 2 | `composer install` لمشروع Laravel | **غير قابل للاختبار** — لا يوجد مشروع Laravel. |
| 3 | `intl` | غير مثبّت؛ غير مطلوب في Gate B. `db:show` لن يعمل حتى يُضاف. |
| 4 | أول بناء للصورة | بطيء جدًا بسبب الشبكة (~20 دقيقة)؛ نجح. |
| 5 | إعادة تشغيل PostgreSQL | استغرقت وقتًا حتى `healthy`؛ نجحت. |
| 6 | سقالة Laravel سابقة | أُنشئت في محاولة سابقة ثم **أُزيلت بالكامل** التزامًا بالنطاق الجديد. تظهر الآن كـ deletions في Git لأنها كانت ضمن `Init commit`. |
| 7 | عدد الجداول | 0 — لا migrations ولا جداول. |

### أمر خادم HTTP المطلوب بعد إنشاء Laravel
```powershell
docker compose exec app php artisan serve --host=0.0.0.0 --port=8000
# ثم: http://localhost:8000/up
```
هذا الأمر **لم يُنفَّذ** في Gate B لأنه لا يوجد Laravel.

---

## 14. تأكيد الالتزام بالنطاق

| القيد | الحالة النهائية |
|---|---|
| مشروع Laravel | ❌ غير موجود |
| `artisan` / `composer.json` لمشروع Laravel | ❌ غير موجود |
| Migrations | ❌ لا توجد |
| جداول قاعدة بيانات | ❌ 0 جداول |
| Models | ❌ لا توجد |
| Controllers | ❌ لا توجد |
| Routes / APIs | ❌ لا توجد |
| Authentication | ❌ لم تُنفَّذ |
| Roles / Permissions | ❌ لم تُنفَّذ |
| Seeders / Factories | ❌ لا توجد |
| Business logic | ❌ لا توجد |
| Phase 1 | ❌ لم تبدأ |
| Hardcoded secrets | ❌ لا توجد |

**الخلاصة:** Gate B يقتصر على البنية التحتية، وكل ما تم التحقق منه ناجح. لا يوجد Laravel ولا قاعدة بيانات مُهيّأة بجداول.

---

## 15. الخطوة التالية

**Gate A:** اعتماد `FINAL_REVIEW_SUMMARY.md` + `DATABASE_DESIGN.md` v4.

**بعد الموافقة فقط:** إنشاء مشروع Laravel 13 داخل الحاوية (T-0.2)، ثم التحقق من `/up` واتصال Laravel بقاعدة البيانات.

> اعتماد تجهيز البنية التحتية **لا يعني** اعتماد الـ Schema. لن أنشئ Laravel أو أي migration قبل موافقتك الصريحة.
