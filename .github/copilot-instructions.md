# Copilot / AI agent instructions for smart-archive

Purpose: short, actionable guidance so an AI coding agent can be immediately productive in this Laravel codebase.

- **Project type:** Laravel app (PHP 8.2, Laravel 12), front-end built with Vite + Tailwind.
- **Key commands:**
  - Setup (full): `composer run-script setup` (runs composer install, copies `.env`, migrates, npm install/build)
  - Dev (local): `composer run-script dev` which concurrently runs `php artisan serve`, queue listener, `pail`, and `vite`.
  - Frontend: `npm run dev` (vite) and `npm run build` (production assets).
  - Tests: `composer run-script test` or `php artisan test` (phpunit configured in `phpunit.xml`).

- **Database & env notes:**
  - Tests use in-memory SQLite (see `phpunit.xml`).
  - Composer post-create scripts ensure a `database/database.sqlite` exists for quick local setups.
  - Check `.env` and `config/database.php` for environment-specific DB settings.

- **Authentication & tokens:**
  - Uses Laravel Sanctum for API authentication. See `AppServiceProvider::boot()` where `Sanctum::usePersonalAccessTokenModel` points to `app/Models/PersonalAccessToken.php` — tokens are personal access tokens (Bearer style).
  - Routes in `routes/api.php` separate public vs protected endpoints; protected endpoints are inside `Route::middleware('auth:sanctum')`.

- **Architecture & code patterns to follow:**
  - Dependency Injection is used heavily via interface bindings in `app/Providers/AppServiceProvider.php`. New service/repository pairs should:
    1. Create `app/Http/Services/<Feature>/<Name>Service.php` and an interface `...ServiceInterface.php`.
    2. Create `app/Http/Repositories/<Name>Repository.php` and `...RepositoryInterface.php`.
    3. Bind interface -> implementation in `AppServiceProvider::register()`.
  - Validation classes live in `app/Http/Requests` and DTOs in `app/Http/DTOs` — prefer using Requests for controller validation surface.
  - Notifications for verification/reset are in `app/Notifications` — follow existing `EmailVerificationNotification.php` and `ResetPasswordNotification.php` patterns.

- **API surface (common examples):** See `routes/api.php` for patterns and endpoints.
  - Auth: `POST /AddUser`, `POST /login` (throttled), password reset endpoints, email verification routes.
  - Documents: `POST /documents/add`, `GET /documents`, `GET /documents/{id}`, `PUT /documents/{id}`, `DELETE /documents/{id}` — expect controller `DocumentController` and service/repository behind it.

- **Role & middleware conventions:**
  - Routes use `->middleware('role:Admin,Manager')` and `->middleware('role:Admin')` to gate admin/manager functionality. Look for a `role` middleware in `app/Http/Middleware`.

- **Storage & files:**
  - Uploaded files and persistent assets go under `storage/app/public` and `storage/app/private`. Check `config/filesystems.php` to confirm mounts.

- **Where to change behavior safely:**
  - Business logic: `app/Http/Services` and `app/Http/Repositories`.
  - HTTP layer: `app/Http/Controllers` and `app/Http/Requests`.
  - Bindings: `app/Providers/AppServiceProvider.php` (must keep interface registrations synced).

- **Testing & CI notes:**
  - Tests live in `tests/Feature` and `tests/Unit`. PHPUnit config sets many env vars to stable test values (e.g., `QUEUE_CONNECTION=sync`).
  - Use `php artisan test` locally; CI likely runs the same.

- **Files to inspect for quick context when asked:**
  - `routes/api.php` — endpoint layout and middleware usage.
  - `app/Providers/AppServiceProvider.php` — DI bindings and rate limit config.
  - `app/Http/Services`, `app/Http/Repositories` — service/repo pattern examples.
  - `app/Models/Document.php`, `app/Models/User.php`, `app/Models/PersonalAccessToken.php` — domain models.
  - `phpunit.xml`, `composer.json`, `package.json` — developer workflows and scripts.

- **Dangerous/fragile spots to avoid changing without care:**
  - The service/repository bindings in `AppServiceProvider` — adding/removing requires keeping DI consistent.
  - Token model customization (`Sanctum::usePersonalAccessTokenModel`) — changing this impacts authentication tokens.
  - Composer `setup` script and migration behavior — can modify developer onboarding if changed.

If anything here is unclear or you'd like more detail for a specific area (example PR, testing flow, or how to add a service), tell me which part and I will expand or iterate.
