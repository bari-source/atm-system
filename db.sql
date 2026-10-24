CREATE DATABASE IF NOT EXISTS atm_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atm_system;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_name VARCHAR(100) NOT NULL DEFAULT 'Demo User',
  pin_hash CHAR(64) NOT NULL,
  balance DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  transaction_type ENUM('Deposit','Withdrawal') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  balance_after DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
);

-- Demo account: PIN = 1234, opening balance = ₹10,000.
-- Do not use this demo PIN/hash approach for a real banking application.
INSERT INTO users (account_name, pin_hash, balance)
SELECT 'Demo User', SHA2('1234', 256), 10000.00
WHERE NOT EXISTS (SELECT 1 FROM users);
