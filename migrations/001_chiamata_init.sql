-- Chiamata: table-call system (call the waiter, ask for the bill, PDF menu).
-- One QR per table, fixed; the code given by the waiter changes at every bill closure.

CREATE TABLE IF NOT EXISTS app_settings (
    k VARCHAR(64) NOT NULL PRIMARY KEY,
    v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#0f766e',
    welcome_text VARCHAR(500) NULL,
    logo_file VARCHAR(100) NULL,
    menu_file VARCHAR(100) NULL,
    menu_url VARCHAR(500) NULL,
    code_length TINYINT NOT NULL DEFAULT 4,
    bill_enabled TINYINT(1) NOT NULL DEFAULT 1,
    bill_ask_payment TINYINT(1) NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff. venue_id is NULL only for the superadmin. Users are never deleted, only disabled.
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NULL,
    role ENUM('superadmin','manager','waiter') NOT NULL DEFAULT 'waiter',
    name VARCHAR(100) NOT NULL,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    zones TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_venue (venue_id),
    CONSTRAINT fk_users_venue FOREIGN KEY (venue_id) REFERENCES venues (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Remember me" logins, so the waiter app (and its push notifications) stay signed in.
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_selector (selector),
    KEY idx_auth_user (user_id),
    CONSTRAINT fk_auth_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    label VARCHAR(40) NOT NULL,
    zone VARCHAR(60) NULL,
    qr_token VARCHAR(16) NOT NULL,
    current_session_id INT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tables_token (qr_token),
    KEY idx_tables_venue (venue_id),
    CONSTRAINT fk_tables_venue FOREIGN KEY (venue_id) REFERENCES venues (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per "customer at the table": the code is valid until the bill is closed.
CREATE TABLE IF NOT EXISTS table_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    table_id INT NOT NULL,
    code VARCHAR(8) NOT NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    closed_by INT NULL,
    KEY idx_sessions_table (table_id),
    CONSTRAINT fk_sessions_table FOREIGN KEY (table_id) REFERENCES venue_tables (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    table_id INT NOT NULL,
    session_id INT NOT NULL,
    type ENUM('waiter','bill') NOT NULL,
    payment ENUM('cash','card') NULL,
    status ENUM('open','taken','done','cancelled') NOT NULL DEFAULT 'open',
    repeat_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_call_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    taken_by INT NULL,
    taken_at DATETIME NULL,
    done_by INT NULL,
    done_at DATETIME NULL,
    KEY idx_calls_venue_status (venue_id, status),
    KEY idx_calls_session (session_id),
    KEY idx_calls_venue_created (venue_id, created_at),
    CONSTRAINT fk_calls_table FOREIGN KEY (table_id) REFERENCES venue_tables (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wrong codes typed by guests (brute-force limit).
CREATE TABLE IF NOT EXISTS code_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_ok_at DATETIME NULL,
    UNIQUE KEY uq_push_endpoint (endpoint_hash),
    KEY idx_push_user (user_id),
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
