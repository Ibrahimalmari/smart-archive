<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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

  

}


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
    }
}
