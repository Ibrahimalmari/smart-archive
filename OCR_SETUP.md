# دليل تثبيت Tesseract OCR للذكاء الاصطناعي

## ما هو Tesseract OCR؟
Tesseract هو محرك OCR مفتوح المصدر يستخرج النص من الصور والملفات الممسوحة ضوئياً.

## تثبيت على Windows

### الخطوة 1: تحميل Tesseract
1. اذهب إلى: https://github.com/UB-Mannheim/tesseract/wiki
2. حمل النسخة الأحدث للويندوز (مثل: `tesseract-ocr-w64-setup-v5.3.0.20221214.exe`)
3. قم بتثبيته في المسار الافتراضي (عادة `C:\Program Files\Tesseract-OCR\`)

### الخطوة 2: إضافة إلى PATH
1. افتح System Properties > Advanced > Environment Variables
2. في System Variables، ابحث عن `Path` واضغط Edit
3. أضف: `C:\Program Files\Tesseract-OCR\`
4. أعد تشغيل الكمبيوتر

### الخطوة 3: تحميل حزم اللغات
1. حمل ملفات اللغة من: https://github.com/tesseract-ocr/tessdata
2. انقل الملفات إلى: `C:\Program Files\Tesseract-OCR\tessdata\`
3. تأكد من وجود:
   - `ara.traineddata` (للعربية)
   - `eng.traineddata` (للإنجليزية)

### الخطوة 4: اختبار التثبيت
افتح Command Prompt واكتب:
```
tesseract --version
tesseract --list-langs
```

## استخدام في المشروع

بعد التثبيت، ستعمل APIs التالية:

### استخراج النص من وثيقة
```
POST /api/documents/{id}/ocr
Authorization: Bearer {token}
```

### عرض النص المستخرج
```
GET /api/documents/{id}/ocr
Authorization: Bearer {token}
```

## ملاحظات مهمة

- يدعم الملفات: PDF, JPG, PNG, TIFF
- يدعم اللغات: العربية + الإنجليزية
- النص المستخرج يُحفظ في قاعدة البيانات في حقل `extracted_text`
- يمكن البحث في النص المستخرج عبر API البحث العادي

## استكشاف الأخطاء

إذا ظهرت رسالة "OCR غير متوفر حالياً":
- تأكد من تثبيت Tesseract
- تأكد من إضافة المسار إلى PATH
- تأكد من وجود ملفات اللغة

## بدائل أخرى

إذا واجهت صعوبة في التثبيت، يمكن استخدام:
- Google Cloud Vision API
- Azure Computer Vision
- AWS Textract