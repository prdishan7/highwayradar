-- SQLite database schema for highway gbbs
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'driver' CHECK (
        role IN (
            'superadmin',
            'admin',
            'driver',
            'local'
        )
    ),
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS incidents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category TEXT NOT NULL CHECK (
        category IN (
            'collision',
            'landslide',
            'flooding',
            'obstacle',
            'pothole',
            'fire',
            'sos',
            'other'
        )
    ),
    severity TEXT NOT NULL CHECK (
        severity IN ('low', 'medium', 'high')
    ),
    description TEXT,
    latitude REAL,
    longitude REAL,
    image_base64 TEXT,
    status TEXT NOT NULL DEFAULT 'new' CHECK (
        status IN (
            'new',
            'verified',
            'false',
            'resolved'
        )
    ),
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_incidents_user_id ON incidents (user_id);

CREATE TABLE IF NOT EXISTS highway_status (
    id INTEGER PRIMARY KEY,
    status_text TEXT NOT NULL,
    source TEXT NOT NULL,
    overridden_by_admin INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT,
    payload TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user will be auto-seeded by the API on first request
-- Email: admin@gmail.com
-- Password: admin123