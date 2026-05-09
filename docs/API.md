# API Documentation — SchoolAide Platform

## Base URL
```
http://localhost:8000/api
```

## Authentication
All protected endpoints require:
- `Authorization: Bearer {token}` — Passport personal access token
- `X-Tenant: {slug}` — Identifies the school organization

Obtain a token via `POST /api/auth/login` or `POST /api/tenants/register`.

---

## Tenant Registration (Public)

### POST /api/tenants/register
Creates a new school organization and its first admin account.

**Request:**
```json
{
  "organization_name": "Greenfield Academy",
  "slug": "greenfield",
  "admin_name": "John Smith",
  "admin_email": "admin@greenfield.com",
  "admin_password": "securepass123",
  "admin_password_confirmation": "securepass123"
}
```

**Response 201:**
```json
{
  "data": {
    "tenant": { "id": 1, "name": "Greenfield Academy", "slug": "greenfield", "login_hint": "Use header X-Tenant: greenfield" },
    "admin":  { "id": 1, "name": "John Smith", "email": "admin@greenfield.com", "roles": ["admin"] },
    "token": "eyJ0eXAiOiJKV..."
  },
  "message": "Tenant 'Greenfield Academy' registered successfully."
}
```

---

## Authentication (Tenant-scoped)

### POST /api/auth/register  `X-Tenant: {slug}`
Register a new user within the tenant. Role: `student` (default) or `staff`.
```json
{ "name": "...", "email": "...", "password": "...", "password_confirmation": "...", "role": "student" }
```

### POST /api/auth/login  `X-Tenant: {slug}`
```json
{ "email": "admin@greenfield.com", "password": "password" }
```
**Response 200:** `{ "data": { "token": "...", "user": { "id", "name", "email", "roles" } } }`

### POST /api/auth/logout  🔒
Revokes the current token.

### GET /api/auth/me  🔒
Returns the authenticated user's profile and permissions.

---

## Students  🔒

### GET /api/students
**Query params:** `search`, `status`, `sort_by`, `sort_dir`, `page`, `per_page`

**Response 200:**
```json
{
  "data": [{ "id": 1, "student_number": "2024-0001", "full_name": "Juan Dela Cruz", "status": "active", ... }],
  "meta": { "current_page": 1, "per_page": 15, "total": 42 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

### POST /api/students
```json
{ "student_number": "2024-0001", "first_name": "Juan", "last_name": "Dela Cruz", "email": "...", "status": "active", "program": "...", "year_level": "..." }
```

### GET /api/students/{id}
**Response 200:**
```json
{
  "data": {
    "id": 1,
    "student_number": "2024-0001",
    "first_name": "Juan",
    "last_name": "Dela Cruz",
    "full_name": "Juan Dela Cruz",
    "email": "juan@greenfield.edu",
    "phone": null,
    "birth_date": "2002-03-15",
    "status": "active",
    "program": "BS Computer Science",
    "year_level": "3rd Year",
    "created_at": "2025-01-10T08:00:00Z",
    "updated_at": "2025-01-10T08:00:00Z"
  }
}
```

### PATCH /api/students/{id}
**Request:**
```json
{ "status": "inactive", "year_level": "4th Year", "program": "BS Information Technology" }
```
**Response 200:**
```json
{
  "data": {
    "id": 1,
    "student_number": "2024-0001",
    "full_name": "Juan Dela Cruz",
    "status": "inactive",
    "program": "BS Information Technology",
    "year_level": "4th Year",
    "updated_at": "2025-06-01T10:30:00Z"
  },
  "message": "Student updated successfully."
}
```

---

## Service Requests  🔒

### GET /api/service-requests
**Query params:** `status`, `date_from`, `date_to`, `assigned_to`, `sort_by`, `sort_dir`, `page`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "status": "pending",
      "requested_date": "2025-06-01",
      "remarks": "Urgent",
      "processing_notes": null,
      "processed_at": null,
      "version": 1,
      "student": { "id": 1, "student_number": "2024-0001", "full_name": "Juan Dela Cruz" },
      "service_type": { "id": 2, "code": "TRANSCRIPT", "name": "Transcript of Records" },
      "assigned_to": null,
      "created_at": "2025-05-30T09:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 38 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

### POST /api/service-requests
```json
{ "student_id": 1, "service_type_id": 2, "requested_date": "2025-06-01", "remarks": "Urgent" }
```
**Response 201:** `{ "data": { "id", "status": "pending", "version": 1, "student": {...}, "service_type": {...} } }`

### GET /api/service-requests/{id}
**Response 200:**
```json
{
  "data": {
    "id": 1,
    "status": "approved",
    "requested_date": "2025-06-01",
    "remarks": "Urgent",
    "processing_notes": "All documents verified.",
    "processed_at": "2025-06-02T14:00:00Z",
    "version": 2,
    "student": { "id": 1, "student_number": "2024-0001", "full_name": "Juan Dela Cruz" },
    "service_type": { "id": 2, "code": "TRANSCRIPT", "name": "Transcript of Records" },
    "assigned_to": { "id": 5, "name": "Maria Santos" },
    "processed_by": { "id": 5, "name": "Maria Santos" },
    "created_at": "2025-05-30T09:00:00Z",
    "updated_at": "2025-06-02T14:00:00Z"
  }
}
```

### PATCH /api/service-requests/{id}
**Request** (only `remarks` and `assigned_to` are patchable on a pending request):
```json
{ "remarks": "Updated notes.", "assigned_to": 5 }
```
**Response 200:**
```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "remarks": "Updated notes.",
    "assigned_to": { "id": 5, "name": "Maria Santos" },
    "version": 2,
    "updated_at": "2025-06-01T11:00:00Z"
  },
  "message": "Service request updated successfully."
}
```

### DELETE /api/service-requests/{id}  (Admin only)

### POST /api/service-requests/{id}/approve
```json
{ "notes": "All documents verified.", "version": 1 }
```
**Errors:**
- `409 Conflict` — `version` mismatch (concurrent modification)
- `422 Unprocessable` — request is not in `pending` state

### POST /api/service-requests/{id}/reject
```json
{ "notes": "Missing supporting documents.", "version": 1 }
```

---

## Imports  🔒 (Admin / Staff)

### POST /api/imports
`Content-Type: multipart/form-data`
Field: `file` (xlsx/xls, max 10 MB)

**Response 202:**
```json
{ "data": { "id": 1, "status": "pending", "original_filename": "requests.xlsx" }, "message": "Processing started." }
```

### GET /api/imports
**Query params:** `page`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "original_filename": "requests_may2025.xlsx",
      "status": "completed",
      "total_rows": 120,
      "successful_rows": 115,
      "skipped_rows": 5,
      "created_at": "2025-05-30T08:00:00Z",
      "completed_at": "2025-05-30T08:01:22Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 4 },
  "links": { "first": "...", "last": "...", "prev": null, "next": null }
}
```

