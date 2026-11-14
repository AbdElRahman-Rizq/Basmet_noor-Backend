# تقرير T-0.4 — أساس الـ API وHealth Endpoint

**المشروع:** بصمة نور (Basmat Nour)
**التاريخ:** 2026-11-14
**المرحلة:** T-0.4 — `routes/api.php` + `GET /api/v1/health`
**الحالة:** ✅ **مكتمل** — كل الاختبارات ناجحة، بلا migrations وبلا أي اتصال بقاعدة البيانات من الـ endpoint.

---

## 1. الملفات التي أُنشئت/عُدّلت

| الملف | النوع | التغيير |
|---|---|---|
| `routes/api.php` | **جديد** | مسار `GET /health` يُرجع JSON ثابتًا، بلا أي وصول لقاعدة البيانات. |
| `bootstrap/app.php` | معدّل | تسجيل `api: routes/api.php` مع `apiPrefix: 'api/v1'`. |
| `compose.yaml` | معدّل | `command: php artisan serve --host=0.0.0.0 --port=8000` لربط تشغيل التطبيق بدورة حياة الحاوية. |
| `docs/PROJECT_ROADMAP.md` | معدّل | T-0.4 → `[x]`. |
| `T_0_4_REPORT.md` | **جديد** | هذا التقرير. |

> لم تُعدّل أي ملفات أخرى. لم تُنشأ migrations/models/controllers/middleware/auth.

---

