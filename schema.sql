-- Terrava — panel i CRM. MySQL 5.7+ / MariaDB 10.3+, kodowanie utf8mb4.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','opiekun','analityk','czlonek','wlasciciel') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_pw TINYINT(1) NOT NULL DEFAULT 1,
  investor_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS investors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  company VARCHAR(160) NULL,
  city VARCHAR(80) NULL,
  source VARCHAR(120) NULL,
  stage ENUM('kontakt','rozmowa','zaproszony','czlonek','wstrzymany','odrzucony') NOT NULL DEFAULT 'kontakt',
  owner_user_id INT UNSIGNED NULL,
  next_action VARCHAR(200) NULL,
  next_action_date DATE NULL,
  member_since DATE NULL,
  fee_until DATE NULL,
  nda TINYINT(1) NOT NULL DEFAULT 0,
  survey TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (stage), INDEX (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  location VARCHAR(200) NULL,
  kind ENUM('projekt','grunt') NOT NULL DEFAULT 'projekt',
  stage ENUM('zgloszenie','ocena','weryfikacja','komitet','prezentacja','realizacja','zakonczony','odrzucony') NOT NULL DEFAULT 'zgloszenie',
  package ENUM('','weryfikacja','prezentacja','premium') NOT NULL DEFAULT '',
  company VARCHAR(200) NULL,
  contact_name VARCHAR(160) NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(40) NULL,
  capital_text VARCHAR(120) NULL,
  ticket_text VARCHAR(120) NULL,
  horizon_text VARCHAR(120) NULL,
  form_text VARCHAR(160) NULL,
  security_text VARCHAR(200) NULL,
  summary TEXT NULL,
  risks TEXT NULL,
  committee_date DATE NULL,
  published TINYINT(1) NOT NULL DEFAULT 0,
  analyst_user_id INT UNSIGNED NULL,
  owner_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (stage), INDEX (published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS project_docs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  status ENUM('brak','przeslany','przyjety','do_poprawy') NOT NULL DEFAULT 'brak',
  file_name VARCHAR(200) NULL,
  stored_name VARCHAR(80) NULL,
  visible TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_at DATETIME NULL,
  INDEX (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS interests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  message TEXT NULL,
  status ENUM('nowe','przekazane','zamkniete') NOT NULL DEFAULT 'nowe',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_per_user (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity ENUM('investor','project') NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS downloads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  ip VARCHAR(45) NULL,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  ip VARCHAR(45) NOT NULL PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
