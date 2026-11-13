# تقرير Gate B — تجهيز البيئة فقط

**المشروع:** بصمة نور (Basmat Nour)
**التاريخ:** 2026-11-13
**النطاق:** Gate B فقط — Docker + Laravel 13 + التحقق من الاتصال. **لا** migrations ولا Models ولا Controllers ولا Auth ولا RBAC ولا أي كود أعمال.
**الحالة:** ✅ مكتمل ومُتحقَّق منه. متوقف بانتظار موافقة صريحة قبل Gate C.

---

## 1. ما تم إنجازه باختصار

| البند | النتيجة |
|---|---|
| تحديث الوثائق بالتوضيحات الخمسة | ✅ D-040 … D-044 |
| T-0.1 بيئة Docker | ✅ `app` + `db` يعملان |
| PHP + `pdo_pgsql` | ✅ PHP 8.4.27 |
| PostgreSQL 17 + Healthcheck + Volume | ✅ 17.12 و`healthy` |
| اتصال PDO من PHP إلى PostgreSQL | ✅ ناجح |
| اتصال Laravel بقاعدة البيانات | ✅ `pgsql` |
| T-0.2 مشروع Laravel 13 | ✅ Framework 13.42.0 |
| Composer install | ✅ ناجح |
| `/up` | ✅ HTTP 200 |
| أسرار خارج Git | ✅ `.env` متجاهَل و`.env.example` بلا كلمة مرور |
| migrations/models/controllers/business code | ✅ لا يوجد أي منها |

---

## 2. تحديث الوثائق (قبل Gate B)

أُضيفت قرارات جديدة دون حذف التاريخ:

| القرار | الملخص | الملفات المتأثرة |
|---|---|---|
| **D-040** | `plan_specialists` **مؤكد** كجدول مشاركي الخطة، ويسمح بأكثر من أخصائي مسؤول. لا يمنح وصولًا تلقائيًا للـ Case، ولا يُفرض على `goals.specialist_id` في v1. أُغلق P-14. | `DECISIONS.md`, `DATABASE_DESIGN.md`, `PENDING_REQUIREMENTS.md`, `PROJECT_OVERVIEW.md`, `PROJECT_ROADMAP.md`, `FINAL_REVIEW_SUMMARY.md` |
| **D-041** | أسماء الأطفال تُدخل **بالإنجليزية**. الكود = أول حرفين `A-Z` من اسم الطفل + أول حرفين من اسم ولي الأمر + `nextval(sequence)`. **لا Transliteration**. الحالات الحدّية تبقى P-03. | نفس الملفات + §9 في `DATABASE_DESIGN.md` |
| **D-042** | لا حذف نهائي ولا anonymization في v1. تُحفظ السجلات التاريخية. P-20 يبقى معلّقًا. | `DECISIONS.md`, `PENDING_REQUIREMENTS.md`, `DATABASE_DESIGN.md`, `FINAL_REVIEW_SUMMARY.md` |
| **D-043** | التقرير المخفي عن ولي الأمر يبقى مرئيًا للإدارة عبر `reports.view_all`. الأخصائي لا يملك `reports.hide_from_parent` افتراضيًا. الدور الدقيق P-07. | `DECISIONS.md`, `DATABASE_DESIGN.md`, `PROJECT_ROADMAP.md`, `FINAL_REVIEW_SUMMARY.md` |
| **D-044** | حساب ولي أمر واحد يدير عدة أطفال (مؤكد)، لكن طريقة الدخول ومفتاح Find-or-Create غير محسومين (P-01). **رقم الجوال وحده ليس المفتاح المعتمد**. لا تسجيل/مصادقة لولي الأمر. | نفس الملفات |

**ملاحظات:**
- لم يُحذف أي سؤال قديم بلا مرجع؛ D-031 وD-034 وD-032 بقيت في السجل كسياق تاريخي مع إشارة إلى القرار الأحدث.
- لم يُغيَّر أي Schema بسبب هذه التوضيحات.
- `DATABASE_DESIGN.md` بقي **v4**؛ التغييرات توضيحية فقط.

---

## 3. الملفات المنشأة/المعدلة

### 3.1 ملفات Docker والبيئة

| الملف | الوصف |
|---|---|
| `Dockerfile` | `php:8.4-cli` + `git` + `unzip` + `libpq-dev` + `pdo_pgsql` + Composer 2 |
| `compose.yaml` | خدمتا `app` و`db`، volume دائم، healthcheck، تحميل `.env` |
| `.dockerignore` | استبعاد `.env` و`vendor` و`node_modules` … |
| `.env.example` | قالب Laravel 13 كامل معدّل لـ PostgreSQL، بكلمة مرور placeholder |
| `.env` | محلي فقط، **متجاهَل بواسطة Git**، يحتوي كلمة مرور مولّدة و`APP_KEY` |
| `Makefile` | اختصارات `up/down/build/shell/composer/artisan/test/logs` |
| `.gitignore` | محدّث: إضافة `/database/*.sqlite` |

### 3.2 مشروع Laravel 13 (هيكل قياسي)

