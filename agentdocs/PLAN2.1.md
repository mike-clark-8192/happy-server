# Phase 2.1: Security Hardening & Critical Fixes

**Goal**: Address critical issues identified in code review before proceeding to Phase 3.

**Timeline**: 1-2 days

---

## 2.1.1 Signature Verification (Critical)

**Issue**: AuthController accepts any public key without verifying signature
**Risk**: Complete authentication bypass

**Implementation**:
- Implement Ed25519 signature verification using Sodium
- Verify message = timestamp string
- Validate signature format (base64)
- Add proper error handling

**Files**:
- `src/Services/Auth/SignatureService.php` (new)
- `src/App/Api/Controllers/AuthController.php` (update)

---

## 2.1.2 Input Validation

**Issue**: No validation on API inputs
**Risk**: Invalid data, potential security issues

**Implementation**:
- Create Validator utility class
- Add validation rules for each endpoint
- Standardize validation error responses
- Validate: types, lengths, formats, required fields

**Files**:
- `src/Utils/Validator.php` (new)
- All controllers (update)

---

## 2.1.3 Auth Request Storage

**Issue**: Terminal auth requests not persisted
**Risk**: CLI authentication flow doesn't work

**Implementation**:
- Create TerminalAuthRequest model
- Store requests with expiration
- Implement approval flow
- Clean up expired requests

**Files**:
- `src/Storage/Models/TerminalAuthRequest.php` (new)
- `src/App/Api/Controllers/AuthController.php` (update)

---

## 2.1.4 Rate Limiting

**Issue**: No protection against API abuse
**Risk**: DoS attacks, resource exhaustion

**Implementation**:
- Create RateLimitMiddleware
- Track requests by IP
- Configurable limits (requests per minute)
- Return 429 Too Many Requests

**Files**:
- `src/App/Api/Middleware/RateLimitMiddleware.php` (new)
- `src/App/Api/routes.php` (update)

---

## 2.1.5 Logging Middleware

**Issue**: No visibility into API requests/errors
**Risk**: Difficult debugging, no audit trail

**Implementation**:
- Create LoggingMiddleware
- Log all requests with method, path, user
- Log responses with status, duration
- Log errors with stack traces

**Files**:
- `src/App/Api/Middleware/LoggingMiddleware.php` (new)
- `src/App/Api/routes.php` (update)

---

## 2.1.6 CORS & Security Headers

**Issue**: Browser clients can't access API
**Risk**: Cross-origin requests blocked

**Implementation**:
- Add CORS middleware with configurable origins
- Add security headers (X-Frame-Options, etc.)
- Handle OPTIONS preflight requests

**Files**:
- `src/App/Api/Middleware/CorsMiddleware.php` (new)
- `src/App/Api/routes.php` (update)

---

## Deliverables

### New Files (6)
- `SignatureService.php` - Ed25519 signature verification
- `Validator.php` - Input validation utility
- `TerminalAuthRequest.php` - Auth request model
- `RateLimitMiddleware.php` - Request rate limiting
- `LoggingMiddleware.php` - Request/response logging
- `CorsMiddleware.php` - CORS and security headers

### Updated Files (3)
- `AuthController.php` - Use SignatureService, store requests
- `routes.php` - Register new middleware
- All controllers - Add input validation

### Tests
- SignatureService tests
- Validator tests
- Rate limiting tests

---

## Success Criteria

- [ ] Signature verification rejects invalid signatures
- [ ] Input validation catches malformed requests
- [ ] CLI auth flow works end-to-end
- [ ] Rate limiting returns 429 after threshold
- [ ] All requests are logged
- [ ] CORS headers present on responses
- [ ] Security headers present on responses

---

## Priority Order

1. **Signature Verification** - Critical security fix
2. **Input Validation** - Prevent bad data
3. **Auth Request Storage** - Complete auth flow
4. **Logging Middleware** - Visibility
5. **Rate Limiting** - Protection
6. **CORS & Security Headers** - Browser support

---

## Estimated Time

- Signature Verification: 30 min
- Input Validation: 45 min
- Auth Request Storage: 30 min
- Logging Middleware: 20 min
- Rate Limiting: 30 min
- CORS & Headers: 20 min
- Testing: 30 min

**Total**: ~3-4 hours
