# 💰 Staycation Savings — Sinking Funds System
### Setup Guide (No XAMPP)

---

## Requirements
- **PHP 8.1+** (standalone, not XAMPP)
- **MySQL 8.0+** (MySQL Workbench)
- **Composer** (for PHPMailer)
- **PHP built-in server** OR any local web server (e.g., Laragon, Herd, or `php -S`)

---

## Step 1 — Install PHPMailer

Open a terminal in the project folder and run:

```bash
composer install
```

This creates a `vendor/` folder with PHPMailer.

---

## Step 2 — Set Up the Database

1. Open **MySQL Workbench**
2. Connect to your local MySQL server
3. Open `database.sql` in MySQL Workbench
4. Run the script (⚡ Execute button or Ctrl+Shift+Enter)

This creates the `sinking_funds_db` database and pre-loads the 18 members.

---

## Step 3 — Configure the App

Edit `config/config.php`:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL user
define('DB_PASS', '');           // Your MySQL password

// Gmail SMTP
define('SMTP_USERNAME', 'your_gmail@gmail.com');
define('SMTP_PASSWORD', 'your_16_char_app_password');  // Gmail App Password!

// App URL
define('APP_URL', 'http://localhost:8000');  // Match your server port
```

### ⚠️ Gmail App Password Setup
1. Go to myaccount.google.com → Security → 2-Step Verification (must be ON)
2. Search "App Passwords" → Create one for "Mail"
3. Copy the 16-character password into `SMTP_PASSWORD`
4. Do NOT use your real Gmail password

---

## Step 4 — Run the App

### Option A — PHP Built-in Server
```bash
cd /path/to/sinking-funds
php -S localhost:8000
```
Then open: http://localhost:8000

### Option B — Laragon / Herd
Place the folder in the `www` directory and access via http://sinking-funds.test

---

## Step 5 — Create Admin Account

1. Open http://localhost:8000/setup.php
2. Enter your name + Gmail address + password
3. Check your Gmail for a verification email
4. Click the link to verify
5. Log in at http://localhost:8000

> **Local dev tip:** If email sending fails, the system auto-verifies so you can still log in.

---

## How Payments Work

- Daily rate: **₱5/day** per member, starting **January 1, 2026**
- **Bulk payments supported!** 
  - ₱35 = 7 days (1 week)
  - ₱70 = 2 weeks
  - ₱150 = 30 days
- The system automatically finds the first **unpaid date** and counts forward
- Remainders under ₱5 are noted but not counted as a day

---

## File Structure
```
sinking-funds/
├── index.php          ← Login page
├── setup.php          ← Initial admin setup (one-time)
├── verify.php         ← Email verification
├── logout.php
├── composer.json
├── database.sql       ← Run this in MySQL Workbench first!
├── config/
│   └── config.php     ← Edit your DB + SMTP settings here
├── includes/
│   ├── auth.php       ← Session & authentication
│   ├── mailer.php     ← PHPMailer SMTP
│   ├── payments.php   ← Payment logic
│   ├── header.php     ← Shared nav/layout
│   └── footer.php
├── pages/
│   ├── dashboard.php  ← Main overview
│   ├── members.php    ← Member management
│   ├── payments.php   ← Record payments
│   ├── calendar.php   ← Daily payment calendar
│   └── reports.php    ← Analytics & reports
└── assets/
    ├── css/main.css
    └── js/main.js
```
php -S localhost:8000