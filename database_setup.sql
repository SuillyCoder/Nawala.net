-- ============================================================
--  Lost and Found Items Catalog System
--  Database Setup Script
--  Run this in phpMyAdmin > SQL tab, or via MySQL CLI
-- ============================================================

-- Step 1: Create and select the database
CREATE DATABASE IF NOT EXISTS lost_and_found_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lost_and_found_db;

-- ============================================================
--  TABLE: user
--  Stores both regular users and the admin.
--  Admin is identified at login by password = 'admin12345'.
-- ============================================================
CREATE TABLE IF NOT EXISTS user (
    user_id     INT             NOT NULL AUTO_INCREMENT,
    username    VARCHAR(50)     NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,               -- stored as plain text per project scope
    email       VARCHAR(100)    NOT NULL UNIQUE,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id)
) ENGINE=InnoDB;

-- ============================================================
--  TABLE: item
--  Core table for all found items logged by the office.
--  Claim information is embedded here (no separate claim table).
-- ============================================================
CREATE TABLE IF NOT EXISTS item (
    item_id         INT             NOT NULL AUTO_INCREMENT,
    item_name       VARCHAR(100)    NOT NULL,
    description     TEXT,
    location_found  VARCHAR(100)    NOT NULL,
    date_found      DATE            NOT NULL,
    status          ENUM('unclaimed', 'claimed', 'turned_over')
                                    NOT NULL DEFAULT 'unclaimed',

    -- Claim fields (merged from CLAIM entity per design decision)
    claim_date      DATE            DEFAULT NULL,       -- NULL until someone claims it
    claim_status    ENUM('pending', 'approved', 'rejected')
                                    DEFAULT NULL,       -- NULL when item is still unclaimed

    -- Who reported/logged this item (FK to user)
    reported_by     INT             NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (item_id),
    CONSTRAINT fk_item_reported_by
        FOREIGN KEY (reported_by) REFERENCES user(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA: Default admin account
--  password = 'admin12345' (plain text, used to detect admin role)
-- ============================================================
INSERT INTO user (username, password, email)
VALUES ('admin', 'admin12345', 'admin@lostandfound.com')
ON DUPLICATE KEY UPDATE username = username;   -- safe to re-run

-- ============================================================
--  SEED DATA: Sample regular user
-- ============================================================
INSERT INTO user (username, password, email)
VALUES ('jdelacruz', 'pass1234', 'juan.delacruz@example.com')
ON DUPLICATE KEY UPDATE username = username;

-- ============================================================
--  SEED DATA: Sample found items
--  reported_by = 1 (admin) for initial seeding
-- ============================================================
INSERT INTO item (item_name, description, location_found, date_found, status, claim_date, claim_status, reported_by)
VALUES
    ('Black Umbrella',
     'Medium-sized black umbrella with a wooden handle. No name tag.',
     'Library – Ground Floor', '2025-04-10',
     'unclaimed', NULL, NULL, 1),

    ('Student ID',
     'USC student ID belonging to a certain student. Laminated card.',
     'Canteen – Table 4', '2025-04-12',
     'claimed', '2025-04-14', 'approved', 1),

    ('Blue Water Bottle',
     'Blue stainless steel water bottle, 500ml. Has stickers on the side.',
     'PE Gymnasium', '2025-04-15',
     'unclaimed', NULL, NULL, 1),

    ('Calculator (Casio fx-991ES)',
     'Scientific calculator inside a worn black case.',
     'Room 301 – Engineering Building', '2025-04-17',
     'pending', NULL, 'pending', 1),

    ('Brown Leather Wallet',
     'Brown bifold wallet. Contains a few cards but no cash.',
     'Main Gate – Guard House', '2025-04-20',
     'unclaimed', NULL, NULL, 1);

-- ============================================================
--  VERIFICATION QUERIES (optional – run to check setup)
-- ============================================================
-- SELECT * FROM user;
-- SELECT * FROM item;