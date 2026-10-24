ATM System with MySQL Database
================================

Features
- PIN login
- Balance stored in MySQL
- Deposit and withdrawal with balance validation
- Change 4-digit PIN
- Recent transaction history
- Session-based login/logout

Requirements
- XAMPP (Apache + PHP + MySQL/MariaDB) or another PHP/MySQL server

Setup in XAMPP
1. Extract this folder into: C:\xampp\htdocs\atm_mysql_project
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin
4. Choose the Import tab and import db.sql.
   The SQL file creates the atm_system database, tables and demo account.
5. If your MySQL username/password differs from the defaults, edit the
   database settings at the top of index.php.
6. Open http://localhost/atm_mysql_project/

Demo login
- PIN: 1234
- Opening balance: ₹10,000

Important
This is a college/learning demo, not production banking software. Do not use
real account details or deploy it for real financial transactions. For a real
system, add CSRF protection, rate limiting/lockouts, secure PIN hashing with
password_hash/password_verify, HTTPS, auditing, and stronger access controls.
