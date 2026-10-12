-- HireSwipe: auth + onboarding schema (MySQL 8 / MariaDB 10.5+)
CREATE DATABASE IF NOT EXISTS hireswipe
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hireswipe;

-- 1. USERS: one row per account (signup + role check)
--    confirm_password is validated in PHP only and never stored.
CREATE TABLE users (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100) NOT NULL,
  email              VARCHAR(255) NOT NULL,
  country_code       VARCHAR(5)   NOT NULL DEFAULT '+91',
  mobile             VARCHAR(15)  NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,          -- password_hash($pwd, PASSWORD_DEFAULT)
  role               ENUM('employee','employer') NULL, -- NULL until user picks in onboarding
  onboarding_status  ENUM('pending_role','pending_profile','completed')
                     NOT NULL DEFAULT 'pending_role',
  email_verified_at  DATETIME NULL,
  mobile_verified_at DATETIME NULL,
  status             ENUM('active','blocked','deleted') NOT NULL DEFAULT 'active',
  failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until       DATETIME NULL,
  last_login_at      DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_mobile (country_code, mobile),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

-- 2. OTP: mobile OTP login, signup mobile verification, password reset by OTP
CREATE TABLE otp_verifications (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       BIGINT UNSIGNED NULL,                -- NULL if mobile not registered yet
  country_code  VARCHAR(5)  NOT NULL DEFAULT '+91',
  mobile        VARCHAR(15) NOT NULL,
  purpose       ENUM('signup','login','forgot_password') NOT NULL,
  otp_hash      VARCHAR(255) NOT NULL,               -- store hash, never plain OTP
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0, -- wrong tries (block after 5)
  expires_at    DATETIME NOT NULL,                   -- e.g. now + 5 minutes
  verified_at   DATETIME NULL,
  ip_address    VARCHAR(45) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_otp_lookup (mobile, purpose, expires_at),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. PASSWORD RESET via email link
CREATE TABLE password_resets (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,                     -- hash('sha256', $token); email the raw token
  expires_at  DATETIME NOT NULL,                     -- e.g. now + 30 minutes
  used_at     DATETIME NULL,
  ip_address  VARCHAR(45) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reset_token (token_hash),
  KEY idx_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. SESSIONS / remember-me tokens (optional but recommended for signin)
CREATE TABLE user_sessions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  login_method ENUM('password','otp') NOT NULL,
  user_agent   VARCHAR(255) NULL,
  ip_address   VARCHAR(45) NULL,
  expires_at   DATETIME NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_session_token (token_hash),
  KEY idx_session_user (user_id),
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. EMPLOYEE (job seeker) profile
--    dob is stored instead of age; calculate age in PHP/SQL with TIMESTAMPDIFF.
CREATE TABLE employee (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             BIGINT UNSIGNED NOT NULL,
  name                VARCHAR(100) NOT NULL,
  dob                 DATE NOT NULL,
  location            VARCHAR(150) NOT NULL,
  nationality         VARCHAR(80)  NOT NULL,
  qualification       VARCHAR(150) NOT NULL,
  experience_years    DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  expected_salary     DECIMAL(12,2) NULL,
  salary_currency     CHAR(3) NOT NULL DEFAULT 'INR',
  employment_type     ENUM('full_time','part_time','contract','internship','freelance') NOT NULL,
  industries          JSON NULL,                     -- e.g. ["IT","Finance"]
  picture_path        VARCHAR(255) NULL,
  cv_path             VARCHAR(255) NULL,
  cv_original_name    VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_employee_user (user_id),
  CONSTRAINT fk_employee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. EMPLOYER (company) profile
CREATE TABLE employer (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          BIGINT UNSIGNED NOT NULL,
  company          VARCHAR(150) NOT NULL,
  location         VARCHAR(150) NOT NULL,
  industry         VARCHAR(100) NOT NULL,
  position         VARCHAR(150) NOT NULL,            -- position being hired for
  employment_type  ENUM('full_time','part_time','contract','internship','freelance') NOT NULL,
  salary           DECIMAL(12,2) NULL,
  salary_currency  CHAR(3) NOT NULL DEFAULT 'INR',
  job_detail       TEXT NOT NULL,
  logo_path        VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_employer_user (user_id),
  CONSTRAINT fk_employer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
