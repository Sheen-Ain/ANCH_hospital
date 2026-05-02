# TokenMed — Hospital Reception Management System

A complete, production-ready PHP/MySQL hospital reception management system.
No frameworks. No Composer. Drop it into any Apache + PHP 8+ + MySQL environment and run.

---

## Features

### Admin Panel
- **Dashboard** — live stats, 7-day revenue bar chart, online staff widget, recent admissions
- **Doctors** — add, edit, toggle active/inactive, delete (blocked if patients exist)
- **Receptionists** — full CRUD, reset password separately, online-status indicator
- **Rooms** — card grid + table dual view, three types (General / Private / ICU), maintenance toggle
- **Admissions** — admit patients, edit, discharge with date/time, print admission slip
- **All Patients** — cross-date browser, date-range filter, paginated, receipt print
- **Payments** — revenue stats (today/week/month/all-time), update payment status and method
- **Settings** — 5-tab system (Hospital info, Tokens, Financial, Receipts, System)
- **Profile** — avatar upload, edit details, change password

### Receptionist Panel
- **Dashboard** — personal stats, doctor card grid showing next token numbers
- **Generate Token** — doctor card selection → patient form → token issued → receipt shown
- **Today's Patients** — live table, filter by doctor/payment, edit patient, update payment, print receipt
- **Admissions** — create admissions, discharge patients, print admission slips
- **Profile** — same as admin profile

### Shared Features
- Bilingual receipts (English + Urdu اردو) with hospital branding
- Admission slips with estimated cost breakdown
- Role-switch: admin can toggle into receptionist view and back
- CSRF protection on every POST
- Session-based rate limiting on login (10 attempts / 15 min)
- Auto-logout on inactivity (configurable)
- Online-status tracking via `last_seen` ping
- Dark mode (persisted in `localStorage`)
- Sound effects on token generation and payment (configurable)
- Full audit log

---

## Requirements

| Component | Minimum Version |
|-----------|----------------|
| PHP       | 8.0+           |
| MySQL     | 5.7+ / MariaDB 10.3+ |
| Apache    | 2.4+ with `mod_rewrite` |
| Browser   | Any modern browser (Chrome, Firefox, Edge, Safari) |

PHP extensions required: `pdo`, `pdo_mysql`, `mbstring`, `fileinfo`, `json`

---

## Installation

### 1. Download & Place Files

```bash
# Via git
git clone https://github.com/yourname/tokenmed.git /var/www/html/tokenmed

# Or extract the ZIP into your web server directory
```

### 2. Create the Database

```bash
mysql -u root -p
```

```sql
-- Inside MySQL:
SOURCE /path/to/tokenmed/schema.sql;
SOURCE /path/to/tokenmed/seed.sql;
```

Or use phpMyAdmin: import `schema.sql` first, then `seed.sql`.

### 3. Configure Database Credentials

Edit `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_mysql_user');
define('DB_PASS', 'your_mysql_password');
define('DB_NAME', 'token_med');

define('BASE_PATH', '/var/www/html/tokenmed');  // absolute filesystem path
define('BASE_URL',  'http://localhost/tokenmed'); // public URL
```

### 4. Set Directory Permissions

```bash
# The uploads directory must be writable by the web server
chmod 755 assets/uploads/profiles/

# Logs directory (optional, for PHP error logging)
mkdir -p logs
chmod 755 logs
```

### 5. Enable Apache mod_rewrite

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Ensure your Apache VirtualHost or Directory block has:
```apache
AllowOverride All
```

### 6. Open in Browser

Navigate to your configured `BASE_URL`, e.g.:
```
http://localhost/tokenmed
```

You will be redirected to the login page automatically.

---

## Default Login Credentials

| Role         | Email                          | Password  |
|--------------|-------------------------------|-----------|
| Admin        | admin@hospital.com            | admin123  |
| Receptionist | receptionist1@hospital.com    | rec123    |
| Receptionist | receptionist2@hospital.com    | rec123    |

> ⚠️ **Change all passwords immediately after first login.**

---

## Project Structure

