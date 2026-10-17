-- Azizo Butchery & Grill — MySQL schema.
-- Run automatically on every request (see db.php) — importing it by hand via
-- phpMyAdmin first is optional, not required.

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(32) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(64) PRIMARY KEY,
  category_group VARCHAR(64) NOT NULL,
  cat VARCHAR(64) NOT NULL,
  kind VARCHAR(64) NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  price INT NOT NULL,
  unit VARCHAR(32) NOT NULL,
  img VARCHAR(512) NULL,
  badges VARCHAR(512) NULL,
  origin VARCHAR(255) NULL,
  in_stock TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NULL,
  customer_name VARCHAR(255) NULL,
  customer_phone VARCHAR(32) NOT NULL,
  fulfilment VARCHAR(32) NOT NULL,
  branch VARCHAR(128) NULL,
  pickup_time VARCHAR(64) NULL,
  delivery_address VARCHAR(512) NULL,
  delivery_lat DOUBLE NULL,
  delivery_lng DOUBLE NULL,
  delivery_fee INT NOT NULL DEFAULT 0,
  payment_method VARCHAR(32) NOT NULL,
  mpesa_receipt VARCHAR(64) NULL,
  promo_code VARCHAR(64) NULL,
  items_subtotal INT NOT NULL,
  total INT NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'received',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id VARCHAR(64) NULL,
  name VARCHAR(255) NOT NULL,
  qty INT NOT NULL,
  unit VARCHAR(32) NULL,
  unit_price INT NOT NULL,
  line_total INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mpesa_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NULL,
  checkout_request_id VARCHAR(128) NULL,
  phone VARCHAR(32) NULL,
  amount INT NULL,
  status VARCHAR(32) DEFAULT 'pending',
  mpesa_receipt VARCHAR(64) NULL,
  raw_callback TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS branches (
  id VARCHAR(40) PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  area VARCHAR(60) NULL,
  address VARCHAR(160) NULL,
  `query` VARCHAR(200) NULL,
  lat DOUBLE NULL,
  lng DOUBLE NULL,
  phone VARCHAR(24) NOT NULL DEFAULT '',
  hours VARCHAR(60) NOT NULL DEFAULT 'Open 24 hours',
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  listed TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
