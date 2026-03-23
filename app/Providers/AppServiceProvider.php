<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use App\Http\Repositories\OrganizationRepositoryInterface;
use App\Http\Repositories\OrganizationRepository;
use App\Http\Repositories\DepartmentRepositoryInterface;
use App\Http\Repositories\DepartmentRepository;

use App\Http\Services\Organization\OrganizationServiceInterface;
use App\Http\Services\Organization\OrganizationService;
use App\Http\Services\Department\DepartmentServiceInterface;
use App\Http\Services\Department\DepartmentService;
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

  $this->app->bind(
        \App\Http\Services\Document\DocumentServiceInterface::class,
        \App\Http\Services\Document\DocumentService::class
    );

     $this->app->bind(
        \App\Http\Repositories\DocumentRepositoryInterface::class,
        \App\Http\Repositories\DocumentRepository::class
    );
     $this->app->bind(
        OrganizationRepositoryInterface::class,
        OrganizationRepository::class
    );

    $this->app->bind(
        DepartmentRepositoryInterface::class,
        DepartmentRepository::class
    );

    $this->app->bind(
        OrganizationServiceInterface::class,
        OrganizationService::class
    );

    $this->app->bind(
        DepartmentServiceInterface::class,
        DepartmentService::class
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
