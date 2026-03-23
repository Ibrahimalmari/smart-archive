<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class EmailVerificationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        // مدة الصلاحية بالدقائق
        $expire = 60; // الآن 60 دقيقة (للتجربة والاختبار)

        // توليد رابط مُوقع signed URL
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($expire),
            [
                'id'   => $notifiable->id,
                'hash' => sha1($notifiable->email),
            ]
        );

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('التحقق من البريد الإلكتروني - Smart Archive')
            ->from(config('mail.from.address', 'no-reply@smartarchive.local'), 'Smart Archive')
            ->view('emails.verify-email', [
                'user' => $notifiable,
                'verificationUrl' => $verificationUrl,
                'expire' => $expire,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
