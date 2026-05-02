# Smart Archive - Current-State Blueprint

## 1) Architecture Summary
- API Layer: `routes/api.php` routes requests to controllers.
- Application Layer: controllers orchestrate DTOs, validation requests, and business services.
- Domain/Application Services: auth/document/organization/department services implement use-case rules.
- Data Layer: repositories + Eloquent models persist to `users`, `organizations`, `departments`, `documents`, `personal_access_tokens`.
- Storage Layer: uploaded files stored on `public` disk (`storage/app/public/documents`).

## 2) Main Request Flow
1. Client calls `/api/*` endpoint with Sanctum bearer token where required.
2. Route middleware enforces authentication and role guard.
3. Request classes validate payload shape and references.
4. Controller builds DTO and calls service.
5. Service checks role/data rules and uses repository/model operations.
6. JSON response returned with stable contract.

## 3) Effective Role Matrix (High-Level)
- `SuperAdmin`: full system scope.
- `Admin`: organization scope.
- `Manager`: department scope (plus delegated user-management endpoints).
- `Employee`: department scope for read and own-document updates.
- `Auditor`: read-focused department scope.

## 4) Database Mapping by Module
- Auth: `users`, `personal_access_tokens`, `password_reset_tokens`.
- Organization: `organizations`.
- Department: `departments` (FK `organization_id`).
- Documents: `documents` (FK `organization_id`, `department_id`, `uploaded_by`) + file path on disk.
- OCR: persisted in `documents.extracted_text`.
- Classification: persisted in `documents.document_type`, `classification_confidence`, `classification_source`.

## 5) Implemented Hardening / Cleanup Decisions
- User creation endpoint moved to protected admin scope (`POST /api/users`, plus protected legacy alias `/api/AddUser`).
- Public self-registration path removed.
- User creation authorization hardened in service:
  - `SuperAdmin`: can create all roles.
  - `Admin`: can create only `Manager/Employee/Auditor` inside own organization.
- `email/resend` now uses authenticated current user only (no arbitrary email input).
- Login response contract stabilized to:
  - `user`
  - `plain_token`
  - `expires_at`
- `status` normalization aligned around `active/inactive` in organization and department schemas.
- Document search now includes OCR text (`extracted_text`).
- Document type classification now runs automatically on upload and after OCR extraction.
- OCR extraction flow deduplicated to a single consistent branch.
- Document role scoping/access checks centralized through helper methods in `DocumentController`.

## 6) Roadmap (Execution-Ready)
### Phase 1 - Hardening
- Keep all user-management endpoints behind explicit role middleware.
- Expand auth tests for forbidden/allowed creation and login contract stability.

### Phase 2 - Domain Cleanup
- Move document authorization and filtering logic from controller helpers into a dedicated domain service/policy.
- Normalize response payload structures for all modules (errors + pagination + resource shape).

### Phase 3 - Reliability
- Add feature tests for role matrix across organizations/departments/documents.
- Add OCR tests with mocked parser/OCR runners.
- Add regression tests for API contracts consumed by frontend clients.
