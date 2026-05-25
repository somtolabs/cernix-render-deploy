<<<<<<< HEAD
# CERNIX — Exam Verification System


> **Last updated:** Phase 5 complete — Demo UI, health check, defense preparation  
> **Test suite:** 113 tests · 294 assertions · all passing
=======
> **Last updated:** Phase 6 complete — Student / Examiner / Admin API endpoints + mobile camera fix  
> **Test suite:** 146 tests · 384 assertions · all passing
>>>>>>> origin/main
=======
> **Last updated:** Phase 5 complete — Demo UI, health check, defense preparation  
> **Test suite:** 113 tests · 294 assertions · all passing
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [System Architecture](#system-architecture)
3. [Database Design](#database-design)
4. [API Endpoints](#api-endpoints)
5. [Services](#services)
6. [Security Model](#security-model)
7. [Live Demonstration Flow](#live-demonstration-flow)
8. [Panel Questions & Answers](#panel-questions--answers)
7. [Environment Variables](#environment-variables)
8. [Installation & Setup](#installation--setup)
9. [Running Tests](#running-tests)
10. [Development Progress](#development-progress)

---

## Project Overview

CERNIX is a **secure exam-hall identity and access verification system** for higher institutions. It solves the problem of impersonation and proxy examination by giving each registered, fee-paying student a one-time cryptographic QR token that an examiner scans at the hall entrance to approve or reject entry.

### The problem it solves

| Problem | CERNIX solution |
|---------|----------------|
| Proxy exam-taking (impersonation) | QR token embeds student identity encrypted with session-specific AES-256-GCM key; examiner sees student photo on scan |
| Token reuse / duplication | Every token is single-use; a second scan returns `DUPLICATE` and logs the event |
| Token forgery | HMAC-SHA256 signature over the encrypted blob; any tamper causes rejection before decryption |
| Unverified payment | Payment RRR verified against Remita before a token is issued |
| Audit trail gaps | Every verification decision is written to `verification_logs`; all system actions to `audit_log` |

### Actors

| Actor | Role |
|-------|------|
| **Student** | Self-registers, pays the exam fee, receives a QR token |
| **Examiner** | Scans QR at the hall; sees APPROVED / DUPLICATE / REJECTED |
| **Admin** | Manages sessions, examiners, and the exam configuration |

---

## System Architecture

### High-level data flow

```
mock_sis (SIS)
    │
    │  MockSISService.getStudentByMatric()
    ▼
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX
Student Registration          ← (next phase)
    │  creates row in students
    ▼
Payment (Remita)              ← (next phase)
<<<<<<< HEAD
=======
Student Registration
    │  creates row in students
    ▼
Payment (Remita)
>>>>>>> origin/main
=======
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX
    │  verifies RRR, writes payment_records
    ▼
QrTokenService.issue()
    │  encrypts payload with exam_session AES key
    │  signs with HMAC secret
    │  stores in qr_tokens (status = UNUSED)
    │  returns SVG QR image
    ▼
Student presents QR at hall
    ▼
QrTokenService.verify()
    │  decodes QR JSON → fetches session keys
    │  verifies HMAC (tamper check)
    │  decrypts payload (AES-256-GCM)
    │  checks qr_tokens.status
    │  UNUSED  → APPROVED  → marks USED, writes verification_log
    │  USED    → DUPLICATE → writes verification_log
    │  REVOKED → REJECTED  → writes verification_log
    ▼
verification_logs + audit_log
```

### Authentication flow

```
POST /api/{role}/login
    │
    ├─ AuthService.attemptLogin(credentials, role)
    │       ├─ Auth::guard('api')->attempt(credentials)   [JWT driver]
    │       ├─ Verify user.role === requested role
    │       └─ If mismatch → logout + return false → 401
    │
    └─ On success → return { token, token_type: "bearer", expires_in, user }

POST /api/student/register   (students only)
    ├─ Validate name, email, password, phone
    ├─ User::create([..., role: 'student'])
    └─ Auth::guard('api')->login($user) → token

Protected routes: Authorization: Bearer <token>
    └─ auth:api middleware → JWTAuth → resolves User model
```

Roles are embedded as a **custom JWT claim** (`role`) so middleware can gate
role-specific routes without a database hit.

### Component interaction diagram

```
┌─────────────────────────────────────────────────────────┐
│                     HTTP Layer                          │
│  routes/api.php → {Student|Examiner|Admin}\AuthController│
└────────────────────────┬────────────────────────────────┘
                         │
          ┌──────────────▼──────────────┐
          │         AuthService         │
          │  JWT guard · role check     │
          └──────────────┬──────────────┘
                         │
         ┌───────────────┼───────────────┐
         │               │               │
         ▼               ▼               ▼
  MockSISService   CryptoService   QrTokenService
  (read-only SIS)  (AES-GCM +     (issue · verify
                    HMAC-SHA256)    revoke · QR SVG)
         │               │               │
         └───────────────┴───────────────┘
                         │
          ┌──────────────▼──────────────┐
          │          Database           │
          │  MySQL (prod) / SQLite (test)│
          └─────────────────────────────┘
```

### Security layers

| Layer | Mechanism |
|-------|-----------|
| Transport | HTTPS (production) |
| Authentication | JWT (HS256), signed with `APP_JWT_SECRET`, `auth:api` middleware |
| Role enforcement | Role embedded in JWT claim; `AuthService` rejects cross-role logins |
| Payload encryption | AES-256-GCM with per-session random key stored in `exam_sessions.aes_key` |
| Payload integrity | HMAC-SHA256 of the base64 ciphertext blob; verified before decryption |
| IV randomness | 12-byte random IV generated fresh for every `encryptPayload` call |
| One-time tokens | `qr_tokens.status` transitions `UNUSED → USED`; second scan is `DUPLICATE` |
| Constant-time compare | All HMAC checks use `hash_equals()` — immune to timing attacks |
| Audit trail | Every verification written to `verification_logs`; broader events to `audit_log` |
| No cascade deletes | `verification_logs` and `audit_log` are append-only by design |

---

## Database Design

### Entity relationships

```
departments ◄──── students ────► exam_sessions
                     │                 │
                     │                 │
              payment_records    qr_tokens ◄──── verification_logs
                                       │                │
                                  exam_sessions    examiners

audit_log  (standalone, no FK)
mock_sis   (standalone SIS mirror, no FK)
```

### Tables

#### `departments`
| Column | Type | Notes |
|--------|------|-------|
| dept_id | bigIncrements | PK |
| dept_name | string | e.g. "Computer Science" |
| faculty | string | e.g. "Faculty of Computing" |

#### `exam_sessions`
| Column | Type | Notes |
|--------|------|-------|
| session_id | bigIncrements | PK |
| semester | string | e.g. "First Semester" |
| academic_year | string | e.g. "2025/2026" |
| fee_amount | decimal(10,2) | Exam fee in Naira |
| aes_key | text | 64-char hex of 32 random bytes |
| hmac_secret | text | 64-char hex of 32 random bytes |
| is_active | boolean | Only one session active at a time |
| timestamps | — | created_at, updated_at |

#### `mock_sis`
Simulated Student Information System — read-only source of truth for student identity.

| Column | Type | Notes |
|--------|------|-------|
| matric_no | string | PK |
| full_name | string | |
| department | string | |
| photo_path | string | Relative storage path |

#### `students`
Enrolled students who have been verified against the SIS.

| Column | Type | Notes |
|--------|------|-------|
| matric_no | string | PK, FK → mock_sis |
| full_name | string | Copied from SIS at enrollment |
| department_id | unsignedBigInteger | FK → departments |
| session_id | unsignedBigInteger | FK → exam_sessions |
| photo_path | string | |
| created_at | timestamp | |

#### `payment_records`
| Column | Type | Notes |
|--------|------|-------|
| payment_id | bigIncrements | PK |
| student_id | string | FK → students.matric_no |
| rrr_number | string | UNIQUE — Remita Retrieval Reference |
| amount_declared | decimal(10,2) | Declared by student |
| amount_confirmed | decimal(10,2) | Confirmed by Remita |
| remita_response | json | Full Remita API response |
| verified_at | timestamp | |

#### `examiners`
| Column | Type | Notes |
|--------|------|-------|
| examiner_id | bigIncrements | PK |
| full_name | string | |
| username | string | UNIQUE |
| password_hash | string | bcrypt |
| role | enum | `examiner` \| `admin` |
| is_active | boolean | Default false |
| created_at | timestamp | |

#### `qr_tokens`
| Column | Type | Notes |
|--------|------|-------|
| token_id | char(36) | PK — UUID, no auto-increment |
| student_id | string | FK → students.matric_no |
| session_id | unsignedBigInteger | FK → exam_sessions |
| encrypted_payload | text | Base64(IV \| ciphertext \| tag) |
| hmac_signature | text | HMAC-SHA256 of encrypted_payload |
| status | enum | `UNUSED` \| `USED` \| `REVOKED` |
| issued_at | timestamp | |
| used_at | timestamp | Nullable |

#### `verification_logs`
Append-only — no cascade delete.

| Column | Type | Notes |
|--------|------|-------|
| log_id | bigIncrements | PK |
| token_id | char(36) | FK → qr_tokens |
| examiner_id | unsignedBigInteger | FK → examiners |
| decision | enum | `APPROVED` \| `REJECTED` \| `DUPLICATE` |
| timestamp | timestamp | |
| device_fp | string | Device fingerprint |
| ip_address | string | |

#### `audit_log`
Append-only — no FK, no cascade delete.

| Column | Type | Notes |
|--------|------|-------|
| id | bigIncrements | PK |
| actor_id | string | |
| actor_type | string | e.g. "student", "examiner", "system" |
| action | string | e.g. "token.issued" |
| metadata | json | Arbitrary context payload |
| timestamp | timestamp | |

---

## API Endpoints

Base URL: `/api`

### Student auth
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/student/register` | Public | Register a new student account |
| POST | `/student/login` | Public | Login and receive JWT |

### Examiner auth
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/examiner/login` | Public | Examiner login |

### Admin auth
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/admin/login` | Public | Admin login |

### Shared protected
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/auth/logout` | Bearer | Invalidate JWT |
| POST | `/auth/refresh` | Bearer | Rotate JWT |
| GET | `/auth/me` | Bearer | Current authenticated user |

<<<<<<< HEAD
<<<<<<< HEAD
=======
### Student protected
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/student/register-exam` | Bearer (student) | Verify SIS + payment, issue encrypted QR token |

### Examiner protected
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/examiner/verify` | Bearer (examiner) | Verify scanned QR — returns APPROVED / DUPLICATE / REJECTED |

### Admin protected
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| GET | `/admin/sessions` | Bearer (admin) | List all exam sessions |
| POST | `/admin/sessions` | Bearer (admin) | Create a new exam session (generates AES + HMAC keys) |
| PATCH | `/admin/sessions/{id}/activate` | Bearer (admin) | Atomically activate one session, deactivate all others |
| GET | `/admin/examiners` | Bearer (admin) | List all examiners |
| POST | `/admin/examiners` | Bearer (admin) | Create an examiner record |
| PATCH | `/admin/examiners/{id}/toggle` | Bearer (admin) | Toggle examiner active status |
| POST | `/admin/tokens/{id}/revoke` | Bearer (admin) | Revoke an UNUSED token |
| GET | `/admin/logs` | Bearer (admin) | Filterable verification log (by examiner_id, decision) |
| GET | `/admin/stats` | Bearer (admin) | Total / approved / rejected / duplicate counts |

>>>>>>> origin/main
=======
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX
### Response envelope
All responses follow:
```json
{
  "status": "success | error",
  "message": "...",
  "data": { ... }
}
```

---

## Services

### `AuthService`
`app/Services/AuthService.php`

| Method | Description |
|--------|-------------|
| `attemptLogin(credentials, role)` | Verifies credentials **and** role match; returns JWT string or `false` |
| `logout()` | Invalidates current JWT via guard |
| `refresh()` | Rotates token, returns new JWT string |
| `me()` | Returns the authenticated `User` model |
| `tokenPayload(token)` | Builds `{token, token_type, expires_in}` response array |

---

### `CryptoService`
`app/Services/CryptoService.php`

| Method | Description |
|--------|-------------|
| `encryptPayload(payload, aesKey, hmacSecret)` | AES-256-GCM encrypt + HMAC-SHA256 sign |
| `decryptPayload(encrypted, hmac, aesKey, hmacSecret)` | HMAC verify first, then GCM decrypt |
| `generateRandomKey(length)` | `bin2hex(random_bytes(n))` — default 32 bytes |
| `signData(data, hmacSecret)` | HMAC-SHA256 |
| `verifySignature(data, signature, hmacSecret)` | Constant-time `hash_equals` compare |

**Encrypted blob layout:** `base64( IV[12 bytes] | ciphertext | auth_tag[16 bytes] )`

Keys accepted as either raw 32-byte strings or 64-char hex (as stored in `exam_sessions`).

---

### `QrTokenService`
`app/Services/QrTokenService.php`

| Method | Description |
|--------|-------------|
| `issue(matricNo, sessionId)` | Validates student + session, prevents duplicate UNUSED tokens, encrypts payload, stores in `qr_tokens`, returns token data + SVG QR |
| `verify(qrContent, examinerId, deviceFp, ip)` | Full verify pipeline → `APPROVED / DUPLICATE / REJECTED` → logs to `verification_logs` |
| `revoke(tokenId)` | Transitions `UNUSED → REVOKED` |
| `buildQrCode(content, size)` | Returns SVG string (no Imagick required) |

**QR content (encoded in image):**
```json
{ "v": 1, "session_id": 1, "encrypted_payload": "...", "hmac_signature": "..." }
```

---

### `MockSISService`
`app/Services/MockSISService.php`  
**Read-only.** No writes permitted.

| Method | Description |
|--------|-------------|
| `getStudentByMatric(matricNo)` | Returns `{matric_no, full_name, department, photo_path}` or throws `"Student not found in SIS"` |
| `getPhotoPath(matricNo)` | Returns `photo_path` string only |

---

### `RemitaService`
`app/Services/RemitaService.php`

Wraps the **Remita Fintech payment-query API**. All credentials are read from environment variables at runtime — never hardcoded.

| Method | Description |
|--------|-------------|
| `verifyPayment(rrrNumber, expectedAmount)` | Full pipeline: duplicate-RRR guard → Remita API call → success check → amount match. Returns full response array or throws. |
| `isPaymentSuccessful(response)` | Returns `true` when Remita status is `"Payment Successful"` (case-insensitive) or `"00"` |
| `amountMatches(expected, actual)` | Safe float comparison within a 0.001 tolerance |
| `rrrAlreadyUsed(rrrNumber)` | Checks `payment_records.rrr_number` — prevents replay attacks |

#### Remita credentials

| Env variable | Description |
|---|---|
| `REMITA_BASE_URL` | e.g. `https://remitademo.net/remita/exapp/api/v1` |
| `REMITA_PUBLIC_KEY` | Your Remita Fintech public key — used as `remitaConsumerKey` in the Authorization header |
| `REMITA_SECRET_KEY` | Your Remita Fintech secret key — used **only** to derive the `remitaConsumerToken` via `SHA512(publicKey + rrr + secretKey)`; **never sent over the wire** |

Set these in your local `.env` file. The `.env.example` has placeholder entries. Never commit real keys.

#### API call details

```
GET {REMITA_BASE_URL}/payment/query/{rrr}
Authorization: remitaConsumerKey={publicKey},remitaConsumerToken={sha512(publicKey+rrr+secretKey)}
Content-Type: application/json
```

#### How payment verification fits the system flow

```
Student submits RRR
        │
        ▼
RemitaService.verifyPayment(rrr, expectedAmount)
        ├─ rrrAlreadyUsed()  → throws if RRR already in payment_records
        ├─ queryRemita()     → hits Remita API, returns JSON body
        ├─ isPaymentSuccessful() → throws if status is not "Payment Successful"
        └─ amountMatches()   → throws if confirmed amount ≠ expected amount
        │
        ▼ (all checks passed)
Caller stores row in payment_records
        └─ remita_response = full JSON body
        └─ amount_confirmed = body['amount']
        └─ rrr_number = rrr (now guarded against reuse)
        │
        ▼
QrTokenService.issue() — token can now be issued
```

---

### `VerificationService`
`app/Services/VerificationService.php`

The **examiner gate** — the final step before a student enters the exam hall. Accepts decoded QR data and returns a structured decision. Never throws; all failures surface as a response status.

| Method | Description |
|--------|-------------|
| `verifyQr(qrData, examinerId, deviceFp, ip)` | 10-step pipeline → `APPROVED` / `DUPLICATE` / `REJECTED` |

**Decision logic (strict order):**
1. QR structure validation (4 required fields)
2. Token record lookup
3. Status check (USED → DUPLICATE, REVOKED → REJECTED)
4. Active session guard
5. `CryptoService::decryptPayload()` — HMAC first, then AES-GCM
6. Student DB lookup
7. Identity gate — `hash_equals()` matric match + session_id match
8. Atomic `DB::transaction()` + `lockForUpdate()` — closes race window on concurrent scans
9. Write `verification_logs` entry
10. Return `{status, student|null, token_id, timestamp}`

`student` is `null` for all non-APPROVED outcomes. AES/HMAC keys never appear in the response.

---

### `AuditService`
`app/Services/AuditService.php`

Provides a single append-only write path to `audit_log`. No update or delete operations are exposed.

| Method | Description |
|--------|-------------|
| `logAction(actorId, actorType, action, metadata)` | Inserts one row into `audit_log`; metadata is JSON-encoded |
| `encodeMetadata(array)` | Safe JSON encoder — falls back to an error placeholder rather than dropping the log entry |
| `now()` | Centralised UTC timestamp for consistent formatting |

**Recommended action names (dot-namespaced):**

| Event | Suggested action string |
|-------|------------------------|
| Token issued | `token.issued` |
| Token revoked | `token.revoked` |
| Payment verified | `payment.verified` |
| Student registered | `student.registered` |
| Scan result | `scan.approved` / `scan.duplicate` / `scan.rejected` |
| Session activated | `session.activated` |

---

<<<<<<< HEAD
<<<<<<< HEAD
=======
### Service Container (`AppServiceProvider`)
`app/Providers/AppServiceProvider.php`

All domain services are registered as **singletons** in the Laravel service container so controllers use constructor injection and tests can override bindings without touching production code.

| Binding | Resolved as |
|---------|-------------|
| `CryptoService` | Singleton — stateless, safe to share |
| `MockSISService` | Singleton — read-only SIS adapter |
| `QrTokenService` | Singleton — depends on `CryptoService` |
| `VerificationService` | Singleton — depends on `CryptoService` |
| `RemitaService` | Singleton — wraps a Guzzle `Client`; override in tests with `app()->bind(RemitaService::class, ...)` |
| `RegistrationService` | Singleton — depends on `MockSISService`, `RemitaService`, `CryptoService` |

---

>>>>>>> origin/main
=======
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX
## Audit Trail Design

### Why two separate log tables?

CERNIX uses **two append-only log tables** with distinct scopes:

#### `verification_logs`
- **Purpose:** Records every QR scan decision made at the exam hall entrance.
- **Scope:** Narrow — one row per scan, always tied to a specific `token_id` and `examiner_id`.
- **Use cases:** Detecting replay attacks, auditing which examiner approved a student, forensic review of DUPLICATE / REJECTED events.
- **FK constraints:** `token_id → qr_tokens`, `examiner_id → examiners` — guarantees referential integrity at the hall-entry level.

#### `audit_log`
- **Purpose:** Records broader system events that span beyond a single scan — registrations, payments, revocations, admin actions, session lifecycle.
- **Scope:** Wide — `actor_id` and `actor_type` are free strings; no FK constraints so system-level events (`actor_type = "system"`) can be logged without a matching DB row.
- **Use cases:** Compliance trail, admin accountability, detecting unusual patterns (e.g. a student registering twice from different IPs).

#### Why both are append-only

| Reason | Detail |
|--------|--------|
| **Tamper evidence** | If a record could be updated, a compromised admin could erase evidence of an approved scan or a payment |
| **Forensic integrity** | A `DUPLICATE` entry for the same `token_id` proves replay even if the original `APPROVED` entry is present |
| **Regulatory compliance** | Financial and identity audit trails must be immutable in most institutional contexts |
| **Simplicity** | An insert-only table has no UPDATE/DELETE permissions to grant, reducing attack surface |

Neither table has `ON DELETE CASCADE` — even deleting a student or examiner row cannot remove their audit history.

---

### Key management
- `exam_sessions.aes_key` and `exam_sessions.hmac_secret` are generated with `bin2hex(random_bytes(32))` at session creation — **never hardcoded**.
- `APP_JWT_SECRET` is set once per deployment via `php artisan jwt:secret` — stored only in `.env`.

### Encryption scheme
```
plaintext JSON payload
        │
        ▼
AES-256-GCM (random 12-byte IV, 128-bit auth tag)
        │
        ▼
base64( IV | ciphertext | tag )   ← encrypted_payload
        │
        ▼
HMAC-SHA256(encrypted_payload, hmac_secret)  ← hmac_signature
```

Verification reverses: HMAC checked first (constant-time), then GCM decryption (tag authenticates ciphertext). A forged or modified payload is rejected before any decryption is attempted.

### Token lifecycle
```
ISSUED → UNUSED
              │
    first scan│
              ▼
           USED  (decision: APPROVED)
              │
  rescan same │
              ▼
        DUPLICATE log entry (status stays USED)

    or
              │
  admin revoke│
              ▼
          REVOKED  (decision: REJECTED on any scan)
```

---

## Environment Variables

```ini
APP_NAME=CERNIX
APP_KEY=           # Set by: php artisan key:generate
APP_JWT_SECRET=    # Set by: php artisan jwt:secret (then rename JWT_SECRET → APP_JWT_SECRET)
APP_URL=http://localhost
APP_ENV=local
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cernix
DB_USERNAME=root
DB_PASSWORD=

REMITA_BASE_URL=https://remitademo.net/remita/exapp/api/v1
REMITA_MERCHANT_ID=
REMITA_SERVICE_TYPE_ID=
REMITA_API_KEY=
```

---

## Installation & Setup

```bash
# 1. Clone the repository
git clone https://github.com/bright1122-os/cernix-exam-verify.git
cd cernix-exam-verify/cernix

# 2. Install dependencies
composer install

# 3. Environment
cp .env.example .env
php artisan key:generate
# Generate JWT secret, then move it to APP_JWT_SECRET in .env
php artisan jwt:secret

# 4. Database (MySQL)
# Create the 'cernix' database, then:
php artisan migrate
php artisan db:seed

# 5. Run the application
php artisan serve
```

### Seed data included
| Seeder | Records |
|--------|---------|
| `DepartmentsSeeder` | 5 departments — Faculty of Computing |
| `ExamSessionsSeeder` | 1 active session — First Semester 2025/2026, ₦10,000 |
| `MockSISSeeder` | 5 students (realistic Nigerian names) |
| `ExaminersSeeder` | `examiner1` (password: `password123`) + `admin1` (password: `admin123`) |

---

## Running Tests

```bash
# All tests
php artisan test

# Specific suite
php artisan test tests/Feature/QrTokenServiceTest.php
php artisan test tests/Unit/CryptoServiceTest.php
```

### Current test coverage

| Test file | Tests | Assertions | Covers |
|-----------|-------|-----------|--------|
| `AppTest` | 1 | 1 | Root route |
| `AuthTest` | 12 | 46 | JWT auth, role enforcement |
| `DatabaseSchemaTest` | 1 | 9 | All 9 domain tables exist |
| `SeederTest` | 2 | 3 | Active session count, SIS records |
| `CryptoServiceTest` | 10 | 17 | AES-GCM, HMAC, key generation |
| `QrTokenServiceTest` | 17 | 33 | Issue, verify, revoke, QR image |
| `MockSISServiceTest` | 7 | 13 | SIS lookup, photo path, error cases |
| `RemitaServiceTest` | 11 | 31 | Payment verify, amount match, duplicate RRR |
| `RegistrationServiceTest` | 11 | 22 | Full 8-step registration flow |
| `VerificationServiceTest` | 17 | 49 | QR verify — APPROVED/DUPLICATE/REJECTED paths |
| `AuditServiceTest` | 11 | 20 | Append-only writes, metadata JSON, immutability |
| `EndToEndSystemTest` | 7 | 35 | Full SIS→Registration→QR→Scan→Audit lifecycle |
<<<<<<< HEAD
<<<<<<< HEAD
| **Total** | **113** | **294** | |
=======
| `StudentExamApiTest` | 7 | 18 | POST /student/register-exam — auth guard, happy path, edge cases |
| `ExaminerApiTest` | 7 | 22 | POST /examiner/verify — APPROVED/DUPLICATE/REJECTED over HTTP |
| `AdminApiTest` | 17 | 50 | All 9 admin endpoints — sessions, examiners, tokens, logs, stats |
| **Total** | **146** | **384** | |
>>>>>>> origin/main
=======
| **Total** | **113** | **294** | |
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX

---

## End-to-End System Validation

`tests/Feature/EndToEndSystemTest.php` exercises the complete CERNIX lifecycle as a single closed loop, using the same production seeders the live system runs on. Only the external Remita HTTP call is mocked — every other component (crypto, DB, QR generation, verification logic) runs for real.

### Full lifecycle tested

```
DepartmentsSeeder
ExamSessionsSeeder   ──► active session (real AES-256 key + HMAC secret)
MockSISSeeder        ──► CSC/2021/001 · Adebayo Oluwaseun Emmanuel
ExaminersSeeder      ──► admin1 (examiner performing the scan)
        │
        ▼
RegistrationService.registerStudent()
  └─ MockSISService validates student in SIS
  └─ Active session fetched from DB
  └─ RemitaService.verifyPayment() [mocked HTTP, real logic]
  └─ Student row inserted with SIS data (name/photo from SIS only)
  └─ CryptoService.encryptPayload() — AES-256-GCM + HMAC-SHA256
  └─ qr_tokens row inserted (status = UNUSED)
  └─ Returns { success, token_id, qr_payload, full_name, photo_path }
        │
        ▼
buildTokenData()  ──► { token_id, encrypted_payload, hmac_signature, session_id }
        │              (hmac_signature fetched from qr_tokens row)
        ▼
QrTokenService.buildQrCode()  ──► SVG QR image (no PII, no keys)
        │
        ▼
VerificationService.verifyQr()
  └─ QR structure validated
  └─ Token fetched, status = UNUSED ✓
  └─ Active session confirmed ✓
  └─ CryptoService.decryptPayload() — HMAC first (constant-time), then AES-GCM
  └─ Identity verified: hash_equals(matric_no) + session_id match
  └─ DB transaction: lockForUpdate() → status UNUSED→USED (atomic)
  └─ verification_logs entry written (decision = APPROVED)
  └─ Returns { status: APPROVED, student: {...}, token_id, timestamp }
        │
        ▼
AuditService.logAction()  ──► audit_log entries written by simulated controller layer
        │
        ▼
Second scan  ──► status = DUPLICATE (replay rejected, no second APPROVED log)
        │
        ▼
Tampered QR ──► status = REJECTED (HMAC mismatch caught before decryption)
```

### What this proves

| Assertion | What it guarantees |
|-----------|-------------------|
| `success = true`, `token_id` present | Registration pipeline is wired end-to-end |
| QR has only `{token_id, encrypted_payload, hmac_signature, session_id}` | No PII leaks into the QR image |
| `full_name` and `photo_path` come from SIS, not user input | Identity cannot be spoofed at registration |
| `status = APPROVED`, `student` not null | Crypto pipeline decrypts and authenticates correctly |
| `qr_tokens.status = USED` after first scan | Token is consumed exactly once |
| `verification_logs` has 1 entry | Audit trail is written on every decision |
| `audit_log ≥ 1 entry` | Audit service integrates into the flow |
| Second scan → `DUPLICATE`, only 1 APPROVED log | Replay attacks cannot grant a second entry |
| Tampered `encrypted_payload` → `REJECTED` | Forged QRs are rejected before any DB lookup |
| Tampered `hmac_signature` → `REJECTED` | HMAC tamper detection works end-to-end |

### Test isolation

- `RefreshDatabase` resets SQLite between every test method
- The Remita HTTP call is the only mock — all other I/O is real
- Real cryptographic keys are generated per test run via `ExamSessionsSeeder`
- No hardcoded tokens, payloads, or key material in the test file

---

## Development Progress

### Completed

| Phase | Description |
|-------|-------------|
| Skeleton | Laravel 11 project, packages, folder structure, `.env.example` |
| Auth | JWT auth for student / examiner / admin roles |
| Schema | 9 domain migrations with full FK constraints |
| Seeds | Departments, exam session, mock SIS students, examiners |
| CryptoService | AES-256-GCM encryption + HMAC-SHA256 signing layer |
| QrTokenService | Token issuance, one-time verification, revocation, SVG QR generation |
| MockSISService | Read-only SIS lookup by matric number |
| RemitaService | Remita Fintech payment verification — RRR query, amount check, duplicate guard |
| RegistrationService | 8-step student registration orchestrator — SIS → payment → student record → QR token |
| VerificationService | 10-step examiner QR verification engine — HMAC, atomic lock, append-only log |
| AuditService | Append-only `audit_log` writer with safe metadata encoding |
| EndToEndSystemTest | Full SIS→Registration→QR→Scan→Audit lifecycle validated as closed loop |
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX

### Up next

| Phase | Description |
|-------|-------------|
| Examiner API | HTTP endpoints wiring `VerificationService` — POST /examiner/verify |
| Student API | HTTP endpoints wiring `RegistrationService` — POST /student/register-exam |
| Admin API | Session management, examiner management, token revocation |
<<<<<<< HEAD
=======
| Student API | `POST /api/student/register-exam` — JWT-protected, wires `RegistrationService`, returns QR SVG |
| Examiner API | `POST /api/examiner/verify` — JWT-protected, wires `VerificationService`, logs APPROVED to `audit_log` |
| Admin API | 9 JWT-protected endpoints: session lifecycle, examiner management, token revocation, logs, stats |
| Service container | All services bound in `AppServiceProvider` — enables constructor injection and test mocking |
| Mobile camera fix | `isSecureContext` + `mediaDevices` guards; inline error panel replaces `alert()`; `facingMode: {ideal}` + explicit `video.play()` for iOS |
>>>>>>> origin/main
=======
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX

---

## Live Demonstration Flow

> Complete demo runs in under 2 minutes. Start the server with `php artisan serve` from the `cernix/` directory.

### Step-by-step

**Step 1 — Open the landing page**

Navigate to `http://localhost:8000`. You will see the CERNIX home screen with three portal cards (Student, Examiner, Admin) and a live system health badge confirming DB connectivity and an active exam session.

**Step 2 — Open the Student Portal**

Click **Student Portal** or navigate to `http://localhost:8000/student/register`.

You will see:
- Session banner: `First Semester 2025/2026 — Fee: ₦10,000.00`
- Two input fields: Matriculation Number and Remita RRR Number

**Step 3 — Enter credentials and generate QR**

Enter the following demo values:

| Field | Value |
|-------|-------|
| Matriculation Number | `CSC/2021/001` |
| Remita RRR | `280007021192` |

Click **Generate QR**. The button shows a spinner while the backend:
1. Validates the student against the mock SIS
2. Verifies the Remita payment
3. Creates the student exam record
4. Issues an AES-256-GCM encrypted QR token
5. Returns the SVG QR code

**Step 4 — View the QR result**

The form is replaced by a green success panel showing:
- Student name: **Adebayo Oluwaseun Emmanuel**
- Matric number: `CSC/2021/001`
- Session and UUID token ID
- The scannable QR code SVG

**Step 5 — Open the Examiner Portal**

Open a new tab and navigate to `http://localhost:8000/examiner/dashboard`.

Click **Start Scan** to activate the WebRTC camera. Point the camera at the QR code on the student tab, or paste the QR JSON manually into the text area and click **Verify Manually**.

<<<<<<< HEAD
<<<<<<< HEAD
=======
> **Mobile / tablet note:** Camera access requires a secure context. On **localhost** the camera works over plain HTTP. On any other hostname (e.g. a LAN IP) the browser enforces HTTPS — if the page is served over HTTP the camera panel shows a clear inline error explaining the requirement and directs the examiner to the manual QR input. No `alert()` is shown; the error is rendered inside the camera panel with a **Try Again** button.

>>>>>>> origin/main
=======
>>>>>>> origin/claude/setup-laravel-cernix-P1yxX
**Step 6 — Show APPROVED result**

The right panel turns green and shows:
- ✓ **APPROVED**
- Student name and matric number
- Token ID and timestamp

The token is now marked `USED` in the database.

**Step 7 — Scan the same QR again (replay attack)**

Click **Reset Scan**, then scan or paste the same QR data again.

The right panel turns yellow:
- ! **DUPLICATE** — This QR code has already been used. Entry denied.

Only one `APPROVED` decision ever exists in `verification_logs` for this token.

**Step 8 — View the Admin Panel**

Navigate to `http://localhost:8000/admin/dashboard`.

You will see:
- **Stats row**: Total scans, Approved (1), Rejected (0), Duplicates (1)
- **Verification Logs table**: Both the APPROVED and DUPLICATE entries with examiner ID, IP, and timestamp
- **Audit Log table**: `student.registered` and `scan.approved` entries

Click **Refresh Logs** to reload the data.

---

## Panel Questions & Answers

### Security design

**Q: Why is the student photo not stored inside the QR code?**

A: Embedding photo data would inflate the QR beyond scannable size and leak biometric information into a physically visible medium. Instead, the QR carries only a `token_id` and an encrypted payload. The examiner's device retrieves the photo from the secure server-side student record after cryptographic verification — the photo never travels through the QR channel.

---

**Q: Why use both AES-256-GCM and HMAC-SHA256 — aren't they redundant?**

A: They serve different purposes. AES-256-GCM provides *confidentiality* — it encrypts the payload so no raw PII (name, matric, photo hash) is readable from the QR code. HMAC-SHA256 provides *integrity* — it proves the payload was produced by a system that holds the secret key and has not been altered. The HMAC is checked *before* decryption so forged payloads are rejected with zero decryption cost.

---

**Q: How do you prevent QR code reuse (replay attacks)?**

A: Every token has a `status` column (`UNUSED` → `USED`). On the first successful scan, `VerificationService` performs an *atomic* status update inside a database transaction. Any subsequent scan sees `USED` and returns `DUPLICATE` immediately. The `APPROVED` decision is written to `verification_logs` only once — replays are logged as `DUPLICATE` and never grant entry.

---

**Q: What if two examiners scan the same QR at exactly the same time?**

A: Step 8 of `VerificationService` wraps the status transition in `DB::transaction()` with `SELECT … FOR UPDATE` (row-level lock). The first request acquires the lock, transitions the token to `USED`, commits, and returns `APPROVED`. The second request, when its lock is released, re-reads the status inside its own transaction, finds `USED`, and returns `DUPLICATE`. No race condition can produce two `APPROVED` decisions.

---

**Q: Why is student data fetched from the SIS rather than trusting user input?**

A: If a student could supply their own `full_name` and `photo_path` at registration, they could impersonate any other student. The `MockSISService` (standing in for the university's Student Information System) is the authoritative source. `RegistrationService` discards the user-supplied `full_name` entirely and reads name and photo path exclusively from the SIS lookup keyed on `matric_no`.

---

**Q: What does the audit log record and why is it append-only?**

A: `AuditService` writes to `audit_log` with no `UPDATE` or `DELETE` path in the service layer. Every action — registration, approval, duplicate detection — creates a new row. This gives investigators an immutable timeline: you can prove exactly when a token was issued, when it was consumed, and by which examiner. Foreign keys on `audit_log` intentionally have no `ON DELETE CASCADE` so records survive even if related rows are removed.

---

**Q: How is the Remita payment key kept secure?**

A: Keys are loaded from environment variables (`REMITA_PUBLIC_KEY`, `REMITA_SECRET_KEY`) via `config/remita.php`. They are never committed to source control (`.env` is gitignored), never logged, never returned in API responses. In tests, the HTTP layer is replaced with a Guzzle `MockHandler` so real keys are not required in the test environment at all.

---

**Q: What does the health endpoint check?**

A: `GET /health` checks two things: (1) it attempts to acquire a PDO connection to confirm the database is reachable, and (2) it queries `exam_sessions` for a row with `is_active = true`. The response `{ status, database, session_active, timestamp }` lets an operator or monitoring tool confirm the system is ready to process registrations before a session begins.
=======
# CERNIX — Secure Exam Access & Verification System

> **Important:** CERNIX is a final year academic project demonstration. Payment verification uses Remita's demo environment — no real fees are collected. Student identity verification uses a simulated SIS. This system is not connected to any institution's live payment or student records system.

CERNIX is a Laravel-based examination access and verification system for Adekunle Ajasin University project work. It links student identity, school-fee/payment status, timetable context, and a server-verifiable QR exam pass so exam access can be checked quickly and consistently at the venue.

Students register through a guided portal, select their faculty, department, level, and student number, and CERNIX generates the full matric number from the configured Faculty of Computing code map. The system validates the generated record, confirms the required department fee/payment state, and issues a one-time QR exam pass.

Examiners use a separate scanner portal to verify QR passes at the exam entrance. Admin and Super Admin users monitor students, payments, timetable entries, scan logs, audit activity, notes/notifications, settings, and the Python-assisted Risk Intelligence dashboard.

Laravel/PHP remains the main web application. The optional Python module only analyzes exported operational logs and produces risk reports for admin decision support.

## Problem Statement

Manual exam access checks can lead to slow queues, copied slips, weak payment-clearance checks, duplicated access passes, and limited auditability. CERNIX addresses these problems by combining:

- Controlled student registration and generated matric validation.
- Department-based fee amount checks.
- Server-side QR verification and one-time exam pass control.
- Examiner scan decisions with audit logs.
- Admin/Super Admin oversight and risk intelligence.

## Core Features

### Student Portal

- Guided exam registration at `/student/register`.
- Automatic matric generation from level, Faculty of Computing, department, and last three student-number digits.
- Department-based school-fee amount display.
- Demo/testing payment references only when demo mode is enabled.
- QR Exam Access ID and print-friendly exam pass.
- Student dashboard, profile, timetable, payment, instructions, scan detail, and notifications pages.

### Examiner Portal

- Examiner-only login at `/examiner/login`.
- Minimal live QR scanner at `/examiner/dashboard`.
- Server-controlled verification results: approved, rejected, or duplicate.
- Scan history, student records, today’s exams, audit trail, and examiner notifications.
- Admin and Super Admin accounts are not allowed into the Examiner portal.

### Admin Portal

- Admin login at `/admin/login`.
- Dashboard for operational monitoring.
- Student, payment, timetable, examiner, scan log, activity/audit, notes, notification, and student trace views.
- Admin Settings with role-sensitive controls.
- Admin Notes with visibility support for internal, student, examiner, or both-visible notes.
- Risk Intelligence page at `/admin/intelligence`.

### Super Admin

- Uses the Admin portal, not the Examiner portal.
- Can access system-level controls exposed by the current app, including settings, fee mapping, session controls, examiner/admin management, role-sensitive operations, audit views, and intelligence reporting.
- Regular Admin users have a more limited operational view.

### Python Intelligence Module

- Located at `python_services/risk_analyzer/`.
- Analyzes exported scan/payment/audit-style JSON data.
- Produces risk scoring, suspicious student/examiner/device/IP findings, summary observations, recommendations, JSON reports, and optional HTML reports.
- Provides deeper optional analysis for the Admin Risk Intelligence page. The Admin UI still calculates a live database summary from current scan logs even when no Python report has been generated.
- Does not handle authentication, QR verification, payment verification, cryptographic secrets, scanner verification, or exam pass approval logic.

## Tech Stack

- Laravel 11 / PHP
- Blade templates
- PostgreSQL for Render deployment, SQLite/local database support where configured
- Vite, JavaScript, and browser camera APIs for the scanner UI
- QR generation/scanning libraries already bundled through the Laravel/frontend stack
- Python standard library for offline risk analysis
- Docker and Render deployment files

## Major Routes

- `/` — public homepage
- `/documentation` — project documentation page
- `/student/register` — student exam registration
- `/student/dashboard` — student portal overview
- `/student/exam-access-id` and `/student/exam-pass` — QR exam pass views
- `/examiner/login` and `/examiner/dashboard` — examiner portal and scanner
- `/admin/login` and `/admin/dashboard` — admin portal
- `/admin/intelligence` — admin risk intelligence
- `/admin/settings` — admin settings

## Demo Mode

CERNIX has a demo/testing mode for academic and local testing environments. Demo payment references are accepted only when demo mode is active through the environment. In real production, keep demo mode disabled.

Do not publish real admin, examiner, database, Remita, or application credentials in public documentation. Demo users and passwords should be configured privately for the deployment environment.

## Local Setup

```bash
git clone <repository-url>
cd cernix-exam-verify
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure the database values in `.env`, then run:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

For a fresh local demo reset, use only project-provided commands and seeders. Do not commit `.env` or generated private storage reports.

## Python Intelligence Module

Run the sample analyzer:

```bash
python python_services/risk_analyzer/analyze.py
```

Export safe Laravel scan data:

```bash
php artisan cernix:export-risk-data
```

Generate a report for the Admin Intelligence page:

```bash
php artisan cernix:run-risk-analysis
```

Reports are written under `storage/app/risk-analysis/`. The web UI reads the JSON report safely when it is current. If the Python report is missing or older than the latest scan log, `/admin/intelligence` shows a live Laravel summary from current database records.

## Testing

```bash
php artisan test
npm run build
python python_services/risk_analyzer/analyze.py
```

If Playwright dependencies are installed:

```bash
npx playwright test --headed --workers=1
```

## Render Deployment

CERNIX is prepared for Render Docker deployment.

Important files:

- `Dockerfile`
- `render.yaml`
- `scripts/render-start.sh`
- `docs/render-deployment.md`

Set production environment variables in Render, not in the repository. Use:

- `APP_ENV=production`
- `APP_DEBUG=false`
- PostgreSQL connection through Render’s database URL variable
- `CERNIX_DEMO_MODE=false` for real production
- Remita and cryptographic keys stored only as private environment variables

The start script runs migrations, optional safe seeders, caches Laravel config/routes/views, and starts Laravel on Render’s assigned port.

## Security Notes

- Student, Examiner, Admin, and Super Admin portals are separated server-side.
- QR verification remains server-controlled and one-time-use.
- Audit logs record important scan and admin activity.
- Sensitive values must live in environment variables.
- Do not commit `.env`, real Remita keys, application keys, database URLs, QR payload internals, or passwords.
- Demo mode must remain disabled for real production use.

## Project Media

Project media and team/context images are documentation assets only. They are not used as student identity, passport, verification, or scanner data.

Demo passport images are local mock assets used for controlled testing and are not real university records.

## Academic Note

CERNIX is an academic/project system demonstrating secure exam access workflows, role-based portals, QR verification, auditability, and lightweight risk intelligence.

