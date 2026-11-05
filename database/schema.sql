-- Homestay Maintenance: database tables
-- Import this ONCE in phpMyAdmin (select your database first, then Import).
-- MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(40)  NOT NULL UNIQUE,
  name          VARCHAR(80)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45) NOT NULL,
  attempted_at DATETIME    NOT NULL,
  INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  v TEXT        NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
  id         VARCHAR(40)   NOT NULL PRIMARY KEY,
  name       VARCHAR(60)   NOT NULL,
  rate       DECIMAL(10,2) NOT NULL DEFAULT 0,
  sort_order INT           NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id         VARCHAR(40)   NOT NULL PRIMARY KEY,
  name       VARCHAR(80)   NOT NULL,
  unit       VARCHAR(30)   NOT NULL DEFAULT '',
  price      DECIMAL(10,2) NOT NULL DEFAULT 0,
  sort_order INT           NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id             VARCHAR(40)   NOT NULL PRIMARY KEY,
  guest          VARCHAR(120)  NOT NULL,
  phone          VARCHAR(30)   NOT NULL DEFAULT '',
  room_id        VARCHAR(40)   NOT NULL,
  pax            SMALLINT      NOT NULL DEFAULT 1,
  check_in       DATE          NOT NULL,
  check_out      DATE          NOT NULL,
  check_in_time  TIME          NULL,
  check_out_time TIME          NULL,
  rate           DECIMAL(10,2) NOT NULL DEFAULT 0,
  extras         DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  disc_type      ENUM('amt','pct') NOT NULL DEFAULT 'amt',
  source         VARCHAR(40)   NOT NULL DEFAULT '',
  status         ENUM('confirmed','checkedin','checkedout','cancelled') NOT NULL DEFAULT 'confirmed',
  notes          TEXT          NULL,
  bill_no        VARCHAR(20)   NULL UNIQUE,
  bill_date      DATE          NULL,
  created_by     INT UNSIGNED  NULL,
  updated_by     INT UNSIGNED  NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_dates (check_in, check_out),
  INDEX idx_room (room_id, check_in),
  INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id VARCHAR(40)   NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  paid_on    DATE          NOT NULL,
  mode       VARCHAR(30)   NOT NULL DEFAULT '',
  INDEX idx_paid_on (paid_on),
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_meals (
  booking_id VARCHAR(40) NOT NULL,
  day        DATE        NOT NULL,
  breakfast  SMALLINT    NOT NULL DEFAULT 0,
  lunch      SMALLINT    NOT NULL DEFAULT 0,
  dinner     SMALLINT    NOT NULL DEFAULT 0,
  PRIMARY KEY (booking_id, day),
  INDEX idx_day (day),
  CONSTRAINT fk_meal_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchases (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id VARCHAR(40)   NOT NULL,
  product_id VARCHAR(40)   NULL,
  name       VARCHAR(80)   NOT NULL,
  unit       VARCHAR(30)   NOT NULL DEFAULT '',
  price      DECIMAL(10,2) NOT NULL DEFAULT 0,
  qty        INT           NOT NULL DEFAULT 1,
  disc_pct   DECIMAL(5,2)  NOT NULL DEFAULT 0,
  sold_on    DATE          NOT NULL,
  INDEX idx_sold_on (sold_on),
  CONSTRAINT fk_pur_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
  id         VARCHAR(40)   NOT NULL PRIMARY KEY,
  spent_on   DATE          NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  category   VARCHAR(40)   NOT NULL,
  mode       VARCHAR(30)   NOT NULL DEFAULT '',
  note       VARCHAR(255)  NOT NULL DEFAULT '',
  created_by INT UNSIGNED  NULL,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_spent_on (spent_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting settings (change them later in the app under "Rooms & settings")
INSERT IGNORE INTO settings (k, v) VALUES
  ('name', 'Homestay Maintenance'),
  ('address', ''), ('phone', ''), ('upi', ''), ('gstin', ''),
  ('gst_rate', '0'), ('bill_note', 'Thank you for staying with us!'),
  ('check_in_time', '12:00'), ('check_out_time', '11:00'),
  ('meal_rate_b', '0'), ('meal_rate_l', '0'), ('meal_rate_d', '0');

INSERT IGNORE INTO rooms (id, name, rate, sort_order) VALUES
  ('r1', 'Room 1', 2500, 1), ('r2', 'Room 2', 2500, 2), ('r3', 'Room 3', 2500, 3),
  ('r4', 'Room 4', 3000, 4), ('r5', 'Room 5', 3500, 5);

INSERT IGNORE INTO products (id, name, unit, price, sort_order) VALUES
  ('p1', 'Turmeric',       '250 g', 120, 1),
  ('p2', 'Coconut oil',    '1 L',   450, 2),
  ('p3', 'Coffee powder',  '1 kg',  640, 3),
  ('p4', 'Pepper',         '250 g', 260, 4),
  ('p5', 'Cardamom',       '250 g', 500, 5),
  ('p6', 'Millets powder', '500 g', 300, 6),
  ('p7', 'Mango pickle',   '1 kg',  550, 7),
  ('p8', 'Red rice',       '1 kg',   75, 8);
