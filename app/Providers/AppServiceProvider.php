<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
   public function register()
{
    $this->app->bind(
        \App\Http\Services\Auth\AuthServiceInterface::class,
        \App\Http\Services\Auth\SanctumAuthService::class
    );

    $this->app->bind(
        \App\Http\Repositories\UserRepositoryInterface::class,
        \App\Http\Repositories\UserRepository::class
    );

    $this->app->bind(
    \App\Http\Services\Auth\PasswordServiceInterface::class,
    \App\Http\Services\Auth\PasswordService::class
);

  

}


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
                 // استخدام موديل التوكنز المخصص
                Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

                // تحديد Rate Limit لمحاولات تسجيل الدخول
                RateLimiter::for('login', function (Request $request) {

                    // نستخدم الايميل لكي يكون لكل مستخدم limit مستقل
                    $email = (string) $request->email;

                    return Limit::perMinute(5)->by($email);
                });
              
    
    }
}
