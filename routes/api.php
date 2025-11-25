<?php
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailVerificationController;

/*
|--------------------------------------------------------------------------
| 🔓 Public Routes  (Routes without authentication)
| المسارات العامة — لا تحتاج تسجيل دخول
|--------------------------------------------------------------------------
*/

// Register / Add new user (Admin adds employees)
Route::post('/AddUser', [AuthController::class, 'AddUser']);

// Login with rate limit protection
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

// Forgot & Reset Password
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Email Verification Link
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');


/*
|--------------------------------------------------------------------------
|  Protected Routes (Requires Sanctum Token)
| المسارات المحمية — تتطلب تسجيل دخول ووجود توكن
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    ----------------------------------------------------------------------
    | 👤 User Self Actions
    | عمليات يقوم بها المستخدم على حسابه فقط
    ----------------------------------------------------------------------
    */

    // Update my profile (name, email, password)
    Route::post('/user/profile', [AuthController::class, 'updateMe']);

    // Get logged-in user info
    Route::get('/user', [AuthController::class, 'user']);

    // Resend email verification
    Route::post('/email/resend', [EmailVerificationController::class, 'send']);

    // Logout current device
    Route::post('/logout', [AuthController::class, 'logout']);

    // Logout from all devices
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);


    /*
    ----------------------------------------------------------------------
    | 🛠 Admin & Manager Actions
    | عمليات يقوم بها المدير أو الأدمن
    ----------------------------------------------------------------------
    */

    // Admin + Manager : Update user data
    Route::middleware('role:Admin,Manager')->group(function () {
        Route::post('/users/{id}', [AuthController::class, 'updateUser']);   // Update user
        Route::post('/users/{id}/status', [AuthController::class, 'changeStatus']); // Activate / deactivate accounts
    });


    /*
    ----------------------------------------------------------------------
    |  Admin Only Actions
    | عمليات يقوم بها الأدمن فقط
    ----------------------------------------------------------------------
    */

    Route::middleware('role:Admin')->group(function () {
        Route::delete('/users/delete/{id}', [AuthController::class, 'deleteUser']);
    });
});



