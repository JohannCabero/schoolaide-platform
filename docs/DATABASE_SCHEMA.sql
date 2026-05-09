-- =============================================================================
-- SchoolAide Platform — Database Schema
-- MySQL 8.0 · utf8mb4_unicode_ci
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `schoolaide_platform`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `schoolaide_platform`;

-- ---------------------------------------------------------------------------
-- tenants
-- Root entity. Every other table references this.
-- ---------------------------------------------------------------------------
CREATE TABLE `tenants` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)    NOT NULL COMMENT 'Display name of the organization',
  `slug`       VARCHAR(63)     NOT NULL COMMENT 'Subdomain / X-Tenant header value (lowercase, hyphens)',
  `domain`     VARCHAR(255)    DEFAULT NULL COMMENT 'Custom domain if applicable',
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `settings`   JSON            DEFAULT NULL COMMENT 'Tenant-specific configuration bag',
  `created_at` TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenants_slug`   (`slug`),
  UNIQUE KEY `uq_tenants_domain` (`domain`),
  KEY `idx_tenants_active`       (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- users
-- Tenant-scoped user accounts. Email uniqueness is per-tenant.
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`         BIGINT UNSIGNED NOT NULL,
  `name`              VARCHAR(255)    NOT NULL,
  `email`             VARCHAR(255)    NOT NULL,
  `email_verified_at` TIMESTAMP       DEFAULT NULL,
  `password`          VARCHAR(255)    NOT NULL,
  `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
  `remember_token`    VARCHAR(100)    DEFAULT NULL,
  `created_at`        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_tenant_email` (`tenant_id`, `email`),
  KEY `idx_users_tenant_active`      (`tenant_id`, `is_active`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- service_types
-- Lookup table for available service types per tenant (e.g. TRANSCRIPT).
-- ---------------------------------------------------------------------------
CREATE TABLE `service_types` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   BIGINT UNSIGNED NOT NULL,
  `name`        VARCHAR(255)    NOT NULL,
  `code`        VARCHAR(50)     NOT NULL COMMENT 'Short uppercase code, e.g. TRANSCRIPT',
  `description` TEXT            DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_types_tenant_code` (`tenant_id`, `code`),
  KEY `idx_service_types_tenant_active`     (`tenant_id`, `is_active`),
  CONSTRAINT `fk_service_types_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- students
-- Student records per tenant.
-- ---------------------------------------------------------------------------
CREATE TABLE `students` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `student_number` VARCHAR(50)     NOT NULL COMMENT 'Unique per tenant',
  `first_name`     VARCHAR(100)    NOT NULL,
  `last_name`      VARCHAR(100)    NOT NULL,
  `email`          VARCHAR(255)    DEFAULT NULL,
  `phone`          VARCHAR(20)     DEFAULT NULL,
  `birth_date`     DATE            DEFAULT NULL,
  `status`         ENUM('active','inactive','graduated','suspended') NOT NULL DEFAULT 'active',
  `program`        VARCHAR(100)    DEFAULT NULL,
  `year_level`     VARCHAR(20)     DEFAULT NULL,
  `user_id`        BIGINT UNSIGNED DEFAULT NULL COMMENT 'Linked portal user account',
  `created_at`     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_tenant_number` (`tenant_id`, `student_number`),
  UNIQUE KEY `uq_students_tenant_email`  (`tenant_id`, `email`),
  KEY `idx_students_tenant_status`       (`tenant_id`, `status`),
  KEY `idx_students_tenant_created`      (`tenant_id`, `created_at`),
  CONSTRAINT `fk_students_tenant`  FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_user`    FOREIGN KEY (`user_id`)   REFERENCES `users`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- service_requests
-- Core entity. version field enables optimistic locking.
-- ---------------------------------------------------------------------------
CREATE TABLE `service_requests` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`        BIGINT UNSIGNED NOT NULL,
  `student_id`       BIGINT UNSIGNED NOT NULL,
  `service_type_id`  BIGINT UNSIGNED NOT NULL,
  `assigned_to`      BIGINT UNSIGNED DEFAULT NULL COMMENT 'Staff assigned to handle',
  `processed_by`     BIGINT UNSIGNED DEFAULT NULL COMMENT 'Staff who approved/rejected',
  `status`           ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `requested_date`   DATE            NOT NULL,
  `remarks`          TEXT            DEFAULT NULL COMMENT 'Student notes on request',
  `processing_notes` TEXT            DEFAULT NULL COMMENT 'Staff approval/rejection notes',
  `processed_at`     TIMESTAMP       DEFAULT NULL,
  `version`          INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'Optimistic locking version counter',
  `created_at`       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Prevent duplicate: same student + service + date per tenant
  UNIQUE KEY `uq_service_requests_duplicate` (`tenant_id`, `student_id`, `service_type_id`, `requested_date`),
  -- Frequently filtered
  KEY `idx_requests_tenant_status`  (`tenant_id`, `status`),
  KEY `idx_requests_created_at`     (`tenant_id`, `created_at`),
  KEY `idx_requests_student`        (`tenant_id`, `student_id`),
  KEY `idx_requests_assigned`       (`tenant_id`, `assigned_to`),
  CONSTRAINT `fk_requests_tenant`       FOREIGN KEY (`tenant_id`)       REFERENCES `tenants`       (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_student`      FOREIGN KEY (`student_id`)      REFERENCES `students`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_service_type` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_assigned_to`  FOREIGN KEY (`assigned_to`)     REFERENCES `users`         (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_processed_by` FOREIGN KEY (`processed_by`)    REFERENCES `users`         (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- import_logs
-- Tracks each Excel import job — status, counts, per-row skip reasons.
-- ---------------------------------------------------------------------------
CREATE TABLE `import_logs` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`         BIGINT UNSIGNED NOT NULL,
  `user_id`           BIGINT UNSIGNED NOT NULL,
  `filename`          VARCHAR(500)    NOT NULL COMMENT 'Storage path',
  `original_filename` VARCHAR(255)    NOT NULL,
  `status`            ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `total_rows`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `successful_rows`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `skipped_rows`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `summary_json`      JSON            DEFAULT NULL COMMENT 'Array of {line, reason} for each skipped row',
  `error_message`     TEXT            DEFAULT NULL COMMENT 'Overall failure reason if job crashed',
  `started_at`        TIMESTAMP       DEFAULT NULL,
  `completed_at`      TIMESTAMP       DEFAULT NULL,
  `created_at`        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_imports_tenant_status`  (`tenant_id`, `status`),
  KEY `idx_imports_tenant_user`    (`tenant_id`, `user_id`),
  KEY `idx_imports_tenant_created` (`tenant_id`, `created_at`),
  CONSTRAINT `fk_imports_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_imports_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- audit_logs
-- Immutable compliance log. No updated_at. No soft delete. Never modified.
-- ---------------------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`      BIGINT UNSIGNED NOT NULL,
  `user_id`        BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL for system-triggered actions',
  `auditable_type` VARCHAR(255)    NOT NULL COMMENT 'Fully-qualified model class name',
  `auditable_id`   BIGINT UNSIGNED NOT NULL,
  `action`         ENUM('created','updated','deleted','restored','approved','rejected') NOT NULL,
  `old_values`     JSON            DEFAULT NULL,
  `new_values`     JSON            DEFAULT NULL,
  `ip_address`     VARCHAR(45)     DEFAULT NULL,
  `user_agent`     TEXT            DEFAULT NULL,
  `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity`  (`tenant_id`, `auditable_type`, `auditable_id`),
  KEY `idx_audit_user`    (`tenant_id`, `user_id`),
  KEY `idx_audit_action`  (`tenant_id`, `action`),
  KEY `idx_audit_created` (`tenant_id`, `created_at`),
  CONSTRAINT `fk_audit_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
  -- No FK on user_id intentionally: preserve logs even if the user is deleted
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Spatie laravel-permission tables
-- roles.tenant_id added by custom migration for per-tenant role scoping.
-- The remaining tables are the standard Spatie schema with no modifications.
-- ---------------------------------------------------------------------------

CREATE TABLE `roles` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL for global/platform roles; set for tenant-scoped roles',
  `name`       VARCHAR(125)    NOT NULL,
  `guard_name` VARCHAR(125)    NOT NULL,
  `created_at` TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`, `guard_name`),
  KEY `idx_roles_tenant_name` (`tenant_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual permissions (e.g. 'approve-requests', 'manage-students').
-- Not heavily used in this project — role-based checks are the primary gate.
CREATE TABLE `permissions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(125)    NOT NULL COMMENT 'e.g. approve-requests',
  `guard_name` VARCHAR(125)    NOT NULL,
  `created_at` TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot: roles granted specific permissions.
CREATE TABLE `role_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id`       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `role_id`),
  CONSTRAINT `fk_rhp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rhp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot: polymorphic — any model (User) may be assigned a role.
CREATE TABLE `model_has_roles` (
  `role_id`    BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255)    NOT NULL COMMENT 'Fully-qualified model class, e.g. App\Models\User',
  `model_id`   BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `model_id`, `model_type`),
  KEY `idx_mhr_model` (`model_type`, `model_id`),
  CONSTRAINT `fk_mhr_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot: polymorphic — any model may be granted a direct permission.
CREATE TABLE `model_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `model_type`    VARCHAR(255)    NOT NULL COMMENT 'Fully-qualified model class',
  `model_id`      BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
  KEY `idx_mhp_model` (`model_type`, `model_id`),
  CONSTRAINT `fk_mhp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