تم إنشاء المشروع داخل الحاوية إلى `/tmp/laravel` ثم دمجه في جذر المستودع، مع **عدم** استبدال ملفات الوثائق أو Docker أو `.env`:

```
app/                  (Providers فقط)
artisan
bootstrap/
config/
database/             (migrations فارغة + seeders)
public/
resources/
routes/               (web.php وconsole.php الافتراضيان)
storage/
tests/
composer.json
composer.lock
package.json
phpunit.xml
vite.config.js
.editorconfig
.gitattributes
.npmrc
README.md
```

### 3.3 ملفات Laravel الافتراضية التي أُزيلت

لالتزام حرفي بقيود Gate B:

| الملف | السبب |
|---|---|
| `app/Models/User.php` | ممنوع إنشاء Models |
| `app/Http/Controllers/Controller.php` | ممنوع إنشاء Controllers |
| `database/migrations/0001_01_01_*.php` (3 ملفات) | ممنوع إنشاء migrations |
| `database/factories/UserFactory.php` | بيانات/موديل مستخدم |
| `database/seeders/DatabaseSeeder.php` | أُعيدت كتابته ليصبح **no-op** بلا أي بيانات |

**النتيجة:** `app/` يحتوي فقط `Providers/AppServiceProvider.php`، و`database/migrations` فارغ، و`database/seeders` يحتوي Seeder فارغًا.

---

## 4. خدمات Docker والإصدارات

| العنصر | الإصدار / القيمة |
|---|---|
| Docker Engine | 28.4.0 |
| Docker Compose | v2.39.4-desktop.1 |
| صورة التطبيق | `basma-noor-app:dev` (مبنية محليًا) |
| PHP | **8.4.27** (NTS) |
| Composer | **2.11.2** |
| Laravel Framework | **13.42.0** |
| Laravel Skeleton | `laravel/laravel` **v13.11.0** |
| PostgreSQL | **17.12** (`postgres:17-alpine`) |
| الشبكة | `basma-noor_default` |
| Volume | `basma-noor_pgdata` |
| المنفذ | `8000 → 8000` |
| حالة `db` | `healthy` |
| قاعدة البيانات | `basma_noor` |
| المستخدم | `basma_noor` |

### compose.yaml (الملخص)

- `app`: بناء من `Dockerfile`، `env_file: .env`، bind mount للمشروع، يعتمد على `db` بحالة `service_healthy`.
- `db`: `postgres:17-alpine`، volume `pgdata`، healthcheck عبر `pg_isready` كل 5 ثوانٍ.
- كلمة المرور تُقرأ من `.env` مع `:?` لإلزام وجودها — **لا كلمة مرور في الملف الملتزم**.

---

## 5. أوامر التحقق المستخدمة والنتائج

### 5.1 التحقق من Compose والبناء

```powershell
docker compose config --quiet          # OK
docker compose build app               # built basma-noor-app:dev
docker compose up -d db                # db healthy
docker compose up -d app               # app up
docker compose ps                      # app Up, db Up (healthy)
```

### 5.2 PHP والإضافات وComposer

```powershell
docker compose run --rm --entrypoint sh app -lc "php -v; php -m | grep -i pdo; composer --version"
```

**النتيجة:**
- PHP 8.4.27
- `PDO`
- `pdo_pgsql` ✅
- `pdo_sqlite`
- Composer 2.11.2

> `intl` غير مثبّت. لم يكن مطلوبًا في Gate B، لكنه مطلوب لأمر `php artisan db:show` فقط (انظر §7).

### 5.3 اتصال PDO المباشر

```powershell
$code | docker compose exec -T app php
```

**النتيجة:**
```
PDO PostgreSQL OK: PostgreSQL 17.12 on x86_64-pc-linux-musl
```

### 5.4 اتصال Laravel بقاعدة البيانات

```powershell
$code | docker compose exec -T app php artisan tinker
```

**النتيجة:**
```
pgsql | PostgreSQL 17.12 on x86_64-pc-linux-musl
```

### 5.5 Laravel وComposer

```powershell
docker compose exec -T app php artisan --version
# Laravel Framework 13.42.0

docker compose exec -T app composer validate --no-check-publish
# ./composer.json is valid

docker compose exec -T app composer install --no-interaction --no-progress
# Nothing to install, update or remove — ناجح
```

### 5.6 المسارات

```powershell
docker compose exec -T app php artisan route:list
```

النتيجة: 4 مسارات افتراضية فقط:
- `GET /`
- `GET|PUT storage/{path}`
- `GET up` (HealthCheck)

لا توجد أي مسارات أعمال أو Auth.

### 5.7 التحقق من `/up`

```powershell
docker compose exec -T app php artisan serve --host=0.0.0.0 --port=8000
Invoke-WebRequest http://localhost:8000/up
```

**النتيجة:** `HTTP 200` (تم التحقق مرتين، قبل وبعد إزالة ملفات Laravel الافتراضية).

### 5.8 التحقق من نظافة قاعدة البيانات

```sql
select count(*) from pg_tables where schemaname = 'public';
```

**النتيجة:** `0` جدول.

---

## 6. الأسرار وGit

