# BARBERSHOP.CO — Setup Guide

## 1. Requirements
- XAMPP (Apache + MySQL + PHP 8+)

## 2. Installation Steps

1. Copy the whole `barbershop.co` folder into `C:\xampp\htdocs\` (or your `htdocs` folder).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
4. Click the **SQL** tab.
5. Open the file `mysql/schema.sql` from this project, copy ALL of its contents, paste it into the SQL box, and click **Go**.
   - This creates the `barbershop_database` database with all tables and starter data (5 packages, 3 barbers, 1 admin account).
6. Visit `http://localhost/barbershop.co/` in your browser.
7. **Set up email verification (optional but recommended)** — see section 6 below. If you skip this, registration still works fine; new accounts are just automatically verified instead of receiving a real email.

**Already have this project installed and just want the newest update?**
See section 8 (Updating an Existing Installation) below instead of re-importing
`schema.sql` from scratch — that would erase your existing data.

## 3. Default Admin Account

- **Phone Number:** `09999999999`
- **Password:** `Admin123!`

Login at `pages/login.php` — since this account's role is Administrator, it will
automatically redirect to the Admin Dashboard (`admin/dashboard.php`).
**Please change this password after logging in (My Profile → Change Password).**

## 4. Folder Structure

```
barbershop.co/
├── admin/                    → Admin dashboard pages (protected, Administrator only)
├── auth/                     → Form processors (login, register, booking, payment, cancel, refund, etc.)
├── db/                       → Database connection
├── includes/                 → Shared header/footer/config/booking-helpers/mail-config/guards
├── libs/
│   └── PHPMailer/            → The PHPMailer email library (used for verification emails)
├── mysql/
│   ├── schema.sql            → Full database schema + seed data (fresh install)
│   ├── migration_v2.sql      → Run if updating from the very first version
│   ├── migration_v3.sql      → Live Queue columns
│   ├── migration_v4.sql      → Refund requests table
│   └── migration_v5.sql      → Email verification columns
├── pages/                    → Customer-facing pages (login, booking, payment, verify email, etc.)
├── scripts/
│   └── live-countdown.js     → Powers every live countdown + page auto-refresh
├── styles/                   → CSS + images
├── uploads/                  → Payment proofs, profile images, refund proofs
└── index.php
```

## 5. What's New in This Version

**Real email verification on registration**
When someone registers, the system now emails them a real verification link
(using PHPMailer) before they can be considered a confirmed account. This is
the industry-standard way to make sure an email address is real and actually
belongs to the person signing up — there's no reliable way to just "check if
a Gmail address exists" without sending something to it and having the owner
confirm receipt, which is exactly what this does.
- A **notification bell reminder** appears for unverified accounts, with a
  one-click "Resend verification email" option.
- **Important:** login is *not* blocked for unverified accounts. This is a
  deliberate safety choice — if the email server were ever misconfigured, no
  real customer should get permanently locked out of their own account. See
  section 6 to set up real email sending.
- **Phone numbers** are validated for correct Philippine mobile format
  (`09XXXXXXXXX`) same as before. True phone number *existence* verification
  would require a paid SMS/OTP service (e.g. Twilio, Semaphore) — that's a
  separate, ongoing-cost integration beyond what a format check or a free
  library can do, so it isn't included here.

**Fixed: only one barber showing up when booking**
`getBookingAvailability()` (which decides which times look "open") wasn't
checking each barber's Available/Busy/Offline status the same way the
barber-picker does — so a time could look bookable even when the only
"available" barbers for it were actually marked Busy/Offline. Both now use
the exact same rule. **If you're still only seeing one or two barbers**,
double-check Admin → Barbers — a barber marked "Busy" or "Offline" there
won't show up as choosable, by design.

## 6. Setting Up Real Email Sending (PHPMailer + Gmail)

Open `includes/mail_config.php` and fill in these two lines with a **real
Gmail account**:

```php
define("SMTP_USERNAME", "your-gmail-address@gmail.com");
define("SMTP_APP_PASSWORD", "your-16-character-app-password");
```

You cannot use your normal Gmail password here — Google requires a special
**App Password** for this kind of access:

1. Go to your Google Account → Security → turn on **2-Step Verification** if
   it isn't already on: https://myaccount.google.com/security
2. Go to https://myaccount.google.com/apppasswords
3. Create a new app password (name it anything, e.g. "BarbershopCO"), choose
   **Mail** as the app.
4. Google gives you a 16-character password like `abcd efgh ijkl mnop` —
   paste that into `SMTP_APP_PASSWORD` above (spaces are fine either way).
5. Save the file. That's it — no restart needed.

Once this is filled in, every new registration will receive a real
verification email with a clickable link. Until then, the system quietly
skips sending and auto-verifies new accounts instead, so nothing breaks.

## 7. Appointment Status Lifecycle (Reference)

```
Pending  →  Confirmed  →  Waiting  →  Completed
   ↓            ↓            ↓
Cancelled    No Show      (auto after service duration ends)
(auto if     (auto if
unpaid and   never checked
time passed) in on time)
```

- **Pending** — booked, payment not yet verified. Auto-Cancels if its time
  passes unpaid.
- **Confirmed** — payment verified, scheduled. Auto-marks No Show if the
  customer never gets checked in within the grace period after start time.
- **Waiting** — admin has checked the customer in; actively being served.
  Auto-completes once the service's full duration has passed.
- **Completed** — done. Frees the barber automatically.
- **Cancelled / No Show** — final, cannot be changed (a new booking is
  always possible instead).

## 8. What's Included From Earlier Updates

- **Barber daily capacity** — up to 4 active bookings per barber per day,
  shown as e.g. "2/4 slots" everywhere a barber is listed.
- **Notification bell**, **refund requests**, **customer self-cancel**,
  **choose-your-own-barber**, **age verification for online payments**, **one
  active booking at a time**, business hours, holiday calendar, Reservation
  fallback with a ₱100 fee, GCash/PayMaya/Cash with proof verification, and
  the full Admin Dashboard (stats, barbers, services, reports, calendar,
  messages, requests).

## 9. Updating an Existing Installation

1. Replace all the project files with this new version.
2. In phpMyAdmin's SQL tab, run `mysql/migration_v5.sql`. If you're not sure
   you're caught up on older ones, it's safe to also run `migration_v2.sql`,
   `migration_v3.sql`, and `migration_v4.sql` in that order — re-running ones
   you've already applied is a harmless no-op.
3. Set up email sending per section 6 above (optional).

## 10. Notes for Your Defense / Demo

- **Email verification demo**: set up section 6 with a real Gmail account
  beforehand, then register a new test account with an email you can check —
  show the verification email arriving and the link working. If you'd rather
  not set up email at all, registering still works fine and just skips
  straight to "verified" — mention that this is the built-in safe fallback.
- **Barber capacity demo**: book 4 appointments for the same barber on the
  same day — on the 5th attempt, that barber shows "Full 4/4" and can't be
  selected.
- **Full lifecycle demo**: book as a customer, verify the payment as admin
  (→ Confirmed), set status to "Waiting" (→ live countdown based on the
  service's duration), then let it finish or mark "Completed" manually — the
  barber frees up automatically either way.
- **Refund request demo**: verify a payment, then cancel that booking from
  My Bookings — you'll be taken to the refund request form; submit it and
  check Admin → Requests.
- To simulate a fully-booked day, book several appointments back-to-back for
  one barber, then try booking again at the same time — you'll see the
  automatic Reservation fallback.
- To test the age restriction, register a test account with a birthdate less
  than 18 years ago, then try to pay — GCash/PayMaya will be disabled.
