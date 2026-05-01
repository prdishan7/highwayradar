# Database Schema (MySQL)

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','driver','local') NOT NULL DEFAULT 'driver',
  created_at DATETIME NOT NULL
);

CREATE TABLE incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category ENUM('collision','landslide','flooding','obstacle','pothole','fire','sos','other') NOT NULL,
  severity ENUM('low','medium','high') NOT NULL,
  description TEXT,
  image_base64 LONGTEXT,
  status ENUM('new','verified','false','resolved') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL,
  INDEX(user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE highway_status (
  id TINYINT PRIMARY KEY DEFAULT 1,
  status_text VARCHAR(120) NOT NULL,
  source VARCHAR(32) NOT NULL,
  overridden_by_admin TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL
);

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(128),
  payload JSON,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

Defaults:
- Seeded admin: `admin@gmail.com / admin123` (bcrypt), inserted at runtime if missing.
- `highway_status` row id=1 is upserted by the API.

RTDB (Firebase) remains minimal:
```
live/sensor   -> { soil, rain, riskIndex, riskLevel, timestampMs }
alerts/sos    -> { active, level, timestampMs }
```
