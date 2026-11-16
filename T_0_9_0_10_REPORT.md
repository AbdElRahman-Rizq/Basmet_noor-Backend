# تقرير T-0.9 + T-0.10 — Developer Tooling & Private File Storage

**المشروع:** بصمة نور (Basmat Nour)
**التاريخ:** 2026-11-16
**النطاق:** استكمال Makefile + تأمين التخزين الخاص للتقارير.
**الحالة:** ✅ **مكتمل** — 26 اختبارًا ناجحًا / 96 assertion، 0 migrations، 0 جداول، ولا Controllers/Models جديدة.

---

## 1. ملخص التنفيذ

| البند | النتيجة |
|---|---|
| Makefile | ✅ كل الأوامر المطلوبة تعمل من داخل حاوية `app` |
| `make fresh` | ⛔ محمي برسالة واضحة وينتهي بخطأ مقصود قبل لمس قاعدة البيانات |
| Docker Compose | ✅ لم يُعدَّل · `down` بلا `-v` · volumes باقية |
| Disk `private_reports` | ✅ محلي/خاص داخل `storage/app/private/reports` |
| URL عام للملفات | ❌ لا يوجد · الطلب المباشر = 403/404 |
| اختبارات التخزين | ✅ 6 اختبارات / 22 assertion |
| اختبارات Pest كاملة | ✅ **26 نجحت / 96 assertion** |
| Migrations / Models / Controllers | ❌ صفر |
| جداول `basma_noor` و`basma_noor_test` | ✅ **0 / 0** |

---

## 2. أوامر Makefile النهائية

```make
.PHONY: up down build shell composer artisan test logs fresh

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build app

shell:
	docker compose exec app bash

composer:
	docker compose exec app composer $(ARGS)

artisan:
	docker compose exec app php artisan $(ARGS)

test:
	docker compose exec app php artisan test

logs:
	docker compose logs -f

# Database rebuilds stay disabled until Gate C (schema/migrations) is approved.
fresh:
	@echo ERROR: 'make fresh' is disabled.
	@echo Gate C has not been approved, so database migrations are not allowed in Phase 0.
	@echo This target will be enabled after the Schema v4 migration plan is approved.
	@exit 1
```

### أمثلة استخدام

```bash
make up
make down
make shell
make composer ARGS="install"
make artisan ARGS="route:list"
make test
make logs
make fresh   # ممنوع وينتهي برسالة واضحة
```

### نتائج اختبار الأوامر

| الأمر | النتيجة | التفصيل |
|---|---|---|
| `make up` | ✅ exit 0 | أُعيد إنشاء الحاويات وتشغيلها |
| `make down` | ✅ exit 0 | إيقاف/إزالة الحاويات والشبكة فقط — **بلا حذف volumes** |
| `make build` | ✅ exit 0 | `basma-noor-app:dev` بُني من الكاش |
| `make shell` | ✅ exit 0 | فتح bash ونُفِّذ PHP داخله → 8.4.27 |
| `make composer ARGS=--version` | ✅ exit 0 | Composer 2.11.2 |
| `make artisan ARGS=--version` | ✅ exit 0 | Laravel Framework 13.42.0 |
| `make test` | ✅ exit 0 | 26 اختبارًا / 96 assertion |
| `make logs` | ✅ يعمل | بثّ السجلات مباشرة (أُوقف بعد 6 ثوانٍ في الاختبار) |
| `make fresh` | ⛔ exit 2 (مقصود) | طبع 3 أسطر توضح أن Gate C لم يُعتمد، ولم ينفّذ أي أمر DB |

> **ملاحظة بيئية (Windows):** GNU Make 4.4.1 (ezwinports) لم يتمكن من اكتشاف `Makefile` تلقائيًا عندما يكون مسار المشروع
> غير ASCII (المسار العربي الحالي)، رغم أن الملف صالح. تم التحقق من كل الأوامر عبر junction بمسار ASCII،
> كما يعمل `make -f Makefile <target>` مباشرة. هذه مشكلة أداة Make على Windows وليست مشكلة في محتوى الملف؛
> على Linux/macOS أو مسار ASCII يعمل `make <target>` طبيعيًا.

---

## 3. الملفات المنشأة والمعدلة

### منشأة

