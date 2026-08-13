-- ============================================================
-- CampusMart - Student-to-Student Campus Marketplace
-- Database schema + demo seed data
-- Import via phpMyAdmin or:  mysql -u root < campusmart.sql
-- ============================================================

DROP DATABASE IF EXISTS campusmart;
CREATE DATABASE IF NOT EXISTS campusmart
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE campusmart;

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(100) NOT NULL,
  student_id VARCHAR(30) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  department VARCHAR(100) DEFAULT NULL,
  batch VARCHAR(30) DEFAULT NULL,
  hall VARCHAR(100) DEFAULT NULL,
  profile_image VARCHAR(255) DEFAULT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_student_id (student_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status (status),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Categories
-- ------------------------------------------------------------
CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Products
-- ------------------------------------------------------------
CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  condition_label ENUM('New','Like New','Good','Fair','Used') NOT NULL DEFAULT 'Good',
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  location VARCHAR(150) NOT NULL,
  contact_preference ENUM('In-app message','Phone','Both') NOT NULL DEFAULT 'In-app message',
  status ENUM('draft','pending','approved','active','reserved','sold','expired','rejected','removed') NOT NULL DEFAULT 'pending',
  reject_reason VARCHAR(500) DEFAULT NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_seller (seller_id),
  KEY idx_products_category (category_id),
  KEY idx_products_status (status),
  KEY idx_products_created (created_at),
  KEY idx_products_price (price),
  KEY idx_products_expires (expires_at),
  FULLTEXT KEY ft_products_search (title, description),
  CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT chk_products_price CHECK (price >= 0),
  CONSTRAINT chk_products_quantity CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Product images
-- ------------------------------------------------------------
CREATE TABLE product_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_product_images_product (product_id),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Favorites
-- ------------------------------------------------------------
CREATE TABLE favorites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_favorites_user_product (user_id, product_id),
  KEY idx_favorites_product (product_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Orders
-- ------------------------------------------------------------
CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_code VARCHAR(20) NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  seller_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('cash','direct') NOT NULL DEFAULT 'cash',
  payment_status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  status ENUM('pending','accepted','ready','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  pickup_location VARCHAR(200) DEFAULT NULL,
  pickup_time VARCHAR(150) DEFAULT NULL,
  status_history TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  cancelled_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_code (order_code),
  KEY idx_orders_buyer (buyer_id),
  KEY idx_orders_seller (seller_id),
  KEY idx_orders_product (product_id),
  KEY idx_orders_status (status),
  CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT chk_orders_quantity CHECK (quantity > 0),
  CONSTRAINT chk_orders_total CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Conversations
-- ------------------------------------------------------------
CREATE TABLE conversations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_a INT UNSIGNED NOT NULL,
  user_b INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  last_message_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conversations_pair (user_a, user_b),
  KEY idx_conversations_product (product_id),
  CONSTRAINT fk_conversations_user_a FOREIGN KEY (user_a) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversations_user_b FOREIGN KEY (user_b) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversations_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Messages
-- ------------------------------------------------------------
CREATE TABLE messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_messages_conversation (conversation_id, created_at),
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Notifications
-- ------------------------------------------------------------
CREATE TABLE notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT,
  related_id INT UNSIGNED DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_read (user_id, is_read),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reviews
-- ------------------------------------------------------------
CREATE TABLE reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reviewer_id INT UNSIGNED NOT NULL,
  reviewed_user_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT,
  status ENUM('approved','removed') NOT NULL DEFAULT 'approved',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_order_reviewer (order_id, reviewer_id),
  KEY idx_reviews_reviewed (reviewed_user_id, status),
  CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_reviews_reviewed FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reports
-- ------------------------------------------------------------
CREATE TABLE reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id INT UNSIGNED NOT NULL,
  reported_user_id INT UNSIGNED DEFAULT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  reason VARCHAR(100) NOT NULL,
  description TEXT,
  status ENUM('pending','under_review','resolved','rejected') NOT NULL DEFAULT 'pending',
  admin_note TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_reports_status (status),
  KEY idx_reports_product (product_id),
  KEY idx_reports_reported_user (reported_user_id),
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_reports_reported_user FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Product views
-- ------------------------------------------------------------
CREATE TABLE product_views (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  ip_hash CHAR(32) DEFAULT NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_product_views_product (product_id),
  KEY idx_product_views_time (viewed_at),
  CONSTRAINT fk_product_views_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Audit log
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) DEFAULT NULL,
  entity_id INT UNSIGNED DEFAULT NULL,
  details TEXT,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Settings
-- ------------------------------------------------------------
CREATE TABLE settings (
  setting_key VARCHAR(50) NOT NULL,
  setting_value TEXT,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Contact messages
-- ------------------------------------------------------------
CREATE TABLE contact_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Settings ----------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'CampusMart'),
('site_tagline', 'Buy. Sell. Connect. Within Your Campus.'),
('email_domain', 'campusmart.test'),
('listing_expiry_days', '30'),
('listings_require_approval', '1'),
('max_listing_images', '5'),
('trusted_seller_min_reviews', '5'),
('trusted_seller_min_rating', '4.5'),
('support_email', 'support@campusmart.test'),
('currency', '৳'),
('_last_order_seq', '8');

-- Users -------------------------------------------------------
-- admin@campusmart.test / Admin@123
-- student@campusmart.test / Student@123
-- rahim/nusrat/tamim/faria/shihab @campusmart.test / Demo@123
INSERT INTO users (id, full_name, student_id, email, phone, password_hash, department, batch, hall, profile_image, role, status, is_verified, created_at) VALUES
(1, 'Admin CampusMart', 'ADM-0001', 'admin@campusmart.test', '01700000000',
 '$2y$10$q1iGH71wJs//UuWqJ/yay.lTxrv.nLlQ85cFONCzHcrE/k1U.MkHi',
 'Administration', '2021', 'Admin Office', NULL, 'admin', 'active', 1, '2026-01-05 09:00:00'),
(2, 'Ayesha Rahman', 'STU-2024-001', 'student@campusmart.test', '01711112222',
 '$2y$10$zO0HnvdKNZ6TaF2//iD2hujoZLTTbkKLf7hCGg5o/i5u9aHwxp/xi',
 'Computer Science & Engineering', '2024', 'Begum Rokeya Hall', NULL, 'student', 'active', 1, '2026-02-10 10:30:00'),
(3, 'Rahim Uddin', 'STU-2023-014', 'rahim@campusmart.test', '01822223333',
 '$2y$10$u18deUJwCnN.WN3OL3N.2us5v6Q7Au3p5p4Cb1pTjHlC4SuTWcfd.',
 'Electrical Engineering', '2023', 'Shaheed Nur Hossain Hall', NULL, 'student', 'active', 1, '2026-02-12 11:00:00'),
(4, 'Nusrat Jahan', 'STU-2024-022', 'nusrat@campusmart.test', '01633334444',
 '$2y$10$qZ/DNwfSDYoQxc7riAOnS.WBy8P4vAWwXvEecUsJZypEevWmuq8xO',
 'Civil Engineering', '2024', 'Nawab Faizunnesa Hall', NULL, 'student', 'active', 1, '2026-02-15 12:15:00'),
(5, 'Tamim Hasan', 'STU-2023-041', 'tamim@campusmart.test', '01944445555',
 '$2y$10$fpWbVu7.TLsKz0TUVIvsbOaq1TE/fvPIZzl3P59eMBCtY8G2O7Juu',
 'Mechanical Engineering', '2023', 'Shaheed President Ziaur Rahman Hall', NULL, 'student', 'active', 1, '2026-02-20 09:45:00'),
(6, 'Faria Islam', 'STU-2025-008', 'faria@campusmart.test', '01555556666',
 '$2y$10$5ZMl1MElfsfhfSo.NUULaeBq9xn06JpBzKeDo7uB7rx7GbpdC2Ccu',
 'Pharmacy', '2025', 'Kobi Sufia Kamal Hall', NULL, 'student', 'active', 1, '2026-03-02 14:00:00'),
(7, 'Shihab Chowdhury', 'STU-2022-055', 'shihab@campusmart.test', '01366667777',
 '$2y$10$.Cad.GoTRp682oYpU6KOL.MsGL53WCEr6TdXJ20SQIFhzoa3vs0wG',
 'Economics', '2022', 'BCS Goli', NULL, 'student', 'suspended', 0, '2026-03-10 08:20:00');

-- Categories --------------------------------------------------
INSERT INTO categories (id, name, description, status, created_at) VALUES
(1, 'Books', 'Textbooks, reference books, novels and study materials', 'active', '2026-01-06 09:00:00'),
(2, 'Electronics', 'Electronics such as fans, chargers, printers and gadgets', 'active', '2026-01-06 09:00:00'),
(3, 'Computers', 'Laptops, desktops, keyboards, mice and computer accessories', 'active', '2026-01-06 09:00:00'),
(4, 'Mobile Accessories', 'Phone cases, chargers, power banks, earbuds and cables', 'active', '2026-01-06 09:00:00'),
(5, 'Calculators', 'Scientific and graphing calculators', 'active', '2026-01-06 09:00:00'),
(6, 'Lab Equipment', 'Laboratory and practical equipment for science students', 'active', '2026-01-06 09:00:00'),
(7, 'Bicycles', 'Cycles and related equipment', 'active', '2026-01-06 09:00:00'),
(8, 'Furniture', 'Study tables, chairs, racks and hostel furniture', 'active', '2026-01-06 09:00:00'),
(9, 'Clothing', 'Clothes, shoes and accessories', 'active', '2026-01-06 09:00:00'),
(10, 'Sports', 'Sports equipment and gear', 'active', '2026-01-06 09:00:00'),
(11, 'Other', 'Anything else useful for campus life', 'active', '2026-01-06 09:00:00');

-- Products ----------------------------------------------------
INSERT INTO products (id, seller_id, category_id, title, description, price, condition_label, quantity, location, contact_preference, status, reject_reason, views, expires_at, created_at) VALUES
(1, 3, 1, 'Calculus Early Transcendentals 8th Edition', 'Complete calculus textbook used for the first two years of engineering math. Clean pages, a few pencil markings in chapter 4. Great condition overall.', 350.00, 'Good', 1, 'Shaheed Nur Hossain Hall', 'In-app message', 'active', NULL, 152, '2026-08-20 00:00:00', '2026-07-12 10:00:00'),
(2, 4, 1, 'Data Structures and Algorithms (Cormen)', 'Introduction to Algorithms by Cormen. Used for 2nd year CSE courses. Slightly worn cover but all pages intact. Price negotiable on campus.', 420.00, 'Like New', 1, 'Begum Rokeya Hall', 'Both', 'active', NULL, 98, '2026-08-22 00:00:00', '2026-07-14 12:00:00'),
(3, 2, 5, 'Casio FX-991EX ClassWiz Scientific Calculator', 'Casio FX-991EX scientific calculator in like-new condition. Comes with original cover and manual. Ideal for engineering exams. Only used for one semester.', 1100.00, 'Like New', 2, 'Begum Rokeya Hall', 'In-app message', 'active', NULL, 310, '2026-09-01 00:00:00', '2026-07-08 15:30:00'),
(4, 3, 5, 'TI-84 Plus Graphing Calculator', 'Texas Instruments TI-84 Plus graphing calculator, works perfectly. Great for math, physics and statistics courses. Includes manual and USB cable.', 5200.00, 'Good', 1, 'Shaheed President Ziaur Rahman Hall', 'Phone', 'active', NULL, 205, '2026-08-18 00:00:00', '2026-07-10 09:00:00'),
(5, 3, 3, 'Dell Inspiron Laptop Core i5 8th Gen', 'Dell Inspiron 15 laptop with Core i5 8th gen, 8GB RAM, 256GB SSD. Works smoothly for programming and general use. Battery lasts about 4 hours. Charger included.', 42000.00, 'Good', 1, 'Shaheed Abrar Fahad Hall', 'Both', 'active', NULL, 480, '2026-08-25 00:00:00', '2026-07-05 11:00:00'),
(6, 4, 2, 'HP LaserJet P1102 Printer', 'HP LaserJet monochrome laser printer in working condition. Used for printing lab reports. Includes toner cartridge with some life left.', 6500.00, 'Used', 1, 'Nawab Faizunnesa Hall', 'In-app message', 'active', NULL, 120, '2026-08-15 00:00:00', '2026-07-18 16:00:00'),
(7, 5, 4, 'Anker Power Bank 20000mAh', 'Anker PowerCore 20000mAh power bank. Charges a phone roughly 4 times. Fast charging supported. Very little used.', 1800.00, 'Like New', 1, 'BCS Goli', 'In-app message', 'active', NULL, 260, '2026-08-28 00:00:00', '2026-07-16 13:00:00'),
(8, 2, 4, 'iPhone Fast Charger + Cable', 'Original 20W USB-C fast charger with cable. Works with iPhone and Android. Bought recently, selling because of extra charger.', 500.00, 'New', 1, 'Nawab Faizunnesa Hall', 'In-app message', 'active', NULL, 140, '2026-08-30 00:00:00', '2026-07-20 10:00:00'),
(9, 5, 6, 'Digital Multimeter DT9205A', 'Digital multimeter used for basic electronics labs. All functions work: voltage, current, resistance, continuity. Includes probes.', 750.00, 'Good', 1, 'Shaheed Nur Hossain Hall', 'In-app message', 'active', NULL, 85, '2026-08-21 00:00:00', '2026-07-09 14:00:00'),
(10, 6, 6, 'White Lab Coat (M size)', 'Clean white lab coat for pharmacy/medical lab practicals. M size, barely used. Must pick up from Kobi Sufia Kamal Hall.', 450.00, 'New', 1, 'Kobi Sufia Kamal Hall', 'In-app message', 'reserved', NULL, 66, '2026-08-19 00:00:00', '2026-07-11 09:30:00'),
(11, 4, 7, 'Hero Sprint Bicycle', 'Hero Sprint bicycle in fair condition. New tyre tubes installed last month. Good for daily campus commute. Needs minor chain oiling.', 8500.00, 'Fair', 1, 'Kobi Sufia Kamal Hall', 'Both', 'active', NULL, 175, '2026-08-26 00:00:00', '2026-07-07 17:00:00'),
(12, 6, 8, 'Study Table with Chair', 'Wooden study table with attached bookshelf and a comfortable chair. Sturdy, ideal for hostel rooms. Pickup required - cannot deliver.', 3200.00, 'Used', 2, 'Khurshid Zahan Haque Hall', 'Phone', 'active', NULL, 134, '2026-08-24 00:00:00', '2026-07-13 12:30:00'),
(13, 2, 9, 'Nike Running Shoes (UK 9)', 'Nike running shoes in like-new condition, worn only a few times. Size UK 9. Very comfortable for jogging and sports.', 2800.00, 'Like New', 1, 'Kobi Sufia Kamal Hall', 'In-app message', 'active', NULL, 92, '2026-08-23 00:00:00', '2026-07-15 08:00:00'),
(14, 5, 10, 'Cricket Bat (Full Size)', 'Full size English willow cricket bat. Used for one season, still in good shape. Great for intra-university tournaments.', 1500.00, 'Good', 1, 'Shaheed President Ziaur Rahman Hall', 'In-app message', 'active', NULL, 70, '2026-08-17 00:00:00', '2026-07-19 15:00:00'),
(15, 6, 11, 'Drawing Instrument Set', 'Complete geometry/drawing instrument set: compasses, divider, protractor, scale, pencil sharpener and eraser. Perfect for architecture and engineering drawing courses.', 380.00, 'New', 1, 'Begum Rokeya Hall', 'In-app message', 'active', NULL, 55, '2026-08-29 00:00:00', '2026-07-21 10:30:00'),
(16, 3, 2, 'Wireless Earbuds (TWS)', 'TWS wireless earbuds with charging case. Good sound quality and battery. Selling after buying a new pair.', 2200.00, 'New', 1, 'International Hall', 'In-app message', 'sold', NULL, 88, '2026-08-16 00:00:00', '2026-07-06 09:00:00'),
(17, 5, 1, 'Engineering Drawing & Graphics Textbook', 'Engineering drawing textbook covering 1st year drawing courses. Some pages have solved examples written in pencil.', 300.00, 'Fair', 1, 'Shaheed Abrar Fahad Hall', 'In-app message', 'active', NULL, 41, '2026-08-27 00:00:00', '2026-07-22 11:00:00'),
(18, 4, 2, 'Portable Desk Fan', 'Small portable desk fan, USB powered. Very handy for hostel rooms in summer. Low noise, two speed settings.', 650.00, 'Good', 1, 'Khurshid Zahan Haque Hall', 'In-app message', 'active', NULL, 60, '2026-08-31 00:00:00', '2026-07-23 13:00:00'),
(19, 6, 5, 'Casio FX-82MS Calculator', 'Casio FX-82MS scientific calculator, fully functional. Ideal for 1st year math. Slight scratch on screen but display is perfect.', 450.00, 'Used', 1, 'Nawab Faizunnesa Hall', 'In-app message', 'active', NULL, 73, '2026-08-20 00:00:00', '2026-07-17 09:00:00'),
(20, 3, 1, 'C Programming: The Complete Reference', 'Classic C programming reference book. Great for 1st year CSE students. Cover slightly worn but pages are clean. Awaiting admin approval.', 250.00, 'Good', 1, 'Bijoy 24 Hall', 'In-app message', 'pending', NULL, 0, '2026-09-10 00:00:00', '2026-08-08 15:00:00'),
(21, 4, 6, 'Digital Vernier Calliper', 'Digital vernier calliper, works accurately. Used for engineering drawing and metrology labs. Batteries included.', 700.00, 'Good', 1, 'Begum Rokeya Hall', 'In-app message', 'sold', NULL, 64, '2026-08-14 00:00:00', '2026-07-04 10:00:00'),
(22, 3, 1, 'Oxford English Dictionary (Paperback)', 'Compact Oxford English dictionary. Useful for language courses. Good condition.', 500.00, 'Good', 1, 'Mohabolipur', 'In-app message', 'sold', NULL, 51, '2026-08-12 00:00:00', '2026-07-03 09:00:00'),
(23, 5, 11, 'Laptop Backpack', 'Spacious laptop backpack with USB port and padded compartments. Fits up to 15.6 inch laptop. Only one semester used.', 900.00, 'Like New', 1, 'International Hall', 'In-app message', 'reserved', NULL, 112, '2026-08-13 00:00:00', '2026-07-08 14:00:00'),
(24, 6, 6, 'Stethoscope', 'Basic student stethoscope for pharmacy/medical practicals. Listing expired - item no longer available.', 1200.00, 'Used', 1, 'Kobi Sufia Kamal Hall', 'In-app message', 'expired', NULL, 34, '2026-07-01 00:00:00', '2026-06-01 09:00:00'),
(25, 4, 2, 'Electric Kettle 1.5L', 'Electric kettle for hostel room. Rejected by admin - duplicate listing of an already sold item.', 900.00, 'Good', 1, 'Nawab Faizunnesa Hall', 'In-app message', 'rejected', 'Duplicate listing of an already sold item on the same account.', 0, '2026-09-05 00:00:00', '2026-08-01 10:00:00'),
(26, 2, 1, 'Math Practice Notebooks (Pack of 3)', 'Three half-used math practice notebooks. Removed by seller.', 150.00, 'Used', 1, 'Khurshid Zahan Haque Hall', 'In-app message', 'removed', NULL, 20, '2026-08-11 00:00:00', '2026-07-25 08:00:00');

-- Product images ----------------------------------------------
INSERT INTO product_images (id, product_id, image_path, is_primary, sort_order) VALUES
(1, 1, 'assets/images/uploads/p01_1.jpg', 1, 0),
(2, 2, 'assets/images/uploads/p02_1.jpg', 1, 0),
(3, 3, 'assets/images/uploads/p03_1.jpg', 1, 0),
(4, 4, 'assets/images/uploads/p04_1.jpg', 1, 0),
(5, 5, 'assets/images/uploads/p05_1.jpg', 1, 0),
(6, 6, 'assets/images/uploads/p06_1.jpg', 1, 0),
(7, 7, 'assets/images/uploads/p07_1.jpg', 1, 0),
(8, 8, 'assets/images/uploads/p08_1.jpg', 1, 0),
(9, 9, 'assets/images/uploads/p09_1.jpg', 1, 0),
(10, 10, 'assets/images/uploads/p10_1.jpg', 1, 0),
(11, 11, 'assets/images/uploads/p11_1.jpg', 1, 0),
(12, 12, 'assets/images/uploads/p12_1.jpg', 1, 0),
(13, 13, 'assets/images/uploads/p13_1.jpg', 1, 0),
(14, 14, 'assets/images/uploads/p14_1.jpg', 1, 0),
(15, 15, 'assets/images/uploads/p15_1.jpg', 1, 0),
(16, 16, 'assets/images/uploads/p16_1.jpg', 1, 0),
(17, 17, 'assets/images/uploads/p17_1.jpg', 1, 0),
(18, 18, 'assets/images/uploads/p18_1.jpg', 1, 0),
(19, 19, 'assets/images/uploads/p19_1.jpg', 1, 0),
(20, 20, 'assets/images/uploads/p20_1.jpg', 1, 0),
(21, 21, 'assets/images/uploads/p21_1.jpg', 1, 0),
(22, 22, 'assets/images/uploads/p22_1.jpg', 1, 0),
(23, 23, 'assets/images/uploads/p23_1.jpg', 1, 0),
(24, 24, 'assets/images/uploads/p24_1.jpg', 1, 0),
(25, 25, 'assets/images/uploads/p25_1.jpg', 1, 0),
(26, 26, 'assets/images/uploads/p26_1.jpg', 1, 0);

-- Favorites ---------------------------------------------------
INSERT INTO favorites (id, user_id, product_id, created_at) VALUES
(1, 2, 1, '2026-07-20 10:00:00'),
(2, 2, 3, '2026-07-21 11:00:00'),
(3, 2, 5, '2026-07-22 12:00:00'),
(4, 2, 13, '2026-07-23 13:00:00'),
(5, 2, 19, '2026-07-24 14:00:00'),
(6, 2, 11, '2026-07-25 15:00:00'),
(7, 3, 7, '2026-07-26 16:00:00'),
(8, 4, 3, '2026-07-27 17:00:00'),
(9, 5, 3, '2026-07-28 18:00:00');

-- Orders ------------------------------------------------------
INSERT INTO orders (id, order_code, buyer_id, seller_id, product_id, quantity, unit_price, total_amount, payment_method, payment_status, status, pickup_location, pickup_time, status_history, created_at, completed_at) VALUES
(1, 'CM-000001', 2, 3, 16, 1, 2200.00, 2200.00, 'cash', 'paid', 'completed', 'Campus central cafeteria', 'Around noon', '[{"status":"pending","at":"2026-07-20 10:00:00"},{"status":"accepted","at":"2026-07-20 12:00:00"},{"status":"ready","at":"2026-07-21 09:00:00"},{"status":"completed","at":"2026-07-22 16:00:00"}]', '2026-07-20 10:00:00', '2026-07-22 16:00:00'),
(2, 'CM-000002', 2, 4, 21, 1, 700.00, 700.00, 'cash', 'paid', 'completed', 'Begum Rokeya Hall front gate', 'After 5pm', '[{"status":"pending","at":"2026-07-24 10:00:00"},{"status":"accepted","at":"2026-07-24 15:00:00"},{"status":"ready","at":"2026-07-25 09:00:00"},{"status":"completed","at":"2026-07-26 14:00:00"}]', '2026-07-24 10:00:00', '2026-07-26 14:00:00'),
(3, 'CM-000003', 6, 3, 22, 1, 500.00, 500.00, 'cash', 'paid', 'completed', 'Central library lobby', 'Morning', '[{"status":"pending","at":"2026-07-27 10:00:00"},{"status":"accepted","at":"2026-07-27 12:00:00"},{"status":"ready","at":"2026-07-28 09:00:00"},{"status":"completed","at":"2026-07-29 15:00:00"}]', '2026-07-27 10:00:00', '2026-07-29 15:00:00'),
(4, 'CM-000004', 2, 5, 23, 1, 900.00, 900.00, 'cash', 'pending', 'accepted', NULL, NULL, '[{"status":"pending","at":"2026-08-01 10:00:00"},{"status":"accepted","at":"2026-08-02 09:00:00"}]', '2026-08-01 10:00:00', NULL),
(5, 'CM-000005', 2, 6, 12, 1, 3200.00, 3200.00, 'cash', 'pending', 'pending', NULL, NULL, '[{"status":"pending","at":"2026-08-03 10:00:00"}]', '2026-08-03 10:00:00', NULL),
(6, 'CM-000006', 3, 2, 3, 1, 1100.00, 1100.00, 'cash', 'pending', 'pending', NULL, NULL, '[{"status":"pending","at":"2026-08-05 10:00:00"}]', '2026-08-05 10:00:00', NULL),
(7, 'CM-000007', 2, 5, 7, 1, 1800.00, 1800.00, 'cash', 'pending', 'rejected', NULL, NULL, '[{"status":"pending","at":"2026-07-30 10:00:00"},{"status":"rejected","at":"2026-07-30 16:00:00"}]', '2026-07-30 10:00:00', NULL),
(8, 'CM-000008', 3, 6, 10, 1, 450.00, 450.00, 'cash', 'pending', 'ready', 'Begum Rokeya Hall front desk', 'Pickup after calling', '[{"status":"pending","at":"2026-08-06 10:00:00"},{"status":"accepted","at":"2026-08-06 14:00:00"},{"status":"ready","at":"2026-08-07 09:00:00"}]', '2026-08-06 10:00:00', NULL);

-- Conversations -----------------------------------------------
INSERT INTO conversations (id, user_a, user_b, product_id, last_message_at, created_at) VALUES
(1, 2, 3, 1, '2026-08-01 13:00:00', '2026-07-20 11:00:00'),
(2, 2, 5, 7, '2026-07-30 15:30:00', '2026-07-25 12:00:00'),
(3, 2, 4, 21, '2026-07-26 15:00:00', '2026-07-26 12:00:00');

-- Messages ----------------------------------------------------
INSERT INTO messages (id, conversation_id, sender_id, body, is_read, read_at, created_at) VALUES
(1, 1, 2, 'Hello! Is the calculus book still available?', 1, '2026-07-20 11:05:00', '2026-07-20 11:00:00'),
(2, 1, 3, 'Hi Ayesha, yes it is still available.', 1, '2026-07-20 11:10:00', '2026-07-20 11:05:00'),
(3, 1, 2, 'Great! Can we meet near the cafeteria?', 1, '2026-07-20 11:12:00', '2026-07-20 11:10:00'),
(4, 1, 3, 'Sure, tomorrow after 12pm works.', 1, '2026-07-20 11:15:00', '2026-07-20 11:12:00'),
(5, 1, 2, 'Perfect, see you then!', 0, NULL, '2026-08-01 13:00:00'),
(6, 2, 2, 'Is the power bank available?', 1, '2026-07-25 12:10:00', '2026-07-25 12:00:00'),
(7, 2, 5, 'Yes it is. Are you interested?', 1, '2026-07-25 12:15:00', '2026-07-25 12:10:00'),
(8, 2, 2, 'Yes, I have sent a purchase request.', 1, '2026-07-30 15:35:00', '2026-07-30 15:30:00'),
(9, 3, 2, 'Thanks for the calliper, it works perfectly!', 1, '2026-07-26 15:05:00', '2026-07-26 15:00:00'),
(10, 3, 4, 'You are welcome! Happy to help.', 0, NULL, '2026-07-26 15:05:00');

-- Notifications -----------------------------------------------
INSERT INTO notifications (id, user_id, type, title, message, related_id, is_read, read_at, created_at) VALUES
(1, 2, 'listing_approved', 'Listing approved', 'Your listing "Casio FX-991EX ClassWiz Scientific Calculator" has been approved and is now active.', 3, 1, '2026-07-08 16:00:00', '2026-07-08 15:35:00'),
(2, 2, 'order_accepted', 'Purchase request accepted', 'Rahim Uddin accepted your purchase request for "Wireless Earbuds (TWS)".', 1, 1, '2026-07-20 12:10:00', '2026-07-20 12:00:00'),
(3, 2, 'order_ready', 'Order ready for pickup', 'Your order CM-000001 is ready for pickup near the campus central cafeteria.', 1, 1, '2026-07-21 09:30:00', '2026-07-21 09:00:00'),
(4, 2, 'order_completed', 'Order completed', 'Your order CM-000001 was completed. Please rate your seller Rahim Uddin.', 1, 1, '2026-07-22 17:00:00', '2026-07-22 16:00:00'),
(5, 2, 'review_received', 'New review received', 'Rahim Uddin left you a 5-star review for order CM-000001.', 1, 1, '2026-07-23 10:00:00', '2026-07-23 09:00:00'),
(6, 2, 'order_received', 'Purchase request received', 'Tamim Hasan sent a purchase request for your "Casio FX-991EX ClassWiz Scientific Calculator".', 6, 1, '2026-08-05 10:30:00', '2026-08-05 10:00:00'),
(7, 2, 'message', 'New message', 'Rahim Uddin sent you a new message.', 5, 0, NULL, '2026-08-01 13:00:00'),
(8, 2, 'favorite', 'Product favorited', 'Someone added your "iPhone Fast Charger + Cable" to their favorites.', 8, 0, NULL, '2026-07-28 12:00:00'),
(9, 2, 'expiry', 'Listing about to expire', 'Your listing "Math Practice Notebooks (Pack of 3)" will expire soon. Renew it to keep it active.', 26, 0, NULL, '2026-08-08 09:00:00'),
(10, 3, 'order_received', 'Purchase request received', 'Ayesha Rahman sent a purchase request for your "Wireless Earbuds (TWS)".', 1, 1, '2026-07-20 10:30:00', '2026-07-20 10:00:00'),
(11, 4, 'order_completed', 'Order completed', 'Your order CM-000002 was completed. Please rate your buyer Ayesha Rahman.', 2, 0, NULL, '2026-07-26 14:05:00'),
(12, 5, 'order_received', 'Purchase request received', 'Ayesha Rahman sent a purchase request for your "Anker Power Bank 20000mAh".', 7, 1, '2026-07-30 10:15:00', '2026-07-30 10:00:00');

-- Reviews -----------------------------------------------------
INSERT INTO reviews (id, reviewer_id, reviewed_user_id, order_id, rating, comment, status, created_at) VALUES
(1, 2, 3, 1, 5, 'Very friendly seller, delivered the earbuds exactly as described. Recommended!', 'approved', '2026-07-23 09:00:00'),
(2, 3, 2, 1, 5, 'Great buyer, on-time pickup and smooth transaction.', 'approved', '2026-07-23 09:30:00'),
(3, 2, 4, 2, 4, 'The calliper was as described. Good communication overall.', 'approved', '2026-07-27 10:00:00'),
(4, 6, 3, 3, 5, 'Smooth deal, the dictionary was in great condition.', 'approved', '2026-07-30 11:00:00'),
(5, 4, 2, 2, 4, 'Smooth payment and pickup, no issues at all.', 'approved', '2026-07-27 11:00:00');

-- Reports -----------------------------------------------------
INSERT INTO reports (id, reporter_id, reported_user_id, product_id, reason, description, status, admin_note, created_at, resolved_at) VALUES
(1, 2, NULL, 1, 'Wrong category', 'This book belongs in Books, not in its current category.', 'under_review', 'Checking category mapping with seller.', '2026-08-04 10:00:00', NULL),
(2, 3, 7, NULL, 'Suspicious user', 'This user sent repeated off-topic messages about buying used electronics at unrealistic prices.', 'resolved', 'Account reviewed; no violation found. Warning sent.', '2026-07-15 12:00:00', '2026-07-16 09:00:00'),
(3, 4, NULL, 24, 'Scam / suspicious listing', 'The item was listed as new but looks heavily used and the photo appears copied from the internet.', 'pending', NULL, '2026-08-06 14:00:00', NULL);

-- Product views -----------------------------------------------
INSERT INTO product_views (id, product_id, user_id, ip_hash, viewed_at) VALUES
(1, 3, 2, NULL, '2026-07-25 10:00:00'),
(2, 3, NULL, 'ab12cd34ef56ab12cd34ef56ab12cd34', '2026-07-26 10:00:00'),
(3, 5, 2, NULL, '2026-07-26 11:00:00'),
(4, 5, NULL, 'cd34ef56ab12cd34ef56ab12cd34ef56', '2026-07-27 11:00:00'),
(5, 7, 2, NULL, '2026-07-27 12:00:00'),
(6, 1, NULL, 'ef56ab12cd34ef56ab12cd34ef56ab12', '2026-07-28 12:00:00'),
(7, 16, 2, NULL, '2026-07-28 13:00:00'),
(8, 4, NULL, 'ab12cd34ef56ab12cd34ef56ab12cd34', '2026-07-29 13:00:00');

-- Audit log ---------------------------------------------------
INSERT INTO audit_logs (id, actor_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES
(1, 1, 'listing_rejected', 'product', 25, 'Rejected product "Electric Kettle 1.5L" (reason: duplicate listing).', '127.0.0.1', '2026-08-02 09:00:00'),
(2, 1, 'user_suspended', 'user', 7, 'Suspended user Shihab Chowdhury.', '127.0.0.1', '2026-08-03 10:00:00'),
(3, 1, 'report_resolved', 'report', 2, 'Resolved report #2 with note: Account reviewed; no violation found. Warning sent.', '127.0.0.1', '2026-07-16 09:00:00'),
(4, 1, 'order_status_changed', 'order', 1, 'Order CM-000001 marked as completed.', '127.0.0.1', '2026-07-22 16:00:00');

-- Contact messages --------------------------------------------
INSERT INTO contact_messages (id, name, email, subject, message, is_read, created_at) VALUES
(1, 'Ayesha Rahman', 'student@campusmart.test', 'Suggestion', 'Could you add a category for musical instruments? Many students want to sell guitars on campus.', 0, '2026-08-07 10:00:00');
