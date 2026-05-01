-- ============================================================
-- Smart Health Tracker — Database Setup
-- Run this in phpMyAdmin → SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS health_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE health_tracker;

-- ------------------------------------------------------------
-- Table 1: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)        NOT NULL,
    username   VARCHAR(50)         NOT NULL UNIQUE,
    email      VARCHAR(150)        NOT NULL UNIQUE,
    password   VARCHAR(255)        NOT NULL,
    created_at TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table 2: user_profile
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_profile (
    profile_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED        NOT NULL UNIQUE,
    age        TINYINT UNSIGNED    DEFAULT NULL,
    gender     ENUM('male','female','other') DEFAULT NULL,
    height     DECIMAL(4,2)        DEFAULT NULL COMMENT 'in metres',
    weight     DECIMAL(5,2)        DEFAULT NULL COMMENT 'in kg',
    CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table 3: health_records
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS health_records (
    record_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    steps       INT UNSIGNED        DEFAULT 0,
    water       DECIMAL(4,2)        DEFAULT 0.00 COMMENT 'litres',
    sleep       DECIMAL(4,2)        DEFAULT 0.00 COMMENT 'hours',
    bmi         DECIMAL(5,2)        DEFAULT NULL,
    record_date DATE                NOT NULL,
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_date (user_id, record_date),
    CONSTRAINT fk_record_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table 4: goals
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS goals (
    goal_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED        NOT NULL UNIQUE,
    steps_goal INT UNSIGNED        DEFAULT 10000,
    water_goal DECIMAL(4,2)        DEFAULT 2.00,
    sleep_goal DECIMAL(4,2)        DEFAULT 8.00,
    CONSTRAINT fk_goal_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;



-- ------------------------------------------------------------
-- Table 5: food_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS food_logs (
    food_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED        NOT NULL,
    food_name  VARCHAR(200)        NOT NULL,
    calories   DECIMAL(7,2)        DEFAULT 0.00,
    protein    DECIMAL(5,2)        DEFAULT 0.00  COMMENT 'grams',
    carbs      DECIMAL(5,2)        DEFAULT 0.00  COMMENT 'grams',
    fats       DECIMAL(5,2)        DEFAULT 0.00  COMMENT 'grams',
    log_date   DATE                NOT NULL,
    created_at TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_food_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
