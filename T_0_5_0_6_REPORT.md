# تقرير T-0.5 + T-0.6 — Testing Infrastructure & API Response Standards

**المشروع:** بصمة نور (Basmat Nour)
**التاريخ:** 2026-11-14
**النطاق:** T-0.5 (Pest + قاعدة اختبار معزولة) + T-0.6 (Success/Error Response + Exception Handling)
**الحالة:** ✅ **مكتمل** — 13 اختبارًا ناجحًا، 0 migrations، 0 جداول في `basma_noor` و`basma_noor_test`.

---

## 1. ملخص التنفيذ

| البند | النتيجة |
|---|---|
| Pest | ✅ **4.7.8** + `pest-plugin-laravel` **4.1.0** |
| PHPUnit | 12.5.33 (متوافق مع قيود Laravel 13) |
| قاعدة الاختبار | ✅ `basma_noor_test` منفصلة تمامًا |
| اختبارات Pest | ✅ **13 نجحت / 41 assertion** |
| Success envelope | ✅ `success` · `message` · `data` · `meta` |
| Error envelope | ✅ `success` · `message` · `error.code` · `error.details` |
| معالجة الاستثناءات | ✅ مركزية في `bootstrap/app.php` عبر `ApiExceptionRenderer` |
| `/api/v1/health` | ✅ 200 JSON بالصيغة الموحدة، ولا يعتمد على قاعدة البيانات |
| Migrations / Models / Controllers | ❌ صفر |

---

## 2. Dependencies المضافة

| الحزمة | الإصدار | السبب |
|---|---|---|
| `pestphp/pest` | 4.7.8 | إطار الاختبار |
| `pestphp/pest-plugin-laravel` | 4.1.0 | تكامل Pest مع Laravel (يدعم `^13.0`) |

**لماذا Pest 4 وليس 5؟**
Pest 5 يتطلب `phpunit/phpunit ^13.4.5`، بينما سكيلتون Laravel 13 يثبّت `phpunit ^12.5`. Pest 4.7.8 متوافق مع Laravel 13 ومع PHPUnit 12، لذلك اختير **بدون** ترقية PHPUnit أو كسر قيود المشروع.

الحزم الانتقالية (paratest، pest plugins) جاءت تلقائيًا كاعتماديات Pest نفسه — لم تُضف أي حزمة غير لازمة يدويًا.

---

## 3. الملفات المنشأة/المعدّلة

### منشأة
| الملف | الوصف |
|---|---|
| `app/Support/ApiResponse.php` | مصدر واحد لصيغتي النجاح والخطأ |
| `app/Exceptions/ApiExceptionRenderer.php` | تحويل كل الاستثناءات إلى Error envelope |
| `tests/Pest.php` | ربط Pest بـ `Tests\TestCase` لمجلد Feature |
| `tests/Feature/Api/HealthTest.php` | عقد health + إثبات عدم استخدام DB |
| `tests/Feature/Api/ErrorHandlingTest.php` | 404/405/422/401/403/500-production |
| `tests/Feature/Api/ResponseEnvelopeTest.php` | صيغ النجاح/الخطأ وmeta |
| `tests/Feature/TestingEnvironmentTest.php` | تأكيد العزل على `basma_noor_test` |
| `tests/Feature/WebTest.php` | التأكد أن `/` لم يتأثر |
| `T_0_5_0_6_REPORT.md` | هذا التقرير |

### معدّلة
| الملف | التغيير |
|---|---|
| `bootstrap/app.php` | تسجيل `ApiExceptionRenderer` كمعالج مركزي لكل مسارات API |
| `routes/api.php` | `/health` يستخدم `ApiResponse::success(...)` |
| `phpunit.xml` | `DB_CONNECTION=pgsql` و`DB_DATABASE=basma_noor_test` مع `force="true"`، و`APP_DEBUG=false` |
| `tests/TestCase.php` | حارس صريح: توجيه كل الاختبارات إلى `basma_noor_test` |
| `composer.json` / `composer.lock` | إضافة Pest 4 + إضافة Laravel |
| `docs/PROJECT_ROADMAP.md` | T-0.5 وT-0.6 → `[x]` |
| حُذف | `tests/Feature/ExampleTest.php` و`tests/Unit/ExampleTest.php` (أمثلة افتراضية) |

---

