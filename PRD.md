# PRD — sytemap-api (backend-api) ✅

**Authors:** Product / API Team  
**Date:** 2025-12-11  
**Reference code:** `routes/api/v1.php`, `app/Http/Controllers/Api/AuthController.php`, `app/Http/Controllers/Api/AgentController.php`, `app/Http/Controllers/Api/EstateController.php` 🔧

---

## 1. Executive Summary 💡
Build and maintain a secure, scalable REST API (v1) to support user registration and verification (OTP), agent workflows, estate & plot management, media uploads, commission handling, and payment callbacks for the Sytemap platform. The API will provide endpoints for public, authenticated user, and admin flows with clear validation, logging, and role-based access control.

---

## 2. Objectives & Goals 🎯
- Provide secure user registration with email OTP verification.
- Support agent authentication (internal & external), commission tracking, and withdrawals.
- Enable estate creation, media upload, plot generation, search, and availability features.
- Expose admin operations for referrals, commission settings, and withdrawal approvals.
- Ensure robust validation, monitoring, and test coverage for critical flows (auth, payments, file upload).

**Key success metrics:**
- 99.9% uptime for API endpoints
- < 200 ms median response for read endpoints
- 0 failed production email OTP deliveries per 1000 sent (target)
- 90% automated test coverage for controllers and critical services

---

## 3. Scope

### In scope ✅
- User registration/login, OTP email verification & resend (`AuthController`).
- Agent login (internal / external), balance and commission history (`AgentController`).
- Estate CRUD, media upload to Cloudinary, plot detail and availability computation (`EstateController`).
- Payment callback handling (Paystack) and plot purchase flows (see `PlotController` routes).
- Admin endpoints: commission settings, withdrawal approvals, referrals.
- API versioning prefix `/api/v1`.

### Out of scope ❌
- Frontend UI changes (backend only).
- SMS OTP (future enhancement).
- Migration to GraphQL.

---

## 4. User Personas & Journeys
- **Prospective Buyer (Unauthenticated):** Browse estates, view top-rated estates, filter/search, preview plot purchase.
- **Registered User:** Register → verify email via OTP → login → purchase plot (authenticated payment flow) → view purchases.
- **Agent:** Login (internal or via external API), view balance, commission history, request withdrawals (authenticated).
- **Admin:** Manage commission settings, approve/reject withdrawals, manage documents, view referrals.

---

## 5. Functional Requirements (selected) 🔧

### 5.1 Authentication & Users (`AuthController`)
- **POST** `/api/v1/auth/register` — Register user, create DB record, send OTP email (6-digit, expiry returned).
- **POST** `/api/v1/auth/verify-email` — Verify OTP and set `email_verified_at`.
- **POST** `/api/v1/auth/resend-otp` — Re-generate and email OTP; enforce rate limits.
- **POST** `/api/v1/auth/login` — Login; if unverified, resend verification OTP and return `requires_verification` flag.
- **GET** `/api/v1/user/account` (auth:sanctum) — Get user profile.

Validation: email format & existence, password length (>=8), OTP length = 6.

### 5.2 Agent Workflows (`AgentController`)
- **POST** `/api/v1/agent/login` — Agent login via external service (`GANDAWEBSITE_URL`), return agent details and create referral if missing.
- **POST** `/api/v1/agent/balance` — Return agent commission balance; create a 0-balance record if missing.
- **POST** `/api/v1/agent/commission-history` — Paginated (5 per page) commission history with `commission` relation.
- Protected routes: agent withdrawals (authenticated).

Edge cases: handle external service downtime with clear 500 error response.

### 5.3 Estate & Media Management (`EstateController`)
- **POST** `/api/v1/estate/estates/new` — Create estate with validation and image uploads to Cloudinary.
- **POST** `/api/v1/estate/estates/media` — Upload media (photos, 3D images, videos, virtual tour URL).
- **GET** `/api/v1/estate/estates/top-rated` — Top-rated estates.
- **GET** `/api/v1/estate/estates/top-rated-alt` — Alternative top-rated listing.
- **GET** `/api/v1/estate/estates/detail` — Top-rated with availability.
- **POST** `/api/v1/estate/estates/nearby` — Nearby estates (Haversine).
- **POST** `/api/v1/estate/estates/search` — Filter & search.
- **GET** `/api/v1/estate/detail/{estateId}` — Estate detail.

Validation: `status` enum (`draft|publish|unpublish`), file type and size limits, required fields.

---

