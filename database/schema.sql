-- Import this ONE file in phpMyAdmin. It creates and updates the single app database.
CREATE DATABASE IF NOT EXISTS munch_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE munch_db;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  address VARCHAR(500) NOT NULL,
  profile_image VARCHAR(255) NULL DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  is_open TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO store_settings (id, is_open) VALUES (1, 1);

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(80) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  emoji VARCHAR(24) NOT NULL,
  description VARCHAR(255) NOT NULL,
  popular TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_category_active (category, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NULL,
  customer_name VARCHAR(120) NULL,
  customer_phone VARCHAR(30) NULL,
  delivery_address VARCHAR(500) NULL,
  order_number VARCHAR(24) NOT NULL UNIQUE,
  status VARCHAR(32) NOT NULL DEFAULT 'Pending',
  total DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_orders_customer_created (customer_id, created_at),
  INDEX idx_orders_created (created_at),
  INDEX idx_orders_status (status),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(120) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  INDEX idx_order_items_product (product_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade an older orders table in place, preserving all existing orders.
DROP PROCEDURE IF EXISTS upgrade_order_customer_columns;
DELIMITER //
CREATE PROCEDURE upgrade_order_customer_columns()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='profile_image') THEN
    ALTER TABLE customers ADD COLUMN profile_image VARCHAR(255) NULL DEFAULT NULL AFTER address;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='customer_id') THEN
    ALTER TABLE orders ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER id;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='customer_name') THEN
    ALTER TABLE orders ADD COLUMN customer_name VARCHAR(120) NULL AFTER customer_id;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='customer_phone') THEN
    ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='delivery_address') THEN
    ALTER TABLE orders ADD COLUMN delivery_address VARCHAR(500) NULL AFTER customer_phone;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_orders_customer_created') THEN
    CREATE INDEX idx_orders_customer_created ON orders (customer_id, created_at);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND CONSTRAINT_NAME='fk_orders_customer') THEN
    ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
  END IF;
END//
DELIMITER ;
CALL upgrade_order_customer_columns();
DROP PROCEDURE upgrade_order_customer_columns;

INSERT INTO products (slug,name,category,price,emoji,description,popular,sort_order) VALUES
('classic-burger','Classic Burger','Burgers',55,'🍔','Juicy patty, fresh lettuce, tomato and our house sauce.',1,1),
('omg-overload','OMG Overload','Burgers',95,'🍔','Burger, ham, egg, mayo, ketchup and TLC.',1,2),
('classic-hotdog','Classic Hotdog Sandwich','Burgers',75,'🌭','TJ jumbo, mayo, ketchup and crisp coleslaw.',1,3),
('loaded-footlong','Loaded Footlong','Burgers',265,'🌭','Footlong with burger, egg, mayo, cheese and TLC.',0,4),
('chicken-poppers-rice','Chicken Poppers Rice','Silog Meals',95,'🍗','Crispy chicken poppers, rice and your choice of flavor.',1,5),
('hamsilog','Hamsilog','Silog Meals',80,'🍳','Ham, garlic rice and a sunny side up egg.',1,6),
('pork-tapsilog','Pork Tapsilog','Silog Meals',85,'🍳','Savory pork tapa with garlic rice and egg.',0,7),
('longganisilog','Longganisilog','Silog Meals',95,'🍳','Filipino longganisa, rice and egg, all in one plate.',0,8),
('chick-pop-n-fries',"Chick Pop 'n Fries",'Snacks',99,'🍟','Crispy chicken poppers over fries, drizzled with garlic mayo.',1,9),
('cheese-sticks','Cheese Sticks','Snacks',50,'🧀','Nine golden, crunchy cheese sticks.',0,10),
('dumplings','Dumplings','Snacks',50,'🥟','Nine pieces, served hot and ready to share.',0,11),
('dynamite','Dynamite','Snacks',55,'🌶️','Three crunchy, cheesy chili poppers.',0,12),
('regular-fries','Regular Fries','Fries',30,'🍟','Golden fries. Add cheese, BBQ or sour cream flavor.',1,13),
('medium-fries','Medium Fries','Fries',65,'🍟','A bigger serving of golden, crispy fries.',0,14),
('large-fries','Large Fries','Fries',95,'🍟','Our biggest fries for sharing or keeping.',0,15),
('classic-hungarian','Classic Hungarian Sandwich','Hungarian & Footlong',65,'🌭','Hungarian sausage with mayo, ketchup and coleslaw.',0,16),
('cheesy-footlong','Cheesy Footlong Sandwich','Hungarian & Footlong',155,'🌭','A jumbo footlong with a cheesy, savory finish.',0,17),
('classic-sandwich','Ham Sandwich','Burgers',55,'🥪','Ham, mayo, ketchup and coleslaw.',0,18),
('plain-rice','Plain Rice','Add-ons',15,'🍚','A warm serving of steamed white rice.',0,19),
('garlic-rice','Garlic Rice','Add-ons',20,'🍚','Savory garlic fried rice.',0,20),
('egg','Egg','Add-ons',15,'🍳','Add a freshly cooked egg to your meal.',0,21),
('burger-patty','Burger Patty','Add-ons',20,'🍔','Add an extra burger patty to your meal.',0,22)
ON DUPLICATE KEY UPDATE name=VALUES(name),category=VALUES(category),price=VALUES(price),emoji=VALUES(emoji),description=VALUES(description),popular=VALUES(popular),sort_order=VALUES(sort_order);
