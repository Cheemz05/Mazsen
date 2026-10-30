-- Import in phpMyAdmin to create the MazSen Munch database and tables.
-- Safe to re-import: existing customer, admin, and order records are preserved.
CREATE DATABASE IF NOT EXISTS munch_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE munch_db;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  address VARCHAR(500) NOT NULL,
  profile_image VARCHAR(255) NULL DEFAULT NULL,
  google_sub VARCHAR(255) NULL DEFAULT NULL,
  facebook_id VARCHAR(255) NULL DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customers_google_sub (google_sub),
  UNIQUE KEY uq_customers_facebook_id (facebook_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_riders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  current_latitude DECIMAL(10,7) NULL DEFAULT NULL,
  current_longitude DECIMAL(10,7) NULL DEFAULT NULL,
  location_updated_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_delivery_riders_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  is_open TINYINT(1) NOT NULL DEFAULT 1,
  rider_payout DECIMAL(10,2) NOT NULL DEFAULT 50.00,
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
  image_path VARCHAR(255) NULL DEFAULT NULL,
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
  customer_latitude DECIMAL(10,7) NULL DEFAULT NULL,
  customer_longitude DECIMAL(10,7) NULL DEFAULT NULL,
  order_number VARCHAR(24) NOT NULL UNIQUE,
  status VARCHAR(32) NOT NULL DEFAULT 'Pending',
  assigned_rider_id BIGINT UNSIGNED NULL DEFAULT NULL,
  rider_offer_status VARCHAR(24) NULL DEFAULT NULL,
  rider_offer_at DATETIME NULL DEFAULT NULL,
  rider_accepted_at DATETIME NULL DEFAULT NULL,
  rider_payout DECIMAL(10,2) NULL DEFAULT NULL,
  rider_token_hash CHAR(64) NULL DEFAULT NULL,
  rider_latitude DECIMAL(10,7) NULL DEFAULT NULL,
  rider_longitude DECIMAL(10,7) NULL DEFAULT NULL,
  rider_location_updated_at DATETIME NULL DEFAULT NULL,
  total DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_orders_customer_created (customer_id, created_at),
  INDEX idx_orders_created (created_at),
  INDEX idx_orders_status (status),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_rider_offers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  rider_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'Offered',
  payout_amount DECIMAL(10,2) NOT NULL,
  distance_km DECIMAL(8,2) NULL DEFAULT NULL,
  offered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at DATETIME NULL DEFAULT NULL,
  UNIQUE KEY uq_order_rider_offer (order_id, rider_id),
  INDEX idx_rider_offer_inbox (rider_id, status, offered_at),
  CONSTRAINT fk_rider_offer_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_rider_offer_rider FOREIGN KEY (rider_id) REFERENCES delivery_riders(id) ON DELETE CASCADE
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

-- Add columns required by this app to an older orders table without deleting rows.
DROP PROCEDURE IF EXISTS upgrade_order_columns;
DELIMITER //
CREATE PROCEDURE upgrade_order_columns()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_settings' AND COLUMN_NAME='rider_payout') THEN
    ALTER TABLE store_settings ADD COLUMN rider_payout DECIMAL(10,2) NOT NULL DEFAULT 50.00 AFTER is_open;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='delivery_riders' AND COLUMN_NAME='password_hash') THEN
    ALTER TABLE delivery_riders ADD COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL AFTER phone;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='profile_image') THEN
    ALTER TABLE customers ADD COLUMN profile_image VARCHAR(255) NULL DEFAULT NULL AFTER address;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='google_sub') THEN
    ALTER TABLE customers ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL AFTER profile_image;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='facebook_id') THEN
    ALTER TABLE customers ADD COLUMN facebook_id VARCHAR(255) NULL DEFAULT NULL AFTER google_sub;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND INDEX_NAME='uq_customers_google_sub') THEN
    CREATE UNIQUE INDEX uq_customers_google_sub ON customers (google_sub);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND INDEX_NAME='uq_customers_facebook_id') THEN
    CREATE UNIQUE INDEX uq_customers_facebook_id ON customers (facebook_id);
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
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='customer_latitude') THEN
    ALTER TABLE orders ADD COLUMN customer_latitude DECIMAL(10,7) NULL DEFAULT NULL AFTER delivery_address;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='customer_longitude') THEN
    ALTER TABLE orders ADD COLUMN customer_longitude DECIMAL(10,7) NULL DEFAULT NULL AFTER customer_latitude;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_offer_status') THEN
    ALTER TABLE orders ADD COLUMN rider_offer_status VARCHAR(24) NULL DEFAULT NULL AFTER assigned_rider_id;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_offer_at') THEN
    ALTER TABLE orders ADD COLUMN rider_offer_at DATETIME NULL DEFAULT NULL AFTER rider_offer_status;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_accepted_at') THEN
    ALTER TABLE orders ADD COLUMN rider_accepted_at DATETIME NULL DEFAULT NULL AFTER rider_offer_at;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_payout') THEN
    ALTER TABLE orders ADD COLUMN rider_payout DECIMAL(10,2) NULL DEFAULT NULL AFTER rider_accepted_at;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_token_hash') THEN
    ALTER TABLE orders ADD COLUMN rider_token_hash CHAR(64) NULL DEFAULT NULL AFTER status;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='assigned_rider_id') THEN
    ALTER TABLE orders ADD COLUMN assigned_rider_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER status;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_latitude') THEN
    ALTER TABLE orders ADD COLUMN rider_latitude DECIMAL(10,7) NULL DEFAULT NULL AFTER rider_token_hash;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_longitude') THEN
    ALTER TABLE orders ADD COLUMN rider_longitude DECIMAL(10,7) NULL DEFAULT NULL AFTER rider_latitude;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='rider_location_updated_at') THEN
    ALTER TABLE orders ADD COLUMN rider_location_updated_at DATETIME NULL DEFAULT NULL AFTER rider_longitude;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_orders_customer_created') THEN
    CREATE INDEX idx_orders_customer_created ON orders (customer_id, created_at);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_orders_assigned_rider_status') THEN
    CREATE INDEX idx_orders_assigned_rider_status ON orders (assigned_rider_id, status);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND CONSTRAINT_NAME='fk_orders_customer') THEN
    ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND CONSTRAINT_NAME='fk_orders_assigned_rider') THEN
    ALTER TABLE orders ADD CONSTRAINT fk_orders_assigned_rider FOREIGN KEY (assigned_rider_id) REFERENCES delivery_riders(id) ON DELETE SET NULL;
  END IF;
