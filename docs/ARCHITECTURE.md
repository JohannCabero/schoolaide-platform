# SchoolAide Platform — Architecture

## Multi-Tenancy Implementation

### Approach: Single-Database with Global Scopes

All tenant data lives in one MySQL database. Every tenant-aware table carries a `tenant_id` column with composite indexes. The `TenantScope` Eloquent global scope is registered in each model's `booted()` method and automatically appends `WHERE tenant_id = ?` to every query.

**Tenant Resolution Order:**
1. `X-Tenant: {slug}` HTTP header — preferred for API clients and tests
2. Subdomain — `school-a.schoolaide.com` → slug = `school-a`

The resolved tenant is bound into Laravel's service container as `app('currentTenant')` by `TenantMiddleware`. The `EnsureUserBelongsToTenant` middleware then cross-checks that the authenticated user's `tenant_id` matches the resolved tenant, preventing valid tokens from being used across tenants.

**Background Jobs:** Jobs receive `tenant_id` as a constructor argument and call `app()->instance('currentTenant', $tenant)` at the start of `handle()`. This is essential because HTTP middleware does not run for queued jobs.

**Edge Cases Documented:**
- Artisan commands: must use `TenantAwareCommand` or accept `--tenant-id` flag
- Super-admin operations: use `withoutGlobalScope(TenantScope::class)` (e.g., tenant listing endpoint)
- Model relationships: use `->withoutGlobalScope(TenantScope::class)` on the relation definition to prevent double-scoping when loading eager relations within an already-scoped context

---

## Authorization Model

### Roles (Spatie/Laravel-Permission, scoped per tenant)

| Role    | Capabilities |
|---------|-------------|
| Admin   | Full CRUD on all resources; approve/reject any request; manage users and roles |
| Staff   | View all requests; approve/reject **assigned** requests only; manage students |
| Student | View **own** requests only; create requests for themselves |

Roles are tenant-scoped: a `tenant_id` column is added to the Spatie `roles` table so that "admin" at School A is independent from "admin" at School B.

### Policies (Fine-grained beyond RBAC)

- `ServiceRequestPolicy::approve` — staff must be the `assigned_to` user; admin can approve any
- `ServiceRequestPolicy::delete` — admin only
- `UserPolicy::assignRole` — admin only; staff cannot escalate privileges
- `UserPolicy::delete` — admin cannot delete themselves

### Privilege Escalation Prevention

- `POST /api/auth/register` accepts `role` values of `student` or `staff` only (validation rule `in:student,staff`). The `admin` role can only be assigned by an existing admin.
- `UserPolicy::assignRole` checks the actor has the admin role before allowing any role assignment.

---

## Concurrency Handling

### Dual-Layer Locking Strategy

**Layer 1 — Pessimistic Locking (database-level):**
`ServiceRequest::lockForUpdate()->findOrFail($id)` is used inside a `DB::transaction()`. This issues `SELECT ... FOR UPDATE`, causing the second concurrent transaction to block until the first commits. Once the first commits the status to `approved`, the second transaction reads the updated status and throws `InvalidRequestStateException`.

**Layer 2 — Optimistic Locking (application-level):**
The `version` column is incremented on every state-changing write. API clients must send `version` in approve/reject requests. If the version sent doesn't match the current database value, a `ConcurrencyConflictException` (HTTP 409) is returned immediately, before even attempting the lock. This catches stale reads efficiently without needing to wait for a DB lock.

**Unique Constraint:**
A unique index on `(tenant_id, student_id, service_type_id, requested_date)` prevents duplicate requests at the database level — even if application-level validation is bypassed.

---

## Database Design Rationale

- **Composite indexes** always include `tenant_id` as the leading column for efficient tenant-filtered queries
- **Soft deletes** on entities (not on audit_logs) to maintain referential integrity and audit trail continuity
- **Immutable audit logs** — no `updated_at`, no soft delete; `AuditLog::updating()` and `AuditLog::deleting()` throw exceptions
- **Version field** on `service_requests` for optimistic locking without external dependencies
- **`import_logs.summary_json`** stores a JSON array of skipped-row details to avoid a separate junction table

---

## Caching Strategy

- Student lookups are cached via `Cache::remember("tenant:{id}:student:{id}", 3600, ...)` with per-record keys
- Per-key invalidation: `Cache::forget("tenant:{id}:student:{id}")` is called on update/delete
- Tag-based invalidation is not implemented; each record is invalidated individually by key
- The file driver is sufficient for the current implementation; Redis is used for queues

---

## Known Limitations & Trade-offs

| Decision | Trade-off |
|----------|-----------|
| Single-database multi-tenancy | Simpler to operate; a runaway query from one tenant could affect all (mitigated by query timeouts) |
| Pessimistic locking for approvals | Slightly lower throughput under extreme concurrency; safer than optimistic-only for critical state transitions |
| Passport personal access tokens | Simpler than authorization code flow; tokens don't auto-refresh (clients must re-login after expiry) |
| Spatie roles scoped by tenant_id | Requires careful seeding; Spatie's cache must be cleared when roles change |
| No event sourcing / CQRS | Simpler codebase per exam requirements; audit log provides sufficient history for compliance |