## 6. Data Models (summary)
- **User:** id, first_name, last_name, email, password, state, country, email_verified_at, account_type
- **OTP:** email, code (hashed recommended), type, expires_at
- **Referral:** user_id, referral_code
- **Estate:** title, town_or_city, state, coordinates, size, direction, preview_display_image, map_background_image, status, rating, amenities
- **EstateMedia:** estate_id, photos[], third_dimension_model_images[], third_dimension_model_video, virtual_tour_video_url
- **EstatePlotDetail:** estate_id, available_plot, available_acre, price_per_plot, promotion_price, effective_price
- **AgentCommission / CommissionHistory / CommissionWithdrawal**
- **CommissionSetting**
- **Document**

Use proper indexes and FK constraints for performance (index `estate_id`, `agent_id`, `user_id`).

---

## 7. API Contract Summary (selected)

| Method | Path | Auth | Controller::Method | Purpose |
|---|---:|:---:|---|---|
| POST | `/api/v1/auth/register` | no | `AuthController::register` | Register user & send OTP |
| POST | `/api/v1/auth/verify-email` | no | `AuthController::verifyEmail` | Verify OTP |
| POST | `/api/v1/auth/login` | no | `AuthController::login` | Login (returns token or requires_verification) |
| POST | `/api/v1/agent/login` | no | `AuthController::agent_login` | External agent authentication |
| POST | `/api/v1/agent/balance` | no | `AgentController::balance` | Get agent commission balance |
| POST | `/api/v1/estate/estates/new` | (admin) | `EstateController::store` | Create estate + upload images |
| POST | `/api/v1/estate/estates/media` | (auth) | `EstateController::media_store` | Upload estate media |
| POST | `/api/v1/estate/estates/search` | no | `EstateController::filterSearch` | Filter & search estates |
| GET | `/api/v1/estate/detail/{estateId}` | no | `EstateController::EstateDetails` | Estate detail |

> Note: Admin-only endpoints are protected with `auth:sanctum` and `role:admin` middleware when applicable.

---

## 8. Non-Functional Requirements (NFRs)
- **Security:** Laravel Sanctum, OTP hashed & expiry, rate-limit OTP generation, role-based access control, webhook signature verification.
- **Performance:** Pagination for lists, cache heavy reads (e.g., top-rated estates).
- **Reliability:** Retry logic and graceful error responses for external APIs.
- **Observability:** Request/error logs, metrics for OTP sends and payment callbacks.
- **Storage:** Cloudinary for media.

---

## 9. Acceptance Criteria / Tests ✅
- **Registration:** returns `201`, user stored with `email_verified_at = null`, OTP sent and `expires_in_minutes` returned.
- **Verify email:** valid OTP marks `email_verified_at`; invalid/expired OTP returns 400/422.
- **Login:** unverified login returns 403 with `requires_verification`; verified login returns 200 with Sanctum token.
- **Agent login:** external success → create referral and return 200; external downtime → 500 with message.
- **Estate:** creating estate uploads images and stores URLs; validation errors return 422.
- **Commission:** balance endpoint creates 0 record if missing and returns correct sum; history is paginated and includes `commission` relation.
- **Payment callback:** verifies signature, updates purchase status, handles idempotency, and logs failures.

**Tests:** unit tests for controllers/services and integration tests for OTP flow, payment callback, and Cloudinary uploads (mocks/stubs).

---

## 10. Roadmap & Milestones 🛣️
**Phase 1 (2 weeks)**
- Core auth (register, OTP, verify, login), user profile
- Agent balance & commission history
- Estate create & media upload, basic search

**Phase 2 (3 weeks)**
- Plot preview/purchase with Paystack, callback handling
- Admin commission settings & withdrawal workflows

**Phase 3 (2 weeks)**
- Performance & security hardening, caching
- Full test coverage & docs (Swagger)

---

## 11. Risks & Mitigations ⚠️
- External agent API downtime → retries, circuit breaker, clear error message.
- OTP delivery failures → monitor metrics, admin resend, consider SMS fallback.
- Cloudinary upload failures → retry, background job fallback.
- Webhook tampering → signature verification and idempotency.

---

## 12. Open Questions / Assumptions ❓
- Who can create estates? (Admin or specific role) — assume admin unless specified.
- OTP storage approach: recommend hashing OTPs and storing expiry.
- Async media processing via queue recommended for large files.

---

## 13. Documentation & Deliverables 📚
- OpenAPI/Swagger docs (the repo uses `l5-swagger`).
- Postman collection with sample requests.
- Acceptance test scenarios and CI-run tests for OTP, payment callback, Cloudinary, and external agent login.

---

## 14. Next Steps 🔜
1. Confirm rules for estate creation and admin roles.  
2. Add OTP rate limiting + hashed OTP storage.  
3. Draft user stories & acceptance tests, or generate OpenAPI contract (pick next action).

---

> **Path:** `PRD.md` created at the repository root (`backend-api/PRD.md`).

*If you want, I can also generate user stories, acceptance tests, or a concise OpenAPI spec next.*
