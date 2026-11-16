# تقرير T-0.7 — API Documentation باستخدام Scramble

**المشروع:** بصمة نور (Basmat Nour)
**التاريخ:** 2026-11-14
**النطاق:** توثيق API حقيقي من المسارات الحالية + واجهة `/docs/api` + OpenAPI JSON + قيود وصول للتوثيق.
**الحالة:** ✅ **مكتمل** — 20 اختبارًا ناجحًا / 74 assertion، OpenAPI صالح، ولا migrations/models/controllers.

---

## 1. Dependency والإصدارات

| الحزمة | الإصدار | النوع |
|---|---|---|
| `dedoc/scramble` | **v0.13.53** | `require-dev` |
| PHP | 8.4.27 | — |
| Laravel | 13.42.0 | — |
| Pest | 4.7.8 | — |
| PHPUnit | 12.5.33 | — |

### التوافق
`dedoc/scramble v0.13.53` يعلن صراحة:
- `php ^8.1` → يعمل على PHP 8.4.27.
- `illuminate/contracts ^10.0|^11.0|^12.0|^13.0` → يدعم Laravel 13 رسميًا.
- `pestphp/pest-plugin-laravel ^4.1` ضمن اعتمادياته التطويرية → متوافق مع Pest 4 المستخدم.

لم تُضف أي حزمة يدويًا غير Scramble. الحزم التالية جاءت **انتقاليًا** معها:
`myclabs/deep-copy`، `nikic/php-parser`، `phpstan/phpdoc-parser`، `spatie/laravel-package-tools`.

**Scramble اعتمادية تطوير فقط**، لذلك `composer install --no-dev` في الإنتاج لا يثبتها إطلاقًا.

---

## 2. الملفات المنشأة والمعدلة

### منشأة

| الملف | الوصف |
|---|---|
| `config/scramble.php` | إعداد Scramble المنشور، مع تكييفه لبيئة الإنتاج |
| `tests/Feature/Api/DocumentationTest.php` | 7 اختبارات للتوثيق والوصول والأمان |
| `T_0_7_REPORT.md` | هذا التقرير |

### معدّلة

| الملف | التغيير |
|---|---|
| `app/Providers/AppServiceProvider.php` | تقييد الوصول للتوثيق + إثراء OpenAPI (أمثلة، ErrorEnvelope، metadata) |
| `composer.json` / `composer.lock` | إضافة `dedoc/scramble:^0.13` إلى `require-dev` |
| `.gitignore` | تجاهل `/api.json` الناتج عن `scramble:export` |
| `docs/PROJECT_ROADMAP.md` | T-0.7 → `[x]` |

**لم يُنشأ:** أي Migration أو Model أو Controller أو Middleware أو Business Logic أو Authentication/Sanctum.

---

## 3. إعداد Scramble

### المسارات المفعّلة

| المسار | الوصف | HTTP |
|---|---|---|
| `/docs/api` | واجهة التوثيق (Stoplight Elements) | 200 |
| `/docs/api.json` | OpenAPI JSON | 200 |
| `api.json` | تصدير اختياري عبر `php artisan scramble:export` | ملف مُتجاهَل في Git |

### `config/scramble.php`

| الإعداد | القيمة | السبب |
|---|---|---|
| `api_path.include` | `['api/v1', 'api/v1/*']` | إبقاء المسارات كاملة داخل الوثيقة: `/api/v1/health` |
| `info.version` | `env('API_VERSION', '1.0.0')` | إصدار API الفعلي |
| `info.description` | Markdown يشرح prefix والصيغتين وأكواد الأخطاء | توضيح العقود المعتمدة |
| `ui.title` | `Basmat Nour API` | عنوان الواجهة و`info.title` |
| `openapi_version` | **محذوف عمدًا** | تجنّب الإشارة إلى enum من حزمة dev-only داخل ملف config |
| `dev_tools.enabled` | `env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false))` | أدوات التطوير تتبع debug فقط |
| `middleware` | `['web', RestrictedDocsAccess::class]` | الحماية الافتراضية لصفحات التوثيق |
| `security_strategy` | `null` | لا يوجد Auth بعد، ولا نضيفه لمجرد التوثيق |

> **ملاحظة تقنية مهمة:** عند وجود prefix واحد ثابت يقوم Scramble بنقله إلى `servers` وحذفه من المسارات (`/health`).
> لضمان أن الوثيقة تحتوي حرفيًا `GET /api/v1/health` — كما طُلب — استُخدمت إضافتان في `include`، وهذا يعطّل وضع
> "single base" ويبقي المسارات كاملة مع `servers: ["http://localhost:8000"]`.

