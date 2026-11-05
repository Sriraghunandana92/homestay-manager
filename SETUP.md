# Homestay Maintenance: setup on Hostinger

This package is the same app you used in Claude (Today, Meals, Calendar, Bookings, Payments & expenses, Bills, Products), running on **your own hosting with a MySQL database** and a username/password login.

```
homestay-app/
├── upload/                  ← everything in here goes onto your website
│   ├── index.html           the app
│   ├── install.php          one-time setup (creates your login; delete it afterwards)
│   ├── .htaccess            forces HTTPS, blocks sensitive files
│   ├── assets/jspdf.umd.min.js   PDF bills (MIT licence)
│   └── api/                 PHP back end
│       ├── config.sample.php  → copy to config.php and add your database details
│       ├── lib.php            shared helpers (database, login, security)
│       ├── auth.php           login, logout, staff logins, password change
│       ├── bookings.php       bookings + payments + meals + products bought
│       ├── expenses.php
│       └── settings.php       rooms, products, prices, bill details
├── database/                ← do NOT upload these; import them in phpMyAdmin
│   ├── schema.sql             creates the tables, your 5 rooms and 8 products
│   └── import-current-data.sql  your bookings from the Claude app
└── SETUP.md                 this guide
```

---

## 1. Which Hostinger plan

You plan to build more small tools, so pick a plan that holds several sites and has **daily backups**.

| Plan | Websites | Backups | Good for |
|---|---|---|---|
| Premium Web Hosting | 3 | Weekly | This app plus one or two more tools |
| **Business Web Hosting** (recommended) | 50 | Daily | A collection of tools, each on its own subdomain |
| Cloud Startup | 100 | Daily | Later, if traffic grows a lot |

All of these run PHP 8 and MySQL, which is what this app needs. Intro prices apply to long terms (for example 48 months) and **renewal prices are much higher**, so compare the renewal price at checkout. If you later write tools in **Python (Django/FastAPI)**, those need a **Hostinger VPS** because shared hosting runs PHP; this PHP app runs on either.

## 2. Domain and subdomain

Use your homestay domain and give each tool its own subdomain, for example:

- `manage.yourhomestay.com` → this app
- `stock.yourhomestay.com`, `invoice.yourhomestay.com` → future tools

In hPanel: **Websites → Manage → Domains → Subdomains** → create `manage`. Hostinger creates a folder for it (shown on that screen).

## 3. Turn on SSL (HTTPS)

hPanel → **Security → SSL** → install the free SSL for `manage.yourhomestay.com`. Wait until it shows *Active*. The app forces HTTPS, so do this before opening it.

## 4. Set PHP version

hPanel → **Advanced → PHP Configuration** → choose **PHP 8.2 or newer**.

## 5. Create the database

hPanel → **Databases → Management** (MySQL Databases):

1. Database name: `homestay` → Hostinger makes it `u123456789_homestay`.
2. Username: `homestay` → becomes `u123456789_homestay`.
3. Use a long random password and save it in your password manager.

Write down the full database name, full username and password.

## 6. Create the tables and bring your bookings across

1. On the same page, click **Enter phpMyAdmin** next to the new database.
2. Click the database name on the left, then the **Import** tab.
3. Choose `database/schema.sql` → **Import**. This creates the tables, your 5 rooms and your 8 products.
4. Import again with `database/import-current-data.sql`. This adds your bookings (Raghu, Chethana K, Nandana).

> If you add more bookings in the Claude version before switching, ask Claude to regenerate `import-current-data.sql` just before you import.

## 7. Upload the app

1. hPanel → **Files → File Manager** → open the folder of the `manage` subdomain.
2. Upload the **contents** of the `upload/` folder (not the folder itself). Easiest: zip the contents, upload the zip, right-click → **Extract**, then delete the zip.
3. Turn on **Show hidden files** (in File Manager settings) and check that `.htaccess` and `api/.htaccess` are there.

## 8. Connect the app to the database

1. In `api/`, copy `config.sample.php` and name the copy `config.php`.
2. Edit `config.php` and fill in the database name, username and password from step 5. Leave `db_host` as `localhost`.
3. Save.

## 9. Create your login

1. Open `https://manage.yourhomestay.com/install.php`.
2. Enter your name, a username and a strong password → **Create login**.
3. **Delete `install.php`** in File Manager right away.

## 10. First steps in the app

1. Open `https://manage.yourhomestay.com` and log in.
2. **Rooms & settings**: homestay name, address, phone, UPI ID, standard check-in/out times, meal prices, product prices, GST (only if registered).
3. Under **Logins**, add a login for each staff member. Each person gets their own username, and you can remove it when they leave.
4. On your phone, open the site in Chrome → menu → **Add to Home screen**, so it opens like an app.

---

## Backups (please do this)

- The Business plan keeps **daily backups** (hPanel → Files → Backups).
- Also, once a month: phpMyAdmin → your database → **Export** → Quick → SQL, and keep the file on Google Drive. Restoring is Import.

## Security checklist

- [ ] SSL active, site opens on `https://`
- [ ] `install.php` deleted
- [ ] `config.php` has `'debug' => false`
- [ ] Strong passwords; staff use their own logins
- [ ] `database/` folder **not** uploaded to the website

Built in: passwords stored as hashes; 10 wrong attempts blocks that network for 15 minutes; every change is checked against a per-login token (CSRF); all database queries use prepared statements; the app tells search engines not to index it.

## Troubleshooting

| You see | Fix |
|---|---|
| "Setup is not finished: copy api/config.sample.php…" | Step 8 not done, or the file is named `config.php.txt` |
| "Could not connect to the database" | Wrong name, user or password in `config.php`. Use the full names with the `u123…_` prefix |
| install.php says tables are not there | Import `schema.sql` (step 6) |
| Redirect loop or "not secure" | SSL not active yet (step 3) |
| Logged out often | Normal after 30 days. If it happens daily, check that the `api/.sessions` folder exists and is writable |

---

## Building your next tools on the same pattern

This app is a template you can reuse:

1. Create a subdomain for the new tool (step 2) and a **separate database** for it (step 5). Keeping each tool's data separate means a mistake in one can't damage another.
2. Copy `api/lib.php`, `api/auth.php`, `install.php`, both `.htaccess` files and the `users` + `login_attempts` tables from `schema.sql`. That gives the new tool a login and security straight away.
3. Write the tool's own endpoints, such as `api/items.php`, the way `expenses.php` is written: `require_login()` at the top, GET to list, POST to save, DELETE to remove.
4. Front end: one `index.html` that calls the endpoints with the same `api()` helper used in this app.

If you later want one login for all your tools, the next step is a small "hub" site holding the users table that the tools share. That's worth doing once you have three or more tools.