## 4. Success Response — الصيغة النهائية

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {},
  "meta": null
}
```

- `data` يكون كائنًا فارغًا `{}` عند عدم وجود حمولة (وليس `null`).
- `meta` تُستخدم للـ Pagination أو أي بيانات وصفية، وإلا `null`.
- يُنشأ عبر `ApiResponse::success($data, $message, $meta, $status)`.
- استجابة `/api/v1/health` بعد التوحيد:

```json
{
  "success": true,
  "message": "Service is healthy",
  "data": {
    "status": "ok",
    "application": "basma-noor",
    "version": "1.0.0"
  },
  "meta": null
}
```

---

## 5. Error Response — الصيغة النهائية

```json
{
  "success": false,
  "message": "Validation failed",
  "error": {
    "code": "VALIDATION_ERROR",
    "details": {
      "email": ["The email field is required."]
    }
  }
}
```

| HTTP | `error.code` | الرسالة |
|---|---|---|
| 400 | `BAD_REQUEST` | حسب الاستثناء |
| 401 | `UNAUTHENTICATED` | `Authentication required.` |
| 403 | `FORBIDDEN` | `This action is unauthorized.` |
| 404 | `NOT_FOUND` | `Resource not found.` |
| 405 | `METHOD_NOT_ALLOWED` | `Method not allowed.` |
| 409 | `CONFLICT` | حسب الاستثناء |
| 422 | `VALIDATION_ERROR` | `Validation failed` + `details = errors()` |
| 429 | `TOO_MANY_REQUESTS` | `Too many requests. Please try again later.` |
| 500 | `SERVER_ERROR` | `An unexpected error occurred.` (في Production) |
| 503 | `SERVICE_UNAVAILABLE` | حسب الاستثناء |
| أخرى | `HTTP_ERROR` | حسب الاستثناء |

---

## 6. معالجة الاستثناءات مركزيًا

في `bootstrap/app.php`:

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
);

$exceptions->render(function (Throwable $e, Request $request) {
    if (! $request->is('api/*') && ! $request->expectsJson()) {
        return null; // صفحات الويب تبقى HTML
    }

    return ApiExceptionRenderer::render($e, $request);
});
```

- كل مسار `api/*` يرجع JSON حتى بدون `Accept: application/json`.
- `ApiExceptionRenderer` يغطي: `ValidationException`, `AuthenticationException`, `AuthorizationException`,
  `AccessDeniedHttpException`, `ModelNotFoundException`, `NotFoundHttpException`,
  `MethodNotAllowedHttpException`, `ThrottleRequestsException`, وأي `HttpExceptionInterface`، ثم أي `Throwable` → 500.
- **500 في Production:** رسالة عامة فقط، ولا `details`، ولا Stack Trace، ولا مسارات ملفات.
  في `APP_DEBUG=true` تُعرض رسالة الاستثناء للمطور فقط (بدون Stack Trace في الاستجابة).
- لا Controllers ولا Middleware مخصصة، ولا تكرار لمعالجة الأخطاء مستقبلًا.

---

## 7. إعداد قاعدة الاختبار

| العنصر | القيمة |
|---|---|
| قاعدة التطوير | `basma_noor` |
| قاعدة الاختبار | `basma_noor_test` |
| الخادم | نفس حاوية `db` (PostgreSQL 17.12) |
| migrations | ❌ لم تُشغَّل |

### الحماية المزدوجة
1. `phpunit.xml`:
   ```xml
   <env name="DB_CONNECTION" value="pgsql" force="true"/>
   <env name="DB_DATABASE" value="basma_noor_test" force="true"/>
   ```
2. `tests/TestCase.php`:
   ```php
   config(['database.connections.pgsql.database' => 'basma_noor_test']);
   DB::purge('pgsql');
   ```

### مشكلة تقنية واجهتنا وحلّها
`php artisan test` داخل Docker يرث `DB_DATABASE=basma_noor` من `$_SERVER` (متغيّر حاوية).
مكتبة Dotenv تقرأ `$_SERVER` قبل `$_ENV`، لذلك قيمة `phpunit.xml` لم تكن تُطبَّق رغم `force="true"`
(كانت `$_ENV` صحيحة و`$_SERVER` خاطئة). أُضيف الحارس في `TestCase::setUp()` لضمان العزل دائمًا،
وأُضيف اختبار `TestingEnvironmentTest` يتحقق من `current_database()` فعليًا.

---

## 8. نتائج الاختبارات

```
Tests: 13 passed (41 assertions) — Duration: ~4.2s
```