### إثراء OpenAPI في `AppServiceProvider`

`Scramble::afterOpenApiGenerated()` يضيف ما لا يمكن استنتاجه من الكود الحالي:

1. `components.schemas.ErrorEnvelope` مطابق لمخرجات `ApiExceptionRenderer`:
   `success` / `message` / `error.code` / `error.details`.
2. مثال استجابة `200` الفعلي لـ `/api/v1/health`.
3. استجابتا `405` و`default` بالصيغة الموحدة، مع أمثلة `METHOD_NOT_ALLOWED` و`SERVER_ERROR`.
4. `operationId: health.check`، و`summary`، و`description`، و`tags: ["System"]`.
5. `security: []` للـ health لأنه عام بطبيعته (لا Auth في هذه المرحلة).
6. `license: MIT` في `info`.

> الاستدعاء محميّ بـ `class_exists(Scramble::class)`، لذلك لا يسبب خطأ في الإنتاج عند غياب الحزمة.

---

## 4. روابط ومسارات التوثيق

| الرابط | النتيجة |
|---|---|
| `http://localhost:8000/docs/api` | ✅ 200 · `text/html; charset=utf-8` · العنوان `Basmat Nour API` |
| `http://localhost:8000/docs/api.json` | ✅ 200 · `application/json` · OpenAPI **3.1.2** |
| `http://localhost:8000/api/v1/health` | ✅ 200 · JSON بالصيغة الموحدة |
| `http://localhost:8000/up` | ✅ 200 |
| `http://localhost:8000/` | ✅ 200 |

### محتوى الوثيقة

- `openapi: 3.1.2`
- `info.title: Basmat Nour API` · `version: 1.0.0` · `license: MIT`
- `servers: [{ url: "http://localhost:8000" }]` (مأخوذ من `APP_URL`)
- `paths./api/v1/health.get`:
  - `operationId: health.check`
  - `responses.200` مع schema ومثال فعلي
  - `responses.405` و`responses.default` بصيغة الخطأ الموحدة
- `components.schemas.ErrorEnvelope`

---

## 5. نتائج الاختبارات

```
Tests: 20 passed (74 assertions)
Duration: ~7s
```

### الاختبارات الجديدة — `tests/Feature/Api/DocumentationTest.php`

| الاختبار | النتيجة |
|---|---|
| `/docs/api` يعمل ويعرض HTML بالعنوان الصحيح | ✅ |
| `/docs/api.json` يُحمّل ويحتوي OpenAPI + info + servers | ✅ |
| المسار `/api/v1/health` موجود والطريقة `get` فقط | ✅ |
| مثال النجاح مطابق للاستجابة الفعلية | ✅ |
| `ErrorEnvelope` موثّق ومُشار إليه من 405/default | ✅ |
| لا تُسرَّب أسرار البيئة (DB password / APP_KEY / DB_PASSWORD) | ✅ |
| الوثيقة غير متاحة في `production` (403) | ✅ |

### الاختبارات السابقة
جميع اختبارات T-0.5/T-0.6 مستمرة بالنجاح: Health · ErrorHandling · ResponseEnvelope · TestingEnvironment · WebTest.

---

## 6. نتائج OpenAPI Validation

تم التصدير ثم التحقق عبر Redocly CLI:

```bash
docker compose exec -T app php artisan scramble:export
npx --yes @redocly/cli@latest lint api.json
```

**النتيجة:** `Woohoo! Your API description is valid.` — **0 errors · 1 warning**

| النتيجة | التفصيل | المعالجة |
|---|---|---|
| ✅ `security-defined` | كان خطأ: لا يوجد security | أُضيف `security: []` للـ health |
| ✅ `operation-operationId` | تحذير: operationId مفقود | أُضيف `health.check` |
| ✅ `info-license` | تحذير: license مفقود | أُضيف `MIT` |
| ⚠️ `no-server-example.com` | `http://localhost:8000` | **متوقع في التطوير** — الخادم محلي؛ يتغير تلقائيًا حسب `APP_URL` |

---

## 7. مراجعة Security

