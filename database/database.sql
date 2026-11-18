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

CREATE TABLE IF NOT EXISTS products (
  product_id VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  category VARCHAR(60) NOT NULL,
  price INT UNSIGNED NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  description VARCHAR(180) NOT NULL DEFAULT '',
  badge VARCHAR(28) NOT NULL DEFAULT '',
  image VARCHAR(180) NOT NULL DEFAULT '',
  color VARCHAR(30) NOT NULL DEFAULT 'lilac',
  icon VARCHAR(16) NOT NULL DEFAULT '🎨',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id),
  UNIQUE KEY products_name_unique (name),
  KEY products_category_index (category),
  KEY products_stock_index (stock)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  order_id VARCHAR(20) NOT NULL,
  customer_user_id BIGINT UNSIGNED NULL,
  guest_token_hash CHAR(64) NULL,
  customer_name VARCHAR(90) NOT NULL,
  customer_email VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL DEFAULT '',
  customer_city VARCHAR(60) NOT NULL DEFAULT '',
  customer_address VARCHAR(120) NOT NULL DEFAULT '',
  customer_notes VARCHAR(500) NOT NULL DEFAULT '',
  sold_by_user_id BIGINT UNSIGNED NULL,
  subtotal INT UNSIGNED NOT NULL,
  shipping INT UNSIGNED NOT NULL,
  total INT UNSIGNED NOT NULL,
  status ENUM('Pedido recibido', 'En preparación', 'Enviado', 'Entregado') NOT NULL DEFAULT 'Pedido recibido',
  payment VARCHAR(60) NOT NULL DEFAULT 'Pago contra entrega',
  is_demo BOOLEAN NOT NULL DEFAULT FALSE,
  is_test BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (order_id),
  KEY orders_customer_index (customer_user_id),
  KEY orders_seller_index (sold_by_user_id),
  KEY orders_guest_token_index (guest_token_hash),
  KEY orders_email_index (customer_email),
  KEY orders_status_date_index (status, created_at),
  CONSTRAINT orders_customer_fk FOREIGN KEY (customer_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT orders_seller_fk FOREIGN KEY (sold_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id VARCHAR(20) NOT NULL,
  product_id VARCHAR(50) NULL,
  product_name VARCHAR(100) NOT NULL,
  unit_price INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY order_items_order_index (order_id),
  KEY order_items_product_index (product_id),
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products (product_id) ON DELETE SET NULL
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

-- Perfiles de prueba: contraseña compartida Prueba2026!. Cámbiala o elimina estas cuentas antes de producción.
INSERT INTO users (name, email, password_hash, role, phone, city, address)
VALUES
  ('Prueba Administrador 1', 'admin.prueba1@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'administrador', '', '', ''),
  ('Prueba Administrador 2', 'admin.prueba2@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'administrador', '', '', ''),
  ('Prueba Operador 1', 'operador.prueba1@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'operador', '', '', ''),
  ('Prueba Operador 2', 'operador.prueba2@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'operador', '', '', ''),
  ('Prueba Consulta 1', 'consulta.prueba1@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'consulta', '', '', ''),
  ('Prueba Consulta 2', 'consulta.prueba2@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'consulta', '', '', ''),
  ('Prueba Cliente 1', 'cliente.prueba1@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'cliente', '3005550101', 'Bogotá', 'Calle 1 # 1-01'),
  ('Prueba Cliente 2', 'cliente.prueba2@artesoft.local', '$2y$10$V3.6kNpJS/.ZqOFu69bekeg/vyJ9QTEqLORxP3WxZCMxeZ0rm7MDe', 'cliente', '3005550102', 'Bogotá', 'Calle 2 # 2-02')
ON DUPLICATE KEY UPDATE email = VALUES(email);