| الملف | الوصف |
|---|---|
| `tests/Feature/Storage/PrivateReportsStorageTest.php` | 6 اختبارات للتخزين الخاص وعدم الوصول العام |
| `T_0_9_0_10_REPORT.md` | هذا التقرير |

### معدّلة

| الملف | التغيير |
|---|---|
| `Makefile` | إضافة `fresh` المحمي + تحديث `.PHONY` (بقية الأوامر سليمة) |
| `config/filesystems.php` | تعريف disk `private_reports` |
| `docs/PROJECT_ROADMAP.md` | T-0.9 وT-0.10 → `[x]` وتحديث تاريخ الحالة |

**لم يُنشأ:** Migration · Model · Controller · Middleware · Endpoint · Business Logic · Auth/Sanctum.
**لم يُعدَّل:** `compose.yaml` · `Dockerfile` · `DATABASE_DESIGN.md`.

---

## 4. إعداد Disk `private_reports`

```php
'private_reports' => [
    'driver' => 'local',
    'root' => storage_path('app/private/reports'),
    'serve' => false,
    'visibility' => 'private',
    'throw' => true,
    'report' => false,
],
```

| الخاصية | القيمة | السبب |
|---|---|---|
| `driver` | `local` | تخزين داخل الخادم، لا خدمة سحابية |
| `root` | `storage/app/private/reports` | المسار المعتمد، خارج `public/` |
| `visibility` | `private` | صلاحيات ملفات خاصة (ظهرت 700 على المجلد) |
| `serve` | `false` | عدم تسجيل/استخدام مسار خدمة لهذا الـ disk |
| `throw` | `true` | فشل الكتابة يظهر كخطأ بدل الفشل الصامت |
| `url` | غير معرّف | لا يوجد رابط عام مقصود للـ disk |
| `storage:link` | غير مضاف | لا symlink في `public/storage` لهذه الملفات |

**المجلد:** يُنشأ تلقائيًا عند أول كتابة عبر `Storage`، فلا حاجة لملف `.gitkeep`.
**الصلاحيات:** `storage/app/private/reports` = `700 root:root` داخل الحاوية.
**التسمية:** لا يوجد أي كود تسمية بعد؛ الاختبارات تستخدم UUID فقط، وأي توليد تقارير مستقبلي يجب أن يلتزم بأسماء غير دالة على الأطفال/الأولياء.

---

## 5. اختبارات Private Storage ونتائجها

الملف: `tests/Feature/Storage/PrivateReportsStorageTest.php`

| الاختبار | النتيجة |
|---|---|
| كتابة ملف وقراءته من `private_reports` | ✅ |
| حذف الملف والتأكد من اختفائه | ✅ |
| الـ disk محلي/خاص وخارج `public/` ولا يملك `url` | ✅ |
| أسماء الملفات UUID بلا بيانات أطفال/أولياء | ✅ |
| عدم إمكانية الوصول عبر URL عام | ✅ |
| تنفيذ الاختبارات على `basma_noor_test` فقط | ✅ |

### تفاصيل اختبار الوصول العام

- تمت كتابة ملف فعلي ثم طلب `/storage/reports/{uuid}` بدون توقيع → **403**، والمحتوى السري لم يظهر.
- `/storage/{uuid}` → **403**.
- `/reports/{uuid}` (مسار عام مباشر) → **404**.
- لا يوجد symlink في `public/storage` يشير إلى `storage/app/private` أو `storage/app/private/reports`.

> **ملاحظة دقيقة:** `FilesystemAdapter::url()` في Laravel يعيد مسارًا شكليًا `/storage/{path}` لأي disk محلي،
> لكنه لا يُخدم فعليًا بدون توقيع صالح؛ الاختبار يثبت أن الطلب المباشر لا يعيد 200 ولا يكشف المحتوى.
> و`private_reports` نفسه معرّف بـ `serve => false` وبلا `url`.

---

## 6. نتائج اختبارات Docker

| الفحص | النتيجة |
|---|---|
| `docker compose config --quiet` | ✅ OK |
| `docker compose down` ثم `make up` | ✅ الحاويات عادت، وvolumes `basma-noor_pgdata` و`basma-noor_vendor` باقية |
| `docker compose restart` | ✅ التطبيق وDB يعودان تلقائيًا |
| `/up` | ✅ 200 |
| `/api/v1/health` | ✅ 200 · `application/json` |
| `/docs/api` | ✅ 200 · HTML |
| `/docs/api.json` | ✅ 200 · JSON |
| `SELECT 1` عبر Laravel | ✅ `1` · `basma_noor` |
| جداول `basma_noor` | ✅ **0** |
| جداول `basma_noor_test` | ✅ **0** |
| سجلات أخطاء بعد restart | ✅ لا أخطاء |

