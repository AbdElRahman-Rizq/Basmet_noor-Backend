# تقرير T-0.2 — إنشاء مشروع Laravel 13

**المشروع:** بصمة نور (Basmat Nour)
**التاريخ:** 2026-11-14
**المرحلة:** T-0.2 — إنشاء مشروع Laravel 13 داخل حاوية `app`
**الحالة:** ✅ **مكتمل** — `/up` = 200، واتصال PostgreSQL ناجح، و**صفر migrations / صفر جداول**.

---

## 1. الملخص

| البند | النتيجة |
|---|---|
| إصدار Laravel | **13.42.0** (سكيلتون v13.11.0 → قيود `^13.0`) |
| إصدار PHP | **8.4.27** (NTS) |
| Composer | 2.11.2 |
| اتصال قاعدة البيانات | ✅ `pgsql` عبر `DB_HOST=db` |
| `SELECT 1` عبر Laravel | ✅ `1` |
| قاعدة البيانات الحالية | `basma_noor` على PostgreSQL **17.12** |
| جداول `public` | **0** |
| `GET /up` | ✅ **HTTP 200** |
| `GET /` | ✅ HTTP 200 (صفحة Laravel الافتراضية) |
| بعد `docker compose restart` | ✅ الاتصال يعمل، `/up` = 200، الجداول = 0 |
| Migrations | ❌ لا يوجد أي ملف migration |
| Models / Controllers / Business Logic | ❌ لم يُنشأ أي منها |

---

## 2. خطوات التنفيذ

### 2.1 التحقق من المتطلبات المسبقة
- الحاويتان تعملان: `basma-noor-app-1` و`basma-noor-db-1` (healthy).
- تأكيد توفر Laravel 13 على Packagist: أحدث إصدار `v13.11.0` للسكيلتون، وقيود الحزمة `laravel/framework:^13.17`.
- `.env` يحتوي مسبقًا: `APP_KEY` صالح، `APP_ENV=local`، و`DB_CONNECTION=pgsql` مع `DB_HOST=db`.

### 2.2 إنشاء السكيلتون في مجلد مؤقت
```bash
docker compose exec -T app composer create-project "laravel/laravel:^13.0" /tmp/lv --no-interaction --no-install --prefer-dist
```
> `--no-install` يمنع تنزيل الاعتماديات في المجلد المؤقت (كان سيكرر العمل). سكربت `post-create-project-cmd`
> فشل بسبب غياب `vendor`، لكن ملفات المشروع أُنشئت بالكامل — وهو السلوك المتوقع.

### 2.3 نقل الملفات إلى مساحة العمل
```bash
cd /tmp/lv && tar cf - \
  --exclude=./.env --exclude=./.env.example --exclude=./.gitignore \
  --exclude=./README.md --exclude=./AGENTS.md --exclude=./CLAUDE.md \
  . | (cd /var/www/html && tar xf -)
```
استُثني `.env` و`.env.example` و`.gitignore` حتى لا تُستبدل إعدادات Docker/الأسرار الحالية.
كذلك استُثنيت ملفات README/AGENTS/CLAUDE الخاصة بالسكيلتون لأنها غير مرتبطة بالمشروع.

### 2.4 إزالة ملفات السكيلتون التي تخالف نطاق T-0.2
| الملف | السبب |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | **ممنوع إنشاء migrations قبل Gate C** — وكان سينشئ `users` متعارضًا مع Schema v4. |
| `database/migrations/0001_01_01_000001_create_cache_table.php` | نفس السبب. |
| `database/migrations/0001_01_01_000002_create_jobs_table.php` | نفس السبب. |
| `app/Models/User.php` | ممنوع إنشاء Models؛ وسيُعرَّف `User` لاحقًا حسب v4 (`role_id` وغيرها). |
| `database/factories/UserFactory.php` | يعتمد على `User` المحذوف. |
| `database/seeders/DatabaseSeeder.php` | يعتمد على `User` المحذوف؛ ولا Seeders قبل Gate C. |
| `app/Http/Controllers/Controller.php` | إزالة أبسط Controller افتراضي التزامًا بحدود T-0.2 (لا Controllers). |

> `config/auth.php` يحتفظ بـ `use App\Models\User;` وهذا **آمن**: `User::class` مجرد ثابت نصي
> ولا يتطلب وجود الصنف. سيُعاد إنشاء النموذج في Phase 2.

### 2.5 تعديل `composer.json`
- الاسم: `laravel/laravel` → **`basma-noor/backend`**.
- الوصف والكلمات المفتاحية.
- **حذف خطوات `artisan migrate`** من سكربتي `setup` و`post-create-project-cmd` لمنع إنشاء Schema بالخطأ قبل Gate C.
- `composer validate --no-check-publish` → **valid**.

### 2.6 حل مشكلة بطء bind mount (مشكلة واجهتنا)
أول محاولة `composer install` فشلت:
```
The process "'/usr/bin/unzip' ... " exceeded the timeout of 300 seconds.
```
السبب: استخراج آلاف الملفات مباشرة على bind mount من قرص Windows (`D:`) بطيء جدًا.

**الحل المطبَّق:** volume مُسمّى لـ `vendor` في `compose.yaml`:
```yaml
volumes:
  - .:/var/www/html
  - vendor:/var/www/html/vendor
...
volumes:
  pgdata:
  vendor:
```
ثم:
```bash
docker compose up -d
COMPOSER_PROCESS_TIMEOUT=0 composer install --no-interaction --prefer-dist
```
✅ نجح تثبيت **110 حزمة**.

