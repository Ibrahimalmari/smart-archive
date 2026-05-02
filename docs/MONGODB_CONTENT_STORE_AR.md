# تخزين OCR والذكاء في MongoDB

هذا المشروع أصبح يدعم فصل بيانات OCR والذكاء عن بيانات النظام التقليدية.

## الفكرة

- تبقى بيانات النظام والعلاقات في MySQL/SQL: المستخدمون، المنظمات، الأقسام، الوثائق، الصلاحيات، الموافقات.
- تنتقل بيانات OCR والذكاء إلى MongoDB عند تفعيل الإعدادات: النصوص الطويلة، بيانات الصفحات، embeddings، ونتائج التحليل لاحقًا.
- الوضع الافتراضي ما زال يستخدم قاعدة البيانات الحالية حتى لا يتعطل المشروع أثناء التطوير.

## الإعداد الافتراضي

```env
DOCUMENT_CONTENT_DRIVER=database
```

هذا يعني أن OCR والـ embeddings تستمر في التخزين بالطريقة الحالية.

## تفعيل MongoDB

تحتاج أولًا إلى:

- تشغيل MongoDB Server.
- تفعيل امتداد PHP الخاص بـ MongoDB في بيئة PHP المستخدمة لتشغيل Laravel.

ثم عدل الإعدادات:

```env
DOCUMENT_CONTENT_DRIVER=mongodb
DOCUMENT_CONTENT_STRICT=false
DOCUMENT_CONTENT_MONGODB_URI=mongodb://127.0.0.1:27017
DOCUMENT_CONTENT_MONGODB_DATABASE=smart_archive
DOCUMENT_CONTENT_MONGODB_COLLECTION=document_contents
```

بعد التعديل:

```bash
php artisan config:clear
```

ثم أعد تشغيل السيرفر.

## السلوك عند فشل MongoDB

بشكل افتراضي:

```env
DOCUMENT_CONTENT_STRICT=false
```

إذا فشل الاتصال بـ MongoDB، يرجع النظام مؤقتًا إلى قاعدة البيانات الحالية حتى لا يفشل OCR أو البحث.

لو أردت إجبار النظام على الفشل عند تعطل MongoDB:

```env
DOCUMENT_CONTENT_STRICT=true
```
