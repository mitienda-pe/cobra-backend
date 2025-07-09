-- Database structure for cobra backend
PRAGMA foreign_keys = ON;

-- Organizations table
CREATE TABLE IF NOT EXISTS organizations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    status VARCHAR(20) DEFAULT 'active',
    remember_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expires_at DATETIME,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Portfolios table
CREATE TABLE IF NOT EXISTS portfolios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    uuid VARCHAR(36),
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active',
    md5_hash VARCHAR(32),
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Clients table
CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    uuid VARCHAR(36),
    business_name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255),
    document_number VARCHAR(20),
    contact_name VARCHAR(100),
    contact_phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    ubigeo VARCHAR(10),
    zip_code VARCHAR(20),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Portfolio user relationship
CREATE TABLE IF NOT EXISTS portfolio_user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    portfolio_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE(portfolio_id, user_id)
);

-- Portfolio clients relationship
CREATE TABLE IF NOT EXISTS portfolio_clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    portfolio_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE(portfolio_id, client_id)
);

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    external_id VARCHAR(36),
    invoice_number VARCHAR(50) NOT NULL,
    concept VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'PEN',
    issue_date DATE,
    due_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Instalments table
CREATE TABLE IF NOT EXISTS instalments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid VARCHAR(36) NOT NULL,
    invoice_id INTEGER NOT NULL,
    number INTEGER NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER,
    instalment_id INTEGER,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    method VARCHAR(50),
    reference VARCHAR(100),
    status VARCHAR(20) DEFAULT 'completed',
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (instalment_id) REFERENCES instalments(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- User API tokens table
CREATE TABLE IF NOT EXISTS user_api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name VARCHAR(100),
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME,
    revoked BOOLEAN DEFAULT FALSE,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- User OTP codes table
CREATE TABLE IF NOT EXISTS user_otp_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phone VARCHAR(20),
    email VARCHAR(255),
    code VARCHAR(10),
    organization_code VARCHAR(50),
    device_info VARCHAR(255),
    expires_at DATETIME,
    used_at DATETIME,
    delivery_method VARCHAR(20),
    delivery_status VARCHAR(20),
    delivery_info TEXT,
    created_at DATETIME
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
CREATE INDEX IF NOT EXISTS idx_invoices_client ON invoices(client_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_instalments_invoice ON instalments(invoice_id);
CREATE INDEX IF NOT EXISTS idx_payments_invoice ON payments(invoice_id);
CREATE INDEX IF NOT EXISTS idx_payments_instalment ON payments(instalment_id);