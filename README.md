# Smart Archive System

Smart Archive System is an API-first document archiving backend built with Laravel 12 for organizations that need structured document storage, role-based access control, approval workflows, OCR extraction, and searchable archives.

## Current Status

The project has moved beyond the initial skeleton stage. The backend currently supports:

- Authentication with Laravel Sanctum
- Email verification, password reset, and token revocation
- Multi-organization and department-aware user management
- Role-based authorization for `SuperAdmin`, `Admin`, `Manager`, `Employee`, and `Auditor`
- Document upload, listing, viewing, downloading, updating, and deletion
- Approval workflow with history tracking
- OCR extraction for image and PDF documents
- Automatic rule-based document classification
- Keyword search with optional hybrid semantic search through Ollama embeddings
- Pluggable OCR/embedding content storage using database or MongoDB

Current limitation:

- The web UI is not implemented yet. The main product surface today is the REST API under `/api/*`.

## Core Modules

### 1. Authentication and User Management

- Login with throttling protection
- Verified-email login enforcement
- Forgot/reset password flows
- Logout from current device or all devices
- Admin-controlled user creation, update, activation, suspension, and deletion

### 2. Organization and Department Management

- `SuperAdmin` manages organizations
- `SuperAdmin` and `Admin` manage departments
- `Admin` scope is limited to the admin's organization

### 3. Document Management

- Upload documents and store files on Laravel storage
- Assign documents to organization and department scopes
- View all allowed documents or only the current user's documents
- Download stored files
- Return a view URL for stored files
- Prevent duplicate uploads for the same user based on file metadata

### 4. Approval Workflow

Supported workflow states:

- `pending`
- `under_review`
- `approved`
- `rejected`
- `archived`

Supported workflow actions:

- Submit for review
- Approve
- Reject
- Archive
- Reopen
- Inspect workflow history

### 5. OCR and Search

- OCR extraction supports image files directly
- PDF OCR works by converting pages to images first, then running Tesseract
- Extracted text is normalized for Arabic and English content
- Search works in two modes:
  - Keyword fallback mode
  - Hybrid embeddings mode when Ollama is configured

### 6. Document Classification

Documents are classified automatically from metadata and OCR text into types such as:

- `invoice`
- `contract`
- `official_letter`
- `report`
- `identity_document`
- `receipt`
- `certificate`
- `policy`
- `legal_document`
- `other`

This classification is currently heuristic and rule-based, not a trained ML pipeline.

## Architecture Snapshot

- API layer: `routes/api.php`
- Controllers: orchestration and response handling
- Request classes: validation
- DTOs: input shaping
- Services: business rules and workflows
- Repositories: persistence abstraction
- Models: Eloquent entities and relationships

Important document services include:

- `DocumentAccessService`
- `DocumentMutationService`
- `DocumentWorkflowService`
- `DocumentClassificationService`
- `DocumentEmbeddingService`
- `HybridDocumentContentStore`

## Tech Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- SQLite by default for local setup
- MySQL or PostgreSQL can also be used
- Tesseract OCR
- Poppler / Imagick / ImageMagick for PDF-to-image conversion
- Ollama for optional embeddings-based search
- Optional MongoDB content store for OCR text and embeddings

## Installation

### Quick Setup

```bash
git clone https://github.com/Ibrahimalmari/smart-archive.git
cd smart-archive
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

If you want the frontend assets compiled as well:

```bash
npm install
npm run build
```

There is also a convenience Composer script:

```bash
composer setup
```

## Default Environment

The shipped `.env.example` is configured for:

- `DB_CONNECTION=sqlite`
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=database`
- `DOCUMENT_CONTENT_DRIVER=database`
- `OLLAMA_BASE_URL=http://localhost:11434`

## Optional Integrations

### OCR

For OCR to work reliably, install Tesseract and make it reachable from PHP.

Optional environment values used by the code:

```env
TESSERACT_PATH=C:\Program Files\Tesseract-OCR\tesseract.exe
TESSDATA_PREFIX=C:\Program Files\Tesseract-OCR\tessdata
PDFTOPPM_PATH=C:\path\to\pdftoppm.exe
```

PDF OCR requires at least one of these:

- `Imagick` PHP extension with Ghostscript
- `pdftoppm` from Poppler
- `magick` CLI with PDF support

See [OCR_SETUP.md](OCR_SETUP.md) for setup details.

### Ollama Embeddings

Hybrid semantic search is enabled only when Ollama is reachable and configured.

```env
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_EMBEDDING_DIMENSIONS=0
OLLAMA_TIMEOUT=30
OLLAMA_SEARCH_MIN_SCORE=0.45
```

If Ollama is unavailable, search falls back automatically to keyword mode.

### MongoDB Content Store

By default, OCR text and embeddings are stored in the relational database.

To move that content to MongoDB:

```env
DOCUMENT_CONTENT_DRIVER=mongodb
DOCUMENT_CONTENT_STRICT=false
DOCUMENT_CONTENT_MONGODB_URI=mongodb://127.0.0.1:27017
DOCUMENT_CONTENT_MONGODB_DATABASE=smart_archive
DOCUMENT_CONTENT_MONGODB_COLLECTION=document_contents
```

With `DOCUMENT_CONTENT_STRICT=false`, the system falls back to the database if MongoDB is unavailable.

See [docs/MONGODB_CONTENT_STORE_AR.md](docs/MONGODB_CONTENT_STORE_AR.md) for more details.

## Main API Areas

### Public Endpoints

- `POST /api/login`
- `POST /api/password/forgot`
- `POST /api/password/reset`
- `GET /api/email/verify/{id}/{hash}`

### Protected Endpoints

- User profile and session endpoints
- User administration endpoints
- Organization and department endpoints
- Document CRUD endpoints
- OCR endpoints
- Workflow endpoints
- Search endpoints

The source of truth for the API surface is [routes/api.php](routes/api.php).

## Testing

Run the full test suite with:

```bash
composer test
```

or:

```bash
php artisan test
```

Current tests cover areas such as:

- authentication hardening
- role-based authorization
- document approval workflow
- document classification
- document search
- OCR text normalization
- content store behavior

## Useful Project Files

- [routes/api.php](routes/api.php)
- [docs/PROJECT_BLUEPRINT_AR.md](docs/PROJECT_BLUEPRINT_AR.md)
- [OCR_SETUP.md](OCR_SETUP.md)
- [docs/MONGODB_CONTENT_STORE_AR.md](docs/MONGODB_CONTENT_STORE_AR.md)

## Summary

At its current stage, Smart Archive is a backend-focused organizational archive system with real document lifecycle management already implemented. The strongest completed areas are API authentication, permissions, document workflow, OCR integration, and search, while the main remaining gap is a dedicated user-facing frontend.
