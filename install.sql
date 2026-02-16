-- Simple time tracking app (UTF8MB4)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS time_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at   DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_started (user_id, started_at),
  CONSTRAINT fk_user_time FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_order CHECK (ended_at IS NULL OR ended_at >= started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: create an initial admin (update password after install)
-- INSERT INTO users(email, password_hash, full_name, role)
-- VALUES ('admin@example.com', '$2y$10$Kc0k1m1k9o2i9mKQy0k2Iu2zQ2yXz0l1q5gS7S2iH2w8wC0QfH4r2', 'Admin', 'admin');
-- The above hash is for password: "admin123" (change immediately).