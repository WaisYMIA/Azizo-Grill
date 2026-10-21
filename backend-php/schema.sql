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
  cost_price INT NOT NULL DEFAULT 0,
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
  notes VARCHAR(300) NULL,
  area VARCHAR(80) NULL,
  recurring VARCHAR(16) NULL,
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
  cost_price INT NOT NULL DEFAULT 0,
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

-- Reporting (v1.2): business expenses and a status history used for "time to complete" figures.
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category VARCHAR(64) NOT NULL,
  description VARCHAR(255) NULL,
  amount INT NOT NULL,
  branch VARCHAR(128) NULL,
  paid_via VARCHAR(40) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expenses_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_status_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  status VARCHAR(32) NOT NULL,
  changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_statuslog_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Delivery areas (v1.7): every Nairobi region split into zones; admin ticks the ones served.
CREATE TABLE IF NOT EXISTS areas (
  id VARCHAR(50) PRIMARY KEY,
  region VARCHAR(80) NOT NULL,
  name VARCHAR(80) NOT NULL,
  corridor VARCHAR(60) NOT NULL DEFAULT '',
  lat DOUBLE NOT NULL,
  lng DOUBLE NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS area_settings (
  skey VARCHAR(40) PRIMARY KEY,
  svalue TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- v1.8: per-branch stock (only what is OUT at a branch is stored) and verified reviews.
CREATE TABLE IF NOT EXISTS branch_stock_out (
  branch_id VARCHAR(40) NOT NULL,
  product_id VARCHAR(64) NOT NULL,
  PRIMARY KEY (branch_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL UNIQUE,
  name VARCHAR(80) NULL,
  rating TINYINT NOT NULL,
  comment VARCHAR(700) NULL,
  photo VARCHAR(200) NULL,
  branch VARCHAR(80) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  reply VARCHAR(500) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
