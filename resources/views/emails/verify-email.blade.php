<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Cairo', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; }
        .email-wrapper { max-width: 620px; margin: 0 auto; }
        .email-card { background: #ffffff; border-radius: 16px; box-shadow: 0 12px 24px rgba(0,0,0,0.15); overflow: hidden; }
        .email-header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #fff; padding: 32px 24px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: 0.5px; }
        .email-body { padding: 32px 24px; text-align: right; line-height: 1.8; }
        .email-body p { margin: 0 0 16px; font-size: 16px; color: #333; }
        .greeting { font-size: 18px; font-weight: 700; color: #1e3a8a; margin-bottom: 20px; }
        .btn-container { text-align: center; margin: 28px 0; }
        .btn { 
            display: inline-block; 
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
            color: #fff; 
            text-decoration: none; 
            border-radius: 10px; 
            padding: 14px 32px; 
            font-weight: 700; 
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.6); }
        .info-box { background: #f0f4ff; border-right: 4px solid #3b82f6; padding: 16px; border-radius: 8px; margin: 20px 0; text-align: right; }
        .info-box p { margin: 0; font-size: 14px; color: #475569; }
        .footer { padding: 20px 24px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e5e7eb; }
        .link { word-break: break-all; color: #3b82f6; font-size: 13px; }
        .small { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-card">
            <div class="email-header">
                <h1>✓ Smart Archive</h1>
            </div>
            <div class="email-body">
                <p class="greeting">مرحباً {{ $user->name ?? 'صديقنا' }},</p>
                <p>شكراً لانضمامك إلى <strong>Smart Archive</strong>. نحن سعداء بوجودك معنا.</p>
                <p>لتفعيل حسابك وبدء الاستخدام، يرجى تأكيد بريدك الإلكتروني بالضغط على الزر أدناه:</p>
                
                <div class="btn-container">
                    <a href="{{ $verificationUrl }}" class="btn">تأكيد البريد الإلكتروني</a>
                </div>

                <div class="info-box">
                    <p>⏱️ هذا الرابط صالح لمدة <strong>{{ $expire }} دقيقة</strong> فقط</p>
                </div>

                <p class="small">إذا تواجهت مشكلة في الضغط على الزر، يمكنك نسخ الرابط التالي والصقه مباشرة في المتصفح:</p>
                <p class="link">{{ $verificationUrl }}</p>

                <p class="small" style="margin-top: 24px;">ℹ️ إذا لم تطلب هذا البريد، يمكنك تجاهله بأمان. لن يتأثر حسابك بأي شيء.</p>

                <p style="margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
                    مع أطيب التحيات، <br>
                    <strong>فريق Smart Archive</strong>
                </p>
            </div>
            <div class="footer">
                هذه الرسالة مرسلة تلقائياً من نظام Smart Archive. الرجاء عدم الرد على هذا البريد.
            </div>
        </div>
    </div>
</body>
</html>