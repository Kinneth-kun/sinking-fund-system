# 💰 Staycation Savings — Sinking Funds System
### Setup Guide (No XAMPP)

## Deploy to Vercel

The shared PHP application is configured for Vercel through `vercel.json` and
the `api/index.php` serverless entrypoint. It requires an externally hosted,
PostgreSQL database; Vercel's function filesystem is not used for data.

1. Create an empty PostgreSQL database (the Vercel Neon integration is recommended).
2. Import `database/schema.postgresql.sql` into it.
3. Import existing member and payment data separately, if needed.
4. Create a Vercel project from this repository.
5. Add every value from `.env.example` in **Project Settings → Environment
   Variables**. Set `APP_URL` to the production Vercel URL and keep
   `SESSION_DRIVER=database`.
6. Deploy, visit `/setup.php` once to create the administrator, then log in.

For CLI deployment, authenticate the Vercel CLI and run:

```bash
vercel
vercel --prod
```

Never commit `.env`, database passwords, or SMTP credentials. If credentials
were committed previously, rotate them before deploying because deleting them
from the current file does not remove them from Git history.

---

## Requirements
- **PHP 8.1+** (standalone, not XAMPP)
- **PostgreSQL 14+**
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

1. Open your PostgreSQL administration tool or `psql`.
2. Connect to your local PostgreSQL server
3. Import `database/schema.postgresql.sql`
4. Run the script (⚡ Execute button or Ctrl+Shift+Enter)

This creates the `sinking_funds_db` database and pre-loads the 18 members.

---

## Step 3 — Configure the App

Edit `config/config.php`:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'postgres');   // Your PostgreSQL user
define('DB_PASS', '');           // Your PostgreSQL password

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
├── database/schema.postgresql.sql  ← Import this first
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

---

## Install on Phone / Offline App Mode

This project is now a **Progressive Web App (PWA)**. Once opened in a supported mobile browser, it can be installed to the phone home screen and will cache the app shell for offline loading.

### Android / Chrome
1. Open the app URL in Chrome.
2. Tap the **Install app** button when it appears, or open Chrome menu -> **Add to Home screen**.
3. Open **Staycation Savings** from the home screen.

### iPhone / Safari
1. Open the app URL in Safari.
2. Tap **Share**.
3. Tap **Add to Home Screen**.

### Offline behavior
- The login screen, offline screen, app shell, and previously opened pages can load without internet.
- Recording payments, editing members, OTP email login, and fresh database data still need the PHP/PostgreSQL server connection.
- For full offline data entry on a phone with no server, the next step is converting the storage layer from PostgreSQL/PHP to browser storage such as IndexedDB, or packaging the app with a local mobile database.

---

## Fully Offline Android App

The project now includes a standalone offline Android build that does **not** need PHP, MySQL, XAMPP, or this source folder running after installation.

APK path:

```text
android-offline/build/outputs/apk/debug/app-debug.apk
```

This APK embeds the standalone app files from `standalone/` and stores data on the phone. Use the Backup screen inside the app to export/import JSON backups.