## 2. تفاصيل تسجيل الـ API

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    apiPrefix: 'api/v1',
)
```

- `apiPrefix` متاح رسميًا في `ApplicationBuilder::withRouting()` في Laravel 13 (تم التحقق من توقيع الدالة في `vendor/`).
- `bootstrap/app.php` يستخدم `shouldRenderJsonWhen` لمسارات `api/*` (سلوك افتراضي من T-0.2).
- المسار داخل `routes/api.php` هو `/health`، فيصبح النهائي **`GET /api/v1/health`**.
- `php artisan route:list --path=api`:
  ```
  GET|HEAD  api/v1/health ............................. routes/api.php:15
  ```

### الاستجابة
```http
HTTP/1.1 200 OK
Content-Type: application/json
```
```json
{"status":"ok","application":"basma-noor","version":"1.0.0"}
```

---

## 3. مراجعة Docker

**المشكلة:** في T-0.2 كان الخادم يعمل بأمر `php artisan serve` **يدويًا** (`nohup`)، أي أنه ينتهي مع إعادة تشغيل الحاوية.

**الحل (أقل تعديل ممكن):** إضافة سطر واحد إلى خدمة `app` في `compose.yaml`:
```yaml
command: php artisan serve --host=0.0.0.0 --port=8000
```

النتيجة:
- التطبيق يعمل كعملية رئيسية للحاوية.
- `docker compose restart` / `up` يعيد تشغيل الخادم تلقائيًا — **بلا أي أمر Artisan يدوي**.
- لم تُغيَّر أي إعدادات أخرى: نفس الـ bind mount، نفس volume `vendor`، نفس `pgdata`، نفس المنافذ والـ healthcheck.
- `docker compose top app` يُظهر `php artisan serve` ثم `php -S 0.0.0.0:8000`.

> ملاحظة: `artisan serve` خادم تطوير فقط. خادم الإنتاج (php-fpm/nginx) خارج نطاق T-0.4.

---

## 4. نتائج الاختبارات

| # | الاختبار | النتيجة |
|---|---|---|
| 1 | `GET /api/v1/health` → HTTP 200 | ✅ 200 |
| 2 | Content-Type = `application/json` | ✅ |
| 3 | جسم الاستجابة مطابق تمامًا | ✅ `{"status":"ok","application":"basma-noor","version":"1.0.0"}` |
| 4 | `GET /up` → 200 | ✅ 200 |
| 5 | `GET /` لا يتأثر | ✅ 200 |
| 6 | `docker compose config --quiet` | ✅ OK |
| 7 | إعادة تشغيل الحاويات لا تتطلب Artisan يدويًا | ✅ الخادم عاد تلقائيًا، والـ endpoints تعمل |
| 8 | PostgreSQL متصل و`SELECT 1` | ✅ `select_1=1` · `current_db=basma_noor` |
| 9 | عدد الجداول لم يتغيّر | ✅ **0 جداول** |
| 10 | الـ endpoint لا يستخدم قاعدة البيانات | ✅ **اختبار حاسم:** أُوقفت `db` مؤقتًا (`docker compose stop db`) وأعاد `/api/v1/health` **200 JSON**، ثم أُعيد تشغيلها |

### أوقات الاستجابة (بيئة Windows bind mount)
| الطلب | الزمن |
|---|---|
| `/api/v1/health` (دافئ) | ~0.76s |
| `/up` | ~3.1s |
| `/` (صفحة الترحيب) | ~8.8s |
| أول طلب بعد الإقلاع (cold) | حتى ~58s |

> هذا بطء I/O في البيئة المحلية (Windows + Docker bind mount) وليس مشكلة في المسار؛ خادم PHP المدمج أحادي الخيط، لذا أول طلب بارد يعطّل ما بعده.

---

## 5. حالة Docker بعد إعادة التشغيل

```
basma-noor-app-1   Up (php artisan serve — يعمل تلقائيًا)
basma-noor-db-1    Up (healthy)
```

- `GET /api/v1/health` → 200 JSON بعد إعادة التشغيل.
- `GET /up` → 200.
- `GET /` → 200.
- `SELECT 1` عبر Laravel → ناجح.
- `public tables = 0`.
- الـ volumes (`pgdata`, `vendor`) سليمة ومستمرة.

---

## 6. المشكلات

| # | المشكلة | الحل/الحالة |
|---|---|---|
| 1 | الخادم في T-0.2 كان يعمل يدويًا ولا يعود بعد إعادة التشغيل | ✅ حُلّت بإضافة `command` إلى `compose.yaml` |
| 2 | أول طلب بارد يستغرق وقتًا طويلًا (~58s) بسبب bind mount | ℹ️ سلوك بيئة محلي؛ الطلبات التالية سريعة. لا تغيير مطلوب الآن |
| 3 | خادم PHP المدمج أحادي الخيط | ℹ️ مقبول للتطوير؛ الإنتاج سيستخدم php-fpm/nginx لاحقًا |

لا مشكلات أخرى. لم تُنشأ أي migrations أو Models أو Controllers.

---

## 7. الالتزام بالقيود

| القيد | الحالة |
|---|---|
| Migrations | ❌ صفر |
| Models | ❌ صفر |
| Controllers | ❌ صفر |
| Authentication / Sanctum | ❌ غير موجود (`sanctum` غير مذكور في `composer.json`) |
| Middleware مخصص | ❌ صفر |
| Phase 1 | ❌ لم تبدأ |
| تعديل Schema | ❌ صفر — 0 جداول |
| إعادة فتح D-040/D-041/D-045 | ❌ لم يحدث |

---

## 8. الخطوة التالية المقترحة

**T-0.5 — Pest + قاعدة `basma_noor_test`** (بلا migrations):
- تثبيت Pest (dev dependency) وتهيئته.
- إنشاء قاعدة `basma_noor_test` كـ database فارغة.
- اختبار Feature بسيط يتحقق من `GET /api/v1/health` → 200 + JSON متطابق.
- تأكيد أن عدد جداول قاعدة التطوير يبقى 0.

**بديل:** T-0.6 (Response envelope + Exception Handler) إذا فُضّل توحيد صيغة الأخطاء قبل إضافة الاختبارات.
**Gate C يبقى مغلقًا** حتى تأكيد P-12/P-20/P-31/P-15.