| البند | النتيجة |
|---|---|
| الوصول للتوثيق | `RestrictedDocsAccess` + Gate `viewApiDocs` |
| البيئات المسموحة | `local` و`testing` فقط |
| الإنتاج | **403** — ومُغطّى باختبار آلي |
| عند غياب Scramble (`--no-dev`) | لا تُسجَّل مسارات `/docs/*` أصلًا (404) |
| أسرار `.env` / DB | غير موجودة في الوثيقة — اختبار آلي يتحقق من DB password وAPP_KEY |
| Authentication جديدة | ❌ لم تُضف |
| Middleware مخصص | ❌ لم يُنشأ |
| Dev tools | تعمل فقط عند `APP_DEBUG=true` (أو `SCRAMBLE_DEV_TOOLS`) |
| ملف `config/scramble.php` | خالٍ من ثوابت/أصناف حزمة dev-only وقت التحميل |
| `api.json` المصدَّر | مُتجاهَل في Git، وليس جزءًا من مسار الإنتاج |

**ملاحظة:** واجهة Stoplight Elements تُحمّل أصولها من `unpkg.com` في المتصفح، لذا تحتاج الواجهة اتصال إنترنت لعرضها — لا علاقة لذلك بأسرار الخادم.

---

## 8. نتائج Docker والتحقق اليدوي

| الفحص | النتيجة |
|---|---|
| `docker compose config --quiet` | ✅ OK |
| `/docs/api` | ✅ 200 HTML |
| `/docs/api.json` | ✅ 200 JSON |
| `/api/v1/health` | ✅ 200 JSON |
| `/up` و`/` | ✅ 200 |
| `/health` مع **إيقاف PostgreSQL** | ✅ 200 |
| `/docs/api` و`/docs/api.json` مع **إيقاف PostgreSQL** | ✅ 200 (لا اعتماد على DB) |
| `SELECT 1` عبر Laravel | ✅ `1` · `basma_noor` |
| جداول `basma_noor` | ✅ **0** |
| جداول `basma_noor_test` | ✅ **0** |
| `docker compose restart` | ✅ التطبيق يعود تلقائيًا، وكل endpoints تعمل |
| الاختبارات بعد restart | ✅ 20/20 ناجحة |
| سجلات الأخطاء بعد restart | ✅ لا أخطاء |

---

## 9. المشكلات التقنية والقرارات

| # | المشكلة / القرار | المعالجة |
|---|---|---|
| 1 | `vendor:publish --provider=...` لم يجد موارد | استُخدم `--tag=scramble-config` بنجاح |
| 2 | `$document->paths` مصفوفة رقمية وليست keyed بالمسار | البحث عبر `collect($paths)->first(fn ($p) => $p->path === 'api/v1/health')` |
| 3 | Scramble يحذف الـ prefix عند وجود single base | إضافتان في `include` لإبقاء `/api/v1/health` كاملًا |
| 4 | `config/scramble.php` الافتراضي يشير إلى `OpenApiVersion::V3_1` (صنف dev-only) | حُذف المفتاح؛ Scramble يستخدم V3_1 افتراضيًا، والملف آمن عند `--no-dev` |
| 5 | Redocly: security غير معرّف | `security: []` للـ health |
| 6 | تحذير localhost في `servers` | مقبول ومتوقع في التطوير؛ مصدره `APP_URL` |
| 7 | هل يُلتزم `api.json` المصدَّر؟ | لا — أُضيف إلى `.gitignore`؛ التوثيق يُخدم ديناميكيًا |

---

## 10. الالتزام بالقيود

| القيد | الحالة |
|---|---|
| Migrations | ❌ صفر |
| Models | ❌ صفر |
| Controllers | ❌ صفر |
| Business Logic | ❌ لا شيء |
| Authentication / Sanctum | ❌ غير موجود |
| تعديل Database Schema | ❌ صفر — 0 جداول في القاعدتين |
| تغيير `DATABASE_DESIGN.md v4` | ❌ لم يُمَس |
| تغيير Response Standards | ❌ لم يتغير — التغيير الوحيد تحديث توثيقي لا سلوكي |
| إعادة فتح D-040 / D-041 / D-045 | ❌ لم يحدث |
| Phase 1 | ❌ لم تبدأ |

---

## 11. الخطوة التالية المقترحة

**T-0.10 — Private reports disk** (غير محجوبة):
- إعداد `storage/app/private/reports`.
- اختبار كتابة/قراءة الملفات عبر `Storage::disk(...)`.
- لا تحتاج Schema ولا Migrations.

**T-0.9** يبقى **مؤجلًا**: هدف `make fresh` يحتاج migrations من Gate C.

**Blockers قائمة:**
- **Gate C:** مغلق حتى حسم P-12 / P-20 / P-31 / P-15.
- **Gate D / Auth:** يعتمد على P-01 / P-04 / P-29 / P-32، P-02، P-07، P-03.

**متوقف الآن — لن تبدأ T-0.9 أو T-0.10 تلقائيًا.**
