# BibleSprint — Email reminders setup (Hostinger cron + PHP)

Simple path, no terminal, no Node.js, no hire. About **30 minutes of pointing and clicking** in two dashboards you already use (Hostinger and Firebase). No ongoing cost.

## What you'll set up

1. Create a sender email address `reminders@biblesprint.com` on Hostinger.
2. Create a **service account** in Firebase so a PHP script can read your users.
3. Upload **two files** to Hostinger: the service account JSON (outside `public_html`, so nobody on the web can see it) and `cron-reminders.php` (inside `public_html`).
4. Edit the top of the PHP file to paste in your SMTP password.
5. Create one **cron job** in Hostinger's point-and-click UI that runs the script every 15 minutes.

Done. From then on, the script runs every 15 minutes, checks which of your users has a reminder due in the next quarter hour (in their local timezone), and sends each one a branded email with today's reading.

---

## Part 1 — Create `reminders@biblesprint.com` on Hostinger

You already have "Starter Business Email" on Hostinger.

1. In Hostinger **hPanel** → left sidebar → **Emails** → pick **Email accounts** for `biblesprint.com`.
2. Click **Create email account**. Local part: `reminders`. Password: set a strong one and **save it somewhere safe** — you'll paste it into the PHP file in a moment.
3. Click the new address → **Configuration settings** (or **Connect apps / SMTP settings**). Note the SMTP host (typically `smtp.hostinger.com`) and port `465`. You'll need these two values + the password in Part 4.
4. Optional sanity check: send yourself a test email via Hostinger's webmail at <https://webmail.hostinger.com>.

---

## Part 2 — Create a Firebase service account

A "service account" is a machine-readable Google account that gives your PHP script permission to read the BibleSprint user database. It's how the script authenticates to Firestore.

1. Open <https://console.firebase.google.com> → your `bible-sprint` project.
2. Click the **gear icon** (top-left) → **Project settings**.
3. Click the **Service accounts** tab at the top.
4. Scroll down to "Firebase Admin SDK". Click **Generate new private key**. A popup warns this file is sensitive — click **Generate key**.
5. Your browser downloads a JSON file (something like `bible-sprint-firebase-adminsdk-xxxxx.json`). **Move this to your Desktop** and **rename it to exactly `firebase-service-account.json`**.

**Important**: Treat this file like a password. Anyone who has it can read all your users' data. Next step makes sure it's not web-accessible.

---

## Part 3 — Upload the two files to Hostinger

### 3.1 Upload the service account JSON (above `public_html`)

1. In hPanel → **File Manager** for biblesprint.com.
2. Don't go into `public_html` yet — you'll see a folder named something like `domains/biblesprint.com/public_html`. Go **one level up** so you're sitting alongside `public_html` (not inside it).
3. Click **New folder**, name it `private`. You should now see `public_html` and `private` as siblings.
4. Double-click into `private`.
5. Click **Upload** and select `firebase-service-account.json` from your Desktop. Confirm upload.

This folder is outside the web root, so nothing uploaded there is visible on the internet — even if someone guesses the filename.

### 3.2 Upload `cron-reminders.php` (inside `public_html`)

1. Back out, double-click into `public_html`.
2. Click **Upload** and select `cron-reminders.php` from your Desktop (it's in the same `deploy/` folder as `index.html` and `privacy.html`).
3. Confirm upload.

---

## Part 4 — Edit the PHP file to add your SMTP password

1. In File Manager, right-click `cron-reminders.php` → **Edit** (opens Hostinger's in-browser text editor).
2. Near the top of the file you'll see a block like this:

```php
$SMTP_HOST = 'smtp.hostinger.com';
$SMTP_PORT = 465;
$SMTP_USER = 'reminders@biblesprint.com';
$SMTP_PASS = '';  // leave blank to use mail() instead of SMTP
```

3. Paste your SMTP password between the two quotes on the `$SMTP_PASS` line:

```php
$SMTP_PASS = 'your-actual-password-here';
```

4. **Save** (Ctrl+S or the Save button). Close the editor tab.

(If you leave `$SMTP_PASS` blank, the script falls back to PHP's built-in `mail()` function. That usually works on Hostinger but can land in spam more often than authenticated SMTP. Fill in the password for best deliverability.)

---

## Part 5 — Create the cron job in Hostinger

1. In hPanel → left sidebar → **Advanced** → **Cron jobs** (or search "cron" in hPanel's search bar).
2. Under **Create a new cron job**, there are fields for the schedule. Set:
   - **Type**: choose `Custom` or `Advanced`
   - **Minutes**: `*/15`  (means every 15 minutes)
   - **Hours**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
3. In the **Command to run** field, paste:
   ```
   /usr/bin/php /home/USERNAME/public_html/cron-reminders.php >/dev/null 2>&1
   ```
   Replace `USERNAME` with your Hostinger username (you'll see it in the File Manager URL or hPanel — it's usually `u` followed by numbers). The exact path might differ slightly — Hostinger sometimes shows the correct path when you click the PHP file in File Manager and look at **File details**.
4. Click **Create**.

The cron job is now live. It will run the PHP script every 15 minutes indefinitely.

---

## Part 6 — Test it

1. Open the BibleSprint app on your phone (while signed into your test account).
2. **Settings → Daily reminder**: set the time to about **16 minutes from now**, and toggle **Email me** on.
3. Wait for the next 15-minute cron slot to pass your chosen time. For example, if you set 14:32 as the reminder time, the cron running at 14:45 will catch it and send.
4. Check your email inbox. The email should arrive from `reminders@biblesprint.com` with the subject "Day X — today's BibleSprint reading".

If nothing arrives:
- Check the spam/junk folder first.
- In hPanel → **Cron jobs**, click your cron job → **Logs** (if available) or set a log path in the cron command, e.g. replace `>/dev/null 2>&1` with `>> /home/USERNAME/reminder-log.txt 2>&1` so output gets captured to a log file. Then view the log in File Manager.
- Common errors are in the log: "Service account not found" (Part 3 path wrong), "Auth failed" (service account JSON invalid), "SMTP connect failed" (password wrong or port blocked).

---

## Privacy note

This PHP script runs on your own Hostinger server and reads user data from your own Firebase. No third-party service is involved. The `firebase-service-account.json` file is the only sensitive credential — keep it in the `private` folder (not `public_html`) and don't share it.

---

## Changing things later

**Change the reminder email design**: edit `cron-reminders.php` in Hostinger File Manager. The HTML template is in the `buildEmailHtml` function near the bottom. Save — next cron run uses the new template.

**Pause reminders temporarily**: hPanel → Cron jobs → three-dot menu → Disable. Nothing fires while disabled. Re-enable any time.

**Rotate the SMTP password**: if you change the password on the Hostinger email, remember to update the `$SMTP_PASS` value inside `cron-reminders.php`.

**Deliverability** (optional, recommended once you have real users): add SPF/DKIM/DMARC DNS records on GoDaddy to improve inbox placement on Gmail, Outlook, Yahoo. Hostinger's hPanel → Emails → DKIM shows the exact records to add. ~15 minutes on GoDaddy's DNS page.
