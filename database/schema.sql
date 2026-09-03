-- STI CCTV Portal — Database Schema
-- Engine: MySQL 8.0+ / MariaDB 10.5+

CREATE DATABASE IF NOT EXISTS sti_cctv_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sti_cctv_portal;

-- ---------------------------------------------------------------------
-- USERS & ROLES
-- ---------------------------------------------------------------------
-- Roles:
--   super_admin  -> full system control: manage admins, all NVRs/cameras,
--                   all permissions, view audit log
--   admin        -> manage NVRs/cameras (CRUD) and grant/revoke viewer
--                   access; cannot manage other admins or super_admins
--   viewer       -> end user (student org officer, guard, dept staff);
--                   can only open NVRs/cameras explicitly granted to them
--                   or flagged "public"
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    department      VARCHAR(100) DEFAULT NULL,
    role            ENUM('super_admin','admin','viewer') NOT NULL DEFAULT 'viewer',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NVRs
-- ---------------------------------------------------------------------
CREATE TABLE nvrs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,        -- e.g. "NVR 1"
    location        VARCHAR(150) DEFAULT NULL,     -- e.g. "3rd Floor Server Room"
    ip_address      VARCHAR(45)  NOT NULL,
    http_port       INT          NOT NULL DEFAULT 80,
    web_url         VARCHAR(255) DEFAULT NULL,     -- overrides auto-built URL if set
    admin_username  VARCHAR(100) DEFAULT NULL,     -- Hikvision device login (optional, encrypted)
    admin_password  VARBINARY(512) DEFAULT NULL,   -- AES-256-CBC encrypted
    is_public       TINYINT(1)   NOT NULL DEFAULT 0, -- 1 = every logged-in viewer can see it
    status          ENUM('online','offline','unknown') NOT NULL DEFAULT 'unknown',
    last_checked_at DATETIME     DEFAULT NULL,
    created_by      INT          DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_nvr_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CAMERAS (channels under an NVR, or standalone IP cameras)
-- ---------------------------------------------------------------------
CREATE TABLE cameras (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nvr_id          INT          NOT NULL,
    name            VARCHAR(100) NOT NULL,        -- e.g. "Computer Lab 101"
    channel_no      INT          DEFAULT NULL,     -- NVR channel number, if applicable
    ip_address      VARCHAR(45)  NOT NULL,
    http_port       INT          NOT NULL DEFAULT 80,
    web_url         VARCHAR(255) DEFAULT NULL,     -- overrides auto-built URL if set
    is_public       TINYINT(1)   NOT NULL DEFAULT 0,
    status          ENUM('online','offline','unknown') NOT NULL DEFAULT 'unknown',
    last_checked_at DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_camera_nvr FOREIGN KEY (nvr_id) REFERENCES nvrs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PERMISSIONS (granular grants for viewers; admins/super_admins bypass this)
-- ---------------------------------------------------------------------
CREATE TABLE permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    scope_type  ENUM('nvr','camera') NOT NULL,
    scope_id    INT NOT NULL,                     -- id in nvrs or cameras, per scope_type
    granted_by  INT DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_perm_granter FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_grant (user_id, scope_type, scope_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AUDIT LOG (who viewed / changed what)
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,   -- e.g. "camera.view", "nvr.create", "login"
    target_type VARCHAR(50)  DEFAULT NULL,
    target_id   INT DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    details     JSON DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SEED: one super admin (username: superadmin / password: ChangeMe!123)
-- Replace the hash immediately after install — see README.
-- ---------------------------------------------------------------------
INSERT INTO users (username, email, password_hash, full_name, role)
VALUES (
  'superadmin',
  'superadmin@sti.edu.ph',
  '$2y$10$FNlUeWAZ93zDn1pQppDqkOnVFo.ALfeBWLUnz6zo7Xcq5aF/6IKk6', -- bcrypt("ChangeMe!123")
  'System Administrator',
  'super_admin'
);

-- Example NVRs / cameras matching the sample tree in the brief
INSERT INTO nvrs (name, location, ip_address, http_port, is_public, created_by) VALUES
 ('NVR 1', 'Main Building', '192.168.1.10', 80, 1, 1),
 ('NVR 2', 'Annex Building', '192.168.1.20', 80, 1, 1);

INSERT INTO cameras (nvr_id, name, channel_no, ip_address, http_port, is_public) VALUES
 (1, 'Computer Lab 101', 1, '192.168.1.11', 80, 1),
 (1, 'Admissions and Lobby View', 2, '192.168.1.12', 80, 1),
 (2, 'Room 502', 1, '192.168.1.21', 80, 1),
 (2, 'Photography Room', 2, '192.168.1.22', 80, 1);
