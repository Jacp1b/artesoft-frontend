CREATE DATABASE IF NOT EXISTS artesoft
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE artesoft;

CREATE TABLE IF NOT EXISTS app_state (
  state_key VARCHAR(80) NOT NULL,
  payload JSON NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (state_key)
) ENGINE=InnoDB;

INSERT IGNORE INTO app_state (state_key, payload)
VALUES ('artesoft:v3:productos', JSON_ARRAY()), ('artesoft:v3:pedidos', JSON_ARRAY()), ('artesoft:v3:initialized', JSON_ARRAY());

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(90) NOT NULL,
  email VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('administrador', 'operador', 'consulta', 'cliente') NOT NULL DEFAULT 'cliente',
  phone VARCHAR(20) NOT NULL DEFAULT '',
  city VARCHAR(60) NOT NULL DEFAULT '',
  address VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  KEY users_role_index (role)
) ENGINE=InnoDB;

-- Cuenta inicial: admin@artesoft.local / Demo1234! Cambia la contraseña tras el primer acceso.
INSERT INTO users (name, email, password_hash, role)
VALUES (
  'Administración ARTESOFT',
  'admin@artesoft.local',
  '$2y$10$UTPOk7OFCAGAtVz4nuL2s.gvgrWSmMxkGQC99TzPgvnFxICajCjki',
  'administrador'
)
ON DUPLICATE KEY UPDATE email = VALUES(email);