- `.env` موجود محليًا فقط، ويحتوي:
  - كلمة مرور PostgreSQL مولّدة عشوائيًا (32 حرفًا).
  - `APP_KEY` مولّد بواسطة `php artisan key:generate`.
- تم التحقق: `git check-ignore -v .env` → `.gitignore:8:/.env`.
- `.env.example` يحتوي `DB_PASSWORD=change-me-in-local-env` فقط.
- `composer.lock` ملتزم (مطلوب لتثبيت الإصدارات).
- **لم يُنشأ أي commit**؛ كل التغييرات ما زالت غير ملتزمة في `git status`.

---

## 7. المشاكل التي واجهتها وكيف حُلّت

| # | المشكلة | الحل |
|---|---|---|
| 1 | شبكة تنزيل Docker بطيئة جدًا (صورة PHP ~118MB، ثم حزم Debian استغرقت ~16 دقيقة). | تشغيل البناء في الخلفية وانتظار الاكتمال. |
| 2 | أول أمر نسخ من `/tmp/laravel` انتهت مهلته واستمر يعمل، فأعاد ملفات افتراضية بعد حذفها. | التحقق من عدم وجود عمليات `tar` متبقية، ثم إعادة الحذف والتحقق النهائي. |
| 3 | **مثبّت Laravel نفّذ migrations الافتراضية على PostgreSQL** لأن متغيرات بيئة الحاوية (`DB_CONNECTION=pgsql`) تغلبت على `.env` المؤقت في `/tmp/laravel`، فظهرت 9 جداول. | `php artisan db:wipe --force` ثم التحقق: **0 جداول**. |
| 4 | `php artisan db:show` يفشل بسبب غياب إضافية `intl`. | لم يكن الأمر مطلوبًا؛ تم التحقق من الاتصال عبر PDO وTinker. `intl` غير مطلوب لتشغيل Laravel أو `/up`. |
| 5 | أول طلب إلى `artisan serve` على Windows bind mount بطيء (عدة ثوانٍ). | إعادة المحاولة بعد بدء الخادم؛ `/up` رجع 200. |

**لا يوجد أثر متبقٍ لهذه المشاكل:** قاعدة البيانات نظيفة، والملفات المخالفة محذوفة، والتحقق النهائي ناجح.

---

## 8. تأكيد الالتزام بالقيود

| القيد | الحالة |
|---|---|
| عدم إنشاء migrations | ✅ `database/migrations` فارغ؛ الحذف شمل migrations الافتراضية |
| عدم إنشاء/تعديل جداول | ✅ 0 جداول في `public` |
| عدم إنشاء Eloquent Models | ✅ `app/Models` غير موجود |
| عدم إنشاء Controllers/endpoints | ✅ `app/Http/Controllers` غير موجود؛ المسارات افتراضية فقط |
| عدم تنفيذ Auth | ✅ لا تسجيل/دخول/مصادقة |
| عدم تنفيذ Roles/Permissions | ✅ لا صلاحيات ولا RBAC |
| عدم تنفيذ Business modules | ✅ لا شيء |
| عدم Seed بيانات أعمال | ✅ `DatabaseSeeder` فارغ |
| عدم بدء Phase 1 | ✅ لم يبدأ |
| عدم تعديل Schema المعتمد | ✅ لا تغيير؛ التوضيحات وثائقية فقط |
| عدم وضع أسرار في Git | ✅ `.env` متجاهَل |

---

## 9. الحالة الحالية للمشروع

**تم:**
- Gate A aftermath: الوثائق محدّثة ومتسقة.
- Gate B: البيئة كاملة وتعمل.
- Laravel 13 جاهز كهيكل فارغ.
- PostgreSQL يعمل مع volume دائم.
- الاتصال والأمان الأساسي (secrets) مضبوطان.

**لم يتم (بانتظار الموافقة):**
- Gate C: أي migration أو جدول أو Seeder أعمال.
- Gate D: Models/Controllers/Auth/RBAC/endpoints.
- Phase 1 أو أي منطق أعمال.
- Pest (لم يُثبّت؛ Laravel 13 جاء مع PHPUnit افتراضيًا).
- إضافية `intl` (اختيارية، مطلوبة فقط لبعض أوامر العرض مثل `db:show`).
- commit للتغييرات.

---

## 10. الخطوة التالية المقترحة (بانتظار موافقة صريحة)

**Gate C — T-1.1:** إنشاء migration لـ:

- `roles` (مع `level` وCHECK 0..100)
- `permissions`
- `role_permissions`

وفق ترتيب `DATABASE_DESIGN.md §11`، ثم التحقق بـ `migrate:fresh` واختبارات الـ schema.

**لن أبدأ أي migration قبل موافقتك الصريحة.**

---

## 11. أوامر يومية سريعة

```powershell
make up            # docker compose up -d --build
make down          # docker compose down
make shell         # bash داخل الحاوية
make artisan ARGS="about"
make composer ARGS="install"
make test          # php artisan test
make logs          # متابعة السجلات
```

أو مباشرة:

```powershell
docker compose exec app php artisan <command>
docker compose exec app composer <command>
```