---

## 7. نتائج Pest بالكامل

```
Tests: 26 passed (96 assertions)
Duration: ~11.6s
```

| المجموعة | الاختبارات |
|---|---|
| `DocumentationTest` | 7 |
| `ErrorHandlingTest` | 6 |
| `HealthTest` | 2 |
| `PrivateReportsStorageTest` | 6 |
| `ResponseEnvelopeTest` | 3 |
| `TestingEnvironmentTest` | 1 |
| `WebTest` | 1 |

---

## 8. المشكلات وحلولها

| # | المشكلة | الحل |
|---|---|---|
| 1 | Docker Desktop كان متوقفًا عند بداية المهمة | شُغِّل وانتُظر جاهزية engine (28.4.0) |
| 2 | `make` غير مثبت على Windows Host | ثُبِّت GNU Make 4.4.1 (`ezwinports.make`) كأداة تحقق، وليس اعتمادية مشروع |
| 3 | Make لا يكتشف `Makefile` تلقائيًا مع المسار العربي على Windows | تم التحقق عبر junction بمسار ASCII، و`make -f Makefile` يعمل دائمًا؛ موثّق كقيد أداة |
| 4 | `Storage::url()` يعيد `/storage/{path}` حتى بدون `url` | ليس رابطًا عامًا فعليًا؛ الاختبار يثبت 403/404 ولا يكشف المحتوى |
| 5 | تنظيف ملفات الاختبار | `try/finally` + حذف صريح داخل الاختبارات؛ المجلد بقي فارغًا |
| 6 | اختبار وجود `public/storage` كان سيفشل عند تشغيل `storage:link` لاحقًا | استُبدل بفحص أن الرابط — إن وُجد — لا يشير إلى المسار الخاص |

---

## 9. ملاحظات أمنية

1. **الملفات خارج Public Web Root:** الجذر `storage/app/private/reports` وليس `public/`.
2. **لا Symlink:** لم يُضف الـ disk إلى `config/filesystems.links`، ولم يُشغَّل `storage:link`.
3. **لا Serving Route:** `serve => false` و`url` غير معرّف، والطلب المباشر يرجّع 403/404.
4. **صلاحيات خاصة:** المجلد `700`، والـ visibility `private`.
5. **بلا PII في المسارات:** الاختبارات تستخدم UUID؛ يجب إبقاء هذا القيد عند بناء مولّد التقارير مستقبلًا.
6. **بلا Endpoint:** لا Controller ولا Route جديد، فسطح الهجوم لم يزد.
7. **قاعدة الاختبار معزولة:** الاختبارات تعمل على `basma_noor_test` فقط.
8. **`make fresh` لا يلمس DB:** لا `migrate` ولا `migrate:fresh` ولا `db:wipe`.

---

## 10. المهام المؤجلة وأسبابها

| البند | السبب |
|---|---|
| التنفيذ الحقيقي لـ `make fresh` | يحتاج migrations المعتمدة في Gate C — ممنوع الآن |
| Gate C / Migrations | ينتظر حسم **P-12 · P-20 · P-31 · P-15** |
| Gate D / Auth / Business Logic | ينتظر **P-01 · P-04 · P-29 · P-32**، **P-02 · P-07 · P-03** |
| Phase 1 | لم تبدأ، وممنوع البدء قبل Gate C |

---

## 11. الخلاصة

اكتملت البنية التحتية لـ Phase 0: Laravel 13، Pest، معايير الاستجابة، Scramble، Makefile، والتخزين الخاص.
لم تُنشأ أي Migrations أو Models أو Controllers، وما زالت القاعدتان فارغتين تمامًا.

**الخطوة التالية المقترحة:** جلسة **Gate C Readiness** لحسم P-12/P-20/P-31/P-15 قبل كتابة أول Migration.

**متوقف الآن — لن تبدأ Gate C أو أي Task أخرى تلقائيًا.**