| الملف | الاختبار | النتيجة |
|---|---|---|
| `HealthTest` | عقد health الدقيق (200 + JSON + `assertExactJson`) | ✅ |
| `HealthTest` | عدم استخدام قاعدة البيانات (DB listener يرمي عند أي query) | ✅ |
| `ErrorHandlingTest` | 404 JSON بدون `Accept` | ✅ |
| `ErrorHandlingTest` | 405 لطريقة خاطئة | ✅ |
| `ErrorHandlingTest` | 422 Validation بصيغة موحّدة و`details` | ✅ |
| `ErrorHandlingTest` | 401 Unauthenticated | ✅ |
| `ErrorHandlingTest` | 403 Forbidden | ✅ |
| `ErrorHandlingTest` | 500 في Production بلا تسريب (SECRET/RuntimeException/Stack trace/مسارات) | ✅ |
| `ResponseEnvelopeTest` | Success envelope + `data = {}` | ✅ |
| `ResponseEnvelopeTest` | Pagination meta | ✅ |
| `ResponseEnvelopeTest` | Error envelope | ✅ |
| `TestingEnvironmentTest` | العزل على `basma_noor_test` | ✅ |
| `WebTest` | `/` يعمل | ✅ |

> مسارات الاختبار المؤقتة (`/api/v1/_test/*`) تُسجَّل **داخل الاختبارات فقط** ولا وجود لها في التطبيق.

---

## 9. نتائج Docker والتحقق اليدوي

| الفحص | النتيجة |
|---|---|
| `GET /api/v1/health` | ✅ 200 · `application/json` · الصيغة الموحدة |
| `GET /api/v1/missing` | ✅ 404 · JSON · `error.code = NOT_FOUND` |
| `GET /up` | ✅ 200 |
| `GET /` | ✅ 200 |
| `docker compose config --quiet` | ✅ OK |
| `/health` مع إيقاف `db` | ✅ 200 JSON (لا اعتماد على قاعدة البيانات) |
| `SELECT 1` عبر Laravel | ✅ `1` · `basma_noor` |
| جداول `basma_noor` | ✅ **0** |
| جداول `basma_noor_test` | ✅ **0** |
| `docker compose restart` | ✅ التطبيق يعود تلقائيًا، والـ endpoints تعمل، و13 اختبارًا تنجح بعدها |

---

## 10. المشكلات والقرارات التقنية

| # | المشكلة/القرار | المعالجة |
|---|---|---|
| 1 | Pest 5 يتطلب PHPUnit 13، والمشروع مثبّت على 12 | اختيار Pest 4.7.8 المتوافق |
| 2 | `$_SERVER` يتجاوز `phpunit.xml` (DB dev) | حارس في `TestCase::setUp()` + اختبار عزل فعلي |
| 3 | تحذير `use Throwable` في ملف بلا namespace | حُذف الـ use، ويُستخدم `Throwable` العالمي |
| 4 | تغيير شكل `/api/v1/health` إلى الصيغة الموحدة | موثّق ومُختبر بـ `assertExactJson`؛ الوظيفة لم تتغير |
| 5 | تمثيل `data`/`details` الفارغ | كائن فارغ `{}` بدل `null` في الحمولات |
| 6 | مسارات لاختبار 422/401/500 | تُسجَّل في الاختبارات فقط، بلا إضافة مسارات إنتاج |

---

## 11. الالتزام بالقيود

| القيد | الحالة |
|---|---|
| Migrations | ❌ صفر |
| Models | ❌ صفر |
| Controllers | ❌ صفر |
| Business Logic | ❌ لا شيء |
| Authentication / Sanctum | ❌ غير موجود |
| Middleware مخصص | ❌ صفر |
| تعديل Schema | ❌ صفر — 0 جداول في القاعدتين |
| Phase 1 | ❌ لم تبدأ |
| إعادة فتح D-040/D-041/D-045 | ❌ لم يحدث |

---

## 12. الخطوة التالية المقترحة

**T-0.7 — Scramble + `/docs/api`:**
- تثبيت `dedoc/scramble` (dev) وتوليد OpenAPI.
- التأكد أن `/docs/api` يعمل ويوثّق `GET /api/v1/health`.
- لا migrations ولا Models ولا Controllers.

**ما تبقّى في Phase 0 بعدها:**
- **T-0.9:** استكمال سكربتات Make (`fresh` لا يعمل بلا Laravel migrations — يُترك لِما بعد Gate C).
- **T-0.10:** إعداد private disk للتقارير (`storage/app/private/reports`) — لا يحتاج Schema.

**Blockers:**
- **Gate C مغلق:** لا migrations قبل تأكيد P-12 / P-20 / P-31 / P-15.
- **Gate D:** لا Backend قبل Gate C، ولا Auth قبل P-01/P-32/P-29/P-04.

**متوقف الآن — لن تبدأ أي مهمة جديدة تلقائيًا.**