END//
DELIMITER ;
CALL upgrade_order_columns();
DROP PROCEDURE upgrade_order_columns;

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

UPDATE products SET image_path = CASE slug
  WHEN 'classic-burger' THEN 'Photos/fitted-4x3-fill/Classic Burger.png'
  WHEN 'omg-overload' THEN 'Photos/fitted-4x3-fill/OMG Overload.png'
  WHEN 'classic-hotdog' THEN 'Photos/fitted-4x3-fill/Classic Hotdog Sandwich.png'
  WHEN 'loaded-footlong' THEN 'Photos/fitted-4x3-fill/Loaded Footlong.png'
  WHEN 'classic-sandwich' THEN 'Photos/fitted-4x3-fill/Ham Sandwich.png'
  WHEN 'chicken-poppers-rice' THEN 'Photos/fitted-4x3-fill/Chicken Poppers Rice.png'
  WHEN 'hamsilog' THEN 'Photos/fitted-4x3-fill/Hamsilog.png'
  WHEN 'pork-tapsilog' THEN 'Photos/fitted-4x3-fill/Pork Tapsilog.png'
  WHEN 'longganisilog' THEN 'Photos/fitted-4x3-fill/Longganisilog.png'
  ELSE image_path
END
WHERE slug IN ('classic-burger','omg-overload','classic-hotdog','loaded-footlong','classic-sandwich','chicken-poppers-rice','hamsilog','pork-tapsilog','longganisilog');