```
tokenmed/
│
├── config.php                  ← DB credentials ONLY
├── index.php                   ← Root entry point (redirects to dashboard)
├── schema.sql                  ← Full database schema
├── seed.sql                    ← Sample data
├── .htaccess                   ← Apache security + caching rules
│
├── includes/
│   ├── db.php                  ← PDO singleton wrapper
│   ├── functions.php           ← ALL shared PHP functions
│   ├── settings.php            ← Loads settings table into $SETTINGS
│   └── auth_check.php          ← Auth guard entry point
│
├── auth/
│   ├── login.php               ← Login page (standalone)
│   └── logout.php              ← Destroys session, redirects
│
├── admin/
│   ├── header.php              ← Admin layout header (topnav + sidebar)
│   ├── footer.php              ← Admin layout footer (loads common.js)
│   ├── dashboard.php
│   ├── doctors.php
│   ├── receptionists.php
│   ├── rooms.php
│   ├── admissions.php
│   ├── patients.php
│   ├── payments.php
│   ├── settings.php
│   └── profile.php
│
├── receptionist/
│   ├── header.php              ← Receptionist layout header
│   ├── footer.php              ← Receptionist layout footer
│   ├── dashboard.php
│   ├── generate-token.php      ← Core token generation workflow
│   ├── patients.php            ← Today's patients management
│   ├── admissions.php
│   └── profile.php
│
├── ajax/
│   ├── login.php               ← Auth endpoint
│   ├── ping.php                ← last_seen updater (every 60s)
│   ├── switch-role.php         ← Admin role-switch endpoint
│   ├── print-slip.php          ← Shared receipt/slip HTML renderer
│   │
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── doctors.php         ← getDoctors, createDoctor, updateDoctor, deleteDoctor, toggleDoctor
│   │   ├── receptionists.php   ← Full CRUD + resetPassword
│   │   ├── rooms.php           ← Full CRUD + toggleRoom
│   │   ├── admissions.php      ← Full CRUD + dischargePatient + getFormData
│   │   ├── patients.php        ← getPatients (paginated), deletePatient
│   │   ├── payments.php        ← getPayments, updatePayment, getRevenueStats
│   │   ├── settings.php        ← getSettings, updateSettings, testEmail
│   │   ├── profile.php         ← getProfile, updateProfile, changePassword
│   │   └── audit-log.php       ← Recent activity log
│   │
│   └── receptionist/
│       ├── dashboard.php
│       ├── tokens.php          ← getDoctors, generateToken, updatePatient, updatePayment, deletePatient
│       └── profile.php         ← getProfile, updateProfile, changePassword
│
└── assets/
    ├── css/
    │   └── custom.css          ← Full design system (light + dark mode)
    ├── js/
    │   └── common.js           ← All shared JS (toasts, modals, AJAX, ping, clock…)
    ├── sounds/
    │   ├── cash-register.mp3   ← Played on token generation / paid payment
    │   └── success.mp3         ← Played on admissions / other successes
    └── uploads/
        └── profiles/           ← User profile images (writable by web server)
```

---

## Sound Effects

The app plays sounds on key events. Add these files to `assets/sounds/`:

- `cash-register.mp3` — token generated, payment marked paid
- `success.mp3` — admission created, patient discharged

Any royalty-free MP3 clips work. Sounds can be disabled system-wide in **Settings → System → Sound Effects**.

---

## Security Notes

1. **`config.php` must never be committed to version control.** It is in `.gitignore`.
2. All AJAX endpoints validate CSRF tokens on every POST.
3. All database queries use PDO prepared statements — no string concatenation.
4. Passwords use `password_hash(PASSWORD_BCRYPT)` — never stored in plain text.
5. Session IDs are regenerated on login (`session_regenerate_id(true)`).
6. The `includes/` directory is blocked from direct browser access via `.htaccess`.
7. File uploads are validated by MIME type and size before saving.
8. For production, enable HTTPS and set `session.cookie_secure = On` in `.htaccess`.

---

## Customisation

### Changing the hospital name, address, or currency symbol
Go to **Admin → Settings → Hospital** or **Settings → Financial**.
These are stored in the `settings` table and applied everywhere (receipts, login page, slips).

### Adding a new doctor specialization
Doctors are free-text — just type any specialization when adding a doctor.

### Token numbering
- Tokens are **per-doctor, per-day** — they reset to 1 for each doctor each day.
- The prefix (e.g. `TKN`) and max tokens per day are configurable in **Settings → Tokens**.

### Extending the system
Each module follows the same pattern:
1. `admin/module.php` — view page
2. `ajax/admin/module.php` — single AJAX file with action-based dispatch
3. All functions in the AJAX file, all shared helpers in `includes/functions.php`

---

## Database Schema Summary

| Table        | Purpose                                              |
|--------------|-----------------------------------------------------|
| `roles`      | Admin (1), Receptionist (2)                        |
| `users`      | All staff — role determined by `role_id`           |
| `doctors`    | Doctor records with fee and specialization         |
| `tokens`     | Per-doctor, per-day token numbers (unique key)     |
| `patients`   | Patient visits linked to token + doctor + receptionist |
| `payments`   | One payment record per patient (1:1)               |
| `rooms`      | Hospital rooms with type and daily fee             |
| `admissions` | Inpatient admissions linked to room + doctor       |
| `settings`   | Key-value system configuration                     |
| `audit_logs` | Activity log for all staff actions                 |

---

## License

MIT — free to use, modify, and distribute.
