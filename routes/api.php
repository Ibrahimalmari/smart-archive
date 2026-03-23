<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\DepartmentController;
/*
|--------------------------------------------------------------------------
| API Routes — Structured & Bilingual Comments
| مسارات API — منسقة مع تعليقات باللغتين العربية والإنجليزية
|--------------------------------------------------------------------------
|
| Keep route behavior unchanged. This file organizes public vs protected
| routes, names common routes for clarity, and adds short Arabic/English
| explanations next to each route group.
|
*/

// -------------------------------------------------------------------------
// Public Routes (no authentication required)
// المسارات العامة — لا تتطلب توثيق
// -------------------------------------------------------------------------

// Register / Add new user (Admin adds employees)
Route::post('/AddUser', [AuthController::class, 'AddUser']);

// Login with rate limit protection (throttled)
// تسجيل الدخول مع حماية من تكرار المحاولات
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

// Forgot & Reset Password
// استرجاع كلمة المرور وإعادة تعيينها
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Email Verification Link (signed URL)
// رابط التحقق من البريد (موقع موقّع)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed'])
    ->name('verification.verify');


// -------------------------------------------------------------------------
// Protected Routes (require Sanctum token)
// المسارات المحمية — تتطلب توكن Sanctum
// -------------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {

    // ---------------------------------------------------------------------
    // User Self Actions — عمليات يحق للمستخدم القيام بها على حسابه
    // ---------------------------------------------------------------------

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


    // ---------------------------------------------------------------------
    // Admin & Manager Actions — عمليات المدير/المدير التنفيذي
    // ---------------------------------------------------------------------

    // Admin + Manager : Update user data and toggle status
    Route::middleware('role:Admin,Manager')->group(function () {
        Route::post('/users/{id}', [AuthController::class, 'updateUser']);
        Route::post('/users/{id}/status', [AuthController::class, 'changeStatus']);
    });


    // ---------------------------------------------------------------------
    // Admin Only Actions — عمليات خاصة بالأدمن فقط
    // ---------------------------------------------------------------------

    Route::middleware('role:Admin')->group(function () {
        Route::delete('/users/delete/{id}', [AuthController::class, 'deleteUser']);
    });


    // ---------------------------------------------------------------------
    // Documents CRUD — مستندات: إضافة، استعراض، تعديل، حذف
    // Controllers: DocumentController (service/repository used internally)
    // ---------------------------------------------------------------------

    // Documents: role-based access
    // - SuperAdmin,Admin: view all documents
    // - Manager: review documents (should be filtered by controller/service to manager's department)
    // - Employee: can add, update, delete their own documents before approval

    // Create document
    Route::post('/documents/add', [DocumentController::class, 'add'])
        ->middleware('auth:sanctum');

    // View documents
    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('auth:sanctum');

    // View own documents
    Route::get('/documents/mine', [DocumentController::class, 'myDocuments'])
        ->middleware('auth:sanctum');

    // Search documents (must be before /documents/{id})
    Route::get('/documents/search', [DocumentController::class, 'search'])
        ->middleware('auth:sanctum');

    // View single document
    Route::get('/documents/{id}', [DocumentController::class, 'show'])
        ->middleware('auth:sanctum');

    // Update document
    Route::put('/documents/{id}', [DocumentController::class, 'update'])
        ->middleware('auth:sanctum');
    Route::patch('/documents/{id}', [DocumentController::class, 'update'])
        ->middleware('auth:sanctum');

    // Delete document
    Route::delete('/documents/{id}', [DocumentController::class, 'delete'])
        ->middleware('auth:sanctum');

    // Download document
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])
        ->middleware('auth:sanctum');

    // View document (temporary URL)
    Route::get('/documents/{id}/view', [DocumentController::class, 'view'])
        ->middleware('auth:sanctum');

    // OCR: Extract text from document
    Route::post('/documents/{id}/ocr', [DocumentController::class, 'extractOcr'])
        ->middleware('auth:sanctum');

    // OCR: Get extracted text
    Route::get('/documents/{id}/ocr', [DocumentController::class, 'getOcrText'])
        ->middleware('auth:sanctum');

});

Route::middleware(['auth:sanctum', 'role:SuperAdmin'])->group(function () {
    // ORGANIZATIONS: Only SuperAdmin (Admin cannot manage organizations)
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{id}', [OrganizationController::class, 'show']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::put('/organizations/{id}', [OrganizationController::class, 'update']);
    Route::patch('/organizations/{id}', [OrganizationController::class, 'update']);
    Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy']);
});

// DEPARTMENTS: SuperAdmin (all) + Admin (his organization only)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/organizations/{orgId}/departments', [DepartmentController::class, 'index']);
    Route::post('/organizations/{orgId}/departments', [DepartmentController::class, 'store']);
    Route::get('/departments/{id}', [DepartmentController::class, 'show']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::patch('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);
});