### GET /api/imports/{id}
Returns full summary including per-row skip reasons.

**Response 200:**
```json
{
  "data": {
    "id": 1,
    "original_filename": "requests_may2025.xlsx",
    "status": "completed",
    "total_rows": 120,
    "successful_rows": 115,
    "skipped_rows": 5,
    "error_message": null,
    "started_at": "2025-05-30T08:00:05Z",
    "completed_at": "2025-05-30T08:01:22Z",
    "summary_json": [
      { "line": 4,  "reason": "Student not found: 9999-0099" },
      { "line": 17, "reason": "Student is inactive: 2021-0034" },
      { "line": 45, "reason": "Invalid service type: UNKNOWN_TYPE" },
      { "line": 78, "reason": "Duplicate request: student 2024-0011, TRANSCRIPT, 2025-06-01" },
      { "line": 99, "reason": "Missing student number" }
    ]
  }
}
```

---

## Error Responses

| Code | Meaning |
|------|---------|
| 400  | Bad request |
| 401  | Unauthenticated — missing or invalid token |
| 403  | Forbidden — insufficient permissions |
| 404  | Resource not found (also: tenant not found) |
| 409  | Conflict — optimistic lock version mismatch |
| 422  | Validation failed or invalid state transition |

**Error body:**
```json
{ "message": "Validation failed.", "errors": { "field": ["Error message."] } }
```

---

## Tenant Profile  🔒

### GET  /api/tenant/profile — Current tenant info
### PATCH /api/tenant/profile — Update name / settings (Admin only)

---

## Custom Domain (White-Label)

The `domain` field on a tenant enables schools to serve the platform from their own hostname instead of `slug.schoolaide.com`.

### How it works

| Step | Who | What |
|------|-----|------|
| 1 | School admin | Provides `domain: "portal.greenfield.edu"` during `POST /api/tenants/register`, or sets it later via `PATCH /api/tenant/profile` |
| 2 | School IT | Points DNS A/CNAME for `portal.greenfield.edu` to this server's IP |
| 3 | TenantMiddleware | On each incoming request, checks `tenants.domain` for an exact hostname match — if found, routes to that tenant without needing `X-Tenant` |

**Resolution priority:**
1. `X-Tenant` header → slug lookup *(API clients / tests)*
2. `Host` header → domain column exact match *(white-label)*
3. Subdomain of `schoolaide.com` → slug lookup *(default)*

### Setting domain at registration
```json
POST /api/tenants/register
{
  "organization_name": "Greenfield Academy",
  "slug": "greenfield",
  "domain": "portal.greenfield.edu",
  ...
}
```

### Setting or changing domain later
```json
PATCH /api/tenant/profile    (Authorization: Bearer {token}, X-Tenant: greenfield)
{ "domain": "portal.greenfield.edu" }
```

### Removing a custom domain
```json
PATCH /api/tenant/profile
{ "domain": null }
```
After this, only the subdomain and `X-Tenant` header strategies work.
