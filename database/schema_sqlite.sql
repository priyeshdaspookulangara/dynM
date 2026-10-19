-- SQLite Database Schema for Multi-Tier Direct-Selling App

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS level_commissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    level_number INTEGER NOT NULL UNIQUE,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user', -- 'user', 'admin', 'super_admin'
    sponsor_id INTEGER DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- 'active', 'inactive'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sponsor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS packages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    direct_referral_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    direct_referral_type VARCHAR(20) NOT NULL DEFAULT 'percent', -- 'percent' or 'fixed'
    direct_referral_fixed DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    wallet_user_split DECIMAL(5,2) NOT NULL DEFAULT 60.00, -- e.g. 60%
    wallet_company_split DECIMAL(5,2) NOT NULL DEFAULT 40.00, -- e.g. 40%
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS epins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(50) NOT NULL UNIQUE,
    package_id INTEGER NOT NULL,
    created_by_user_id INTEGER NOT NULL,
    assigned_to_user_id INTEGER DEFAULT NULL,
    used_by_user_id INTEGER DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unused', -- 'unused', 'used', 'revoked'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME DEFAULT NULL,
    FOREIGN KEY (package_id) REFERENCES packages(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id),
    FOREIGN KEY (used_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS matrix_nodes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    parent_id INTEGER DEFAULT NULL,
    sponsor_id INTEGER NOT NULL,
    position INTEGER DEFAULT 1, -- position relative to parent (1..width)
    depth INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES matrix_nodes(user_id),
    FOREIGN KEY (sponsor_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wallets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    user_wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    company_wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'credit', 'debit'
    category VARCHAR(50) NOT NULL, -- 'direct_referral', 'matrix_level', 'epin_purchase', 'withdrawal', 'company_retention'
    wallet_type VARCHAR(20) NOT NULL DEFAULT 'user', -- 'user', 'company'
    description TEXT,
    reference_id VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS payouts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    notes TEXT DEFAULT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