> النتيجة: `vendor/` يعيش في volume `basma-noor_vendor` (أسرع وأكثر استقرارًا)،
> بينما يبقى كود المشروع على الـ bind mount. يظهر مجلد `vendor/` فارغًا على المضيف كنقطة تركيب فقط.

### 2.7 تشغيل الخادم
```bash
docker compose exec -d app sh -lc "cd /var/www/html && nohup php artisan serve --host=0.0.0.0 --port=8000 > /tmp/serve.log 2>&1"
```

---

## 3. نتائج الاختبارات

### 3.1 الإصدارات
```
Laravel Framework 13.42.0
PHP 8.4.27 (cli) (NTS)
Composer 2.11.2
```

### 3.2 اتصال قاعدة البيانات عبر Laravel (bootstrap حقيقي)
```
laravel=13.42.0
default=pgsql
host=db
database=basma_noor
select_1=1
current_db=basma_noor
server=PostgreSQL 17.12 on x86_64-pc-linux-musl
public_tables=0
```

### 3.3 HTTP
```
GET /up => 200
GET / => 200
```
`/up` مُعرَّف في `bootstrap/app.php` عبر `->withRouting(health: '/up')`.

### 3.4 إعادة التشغيل والـ Volumes
```bash
docker compose restart
```
```
basma-noor-app-1   Up (healthy app process)
basma-noor-db-1    Up (healthy)
GET /up after restart => 200
select_1=1
public_tables=0
```
Volumes:
```
basma-noor_pgdata   local
basma-noor_vendor   local
```
الـ mounts داخل الحاوية:
```
bind   D:\Programing\بصمة نور -> /var/www/html
volume basma-noor_vendor        -> /var/www/html/vendor
```
✅ البيانات والاعتماديات تستمر بعد إعادة التشغيل.

### 3.5 التحقق من `compose.yaml`
```
docker compose config --quiet  → OK
```

---

## 4. الملفات التي أُنشئت/عُدّلت

### أُنشئت (سكيلتون Laravel 13)
`artisan` · `composer.json` · `composer.lock` · `app/` (Providers/Bootstrap فقط) · `bootstrap/` ·
`config/` · `database/` (بدون migrations) · `public/` · `resources/` · `routes/` · `storage/` ·
`tests/` · `phpunit.xml` · `package.json` · `vite.config.js` · `.editorconfig` · `.gitattributes` · `.npmrc`

### عُدّلت
- `compose.yaml` — إضافة volume `vendor`.
- `docs/PROJECT_ROADMAP.md` — T-0.2 وT-0.3 → `[x]`.
- `docs/FINAL_REVIEW_SUMMARY.md` — تحديث حالة إنشاء Laravel.
- `docs/PROJECT_OVERVIEW.md` — توضيح أن Laravel أُنشئ بعد Gate A.
- `docs/DATABASE_DESIGN.md` / `docs/DECISIONS.md` / `docs/PENDING_REQUIREMENTS.md` — تعديلات Gate A السابقة (غير متعلقة بـ T-0.2).

---

## 5. الالتزام بحدود T-0.2

| القيد | الحالة |
|---|---|
| Migrations | ❌ **صفر ملفات** — حُذفت ملفات السكيلتون الثلاثة |
| تشغيل `artisan migrate` | ❌ لم يُنفَّذ |
| Models | ❌ لا يوجد أي Model (حُذف `User` الافتراضي) |
| Controllers | ❌ لا يوجد أي Controller |
| Routes / APIs جديدة | ❌ لم تُضف (فقط `routes/web.php` الافتراضي) |
| Business logic | ❌ لا توجد |
| تغييرات Schema | ❌ صفر — عدد الجداول **0** |
| Phase 1 | ❌ لم تبدأ |
| Seeders / Factories | ❌ لا توجد |
| الأسرار | ✅ `.env` غير متتبع؛ `composer.json` بلا أسرار |

---

## 6. ملاحظات ومشكلات

| # | الملاحظة | الحالة |
|---|---|---|
| 1 | فشل أول `composer install` بمهلة `unzip` على bind mount | ✅ حُلّت بـ volume `vendor` |
| 2 | `post-create-project-cmd` فشل في المجلد المؤقت (لا `vendor`) | ⚠️ متوقع وغير مؤثر؛ الملفات اكتملت |
| 3 | `intl` غير مثبّت | ⚠️ غير مطلوب لتشغيل Laravel أو `/up`؛ قد يلزم لبعض أوامر العرض لاحقًا |
| 4 | `vendor/` على المضيف مجلد فارغ | ℹ️ نقطة تركيب؛ المحتوى داخل volume `basma-noor_vendor` |
| 5 | ملفات السكيلتون `README.md`/`AGENTS.md`/`CLAUDE.md` لم تُنسخ | ℹ️ غير مرتبطة بالمشروع |
| 6 | `php artisan serve` يعمل detached داخل الحاوية | ℹ️ لإيقافه: `docker compose exec app pkill -f "artisan serve"` |

---

## 7. الخطوة التالية المقترحة

**T-0.4 — `routes/api.php` + health endpoint** (لا migrations، لا Sanctum):
- إنشاء `routes/api.php` وربطه في `bootstrap/app.php`.
- `GET /api/v1/health` يرجع JSON موحّدًا `{ "status": "ok", ... }` دون لمس قاعدة البيانات.
- (T-0.5 لاحقًا) تجهيز Pest و`basma_noor_test` بدون migrations.
- **Gate C يبقى مغلقًا:** لا migration قبل تأكيد البنود المؤثرة (P-12، P-20، P-31، P-15).
