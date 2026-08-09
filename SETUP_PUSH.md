# BibleSprint — Push notification setup (Hostinger cron + PHP)

Reminders that arrive even when BibleSprint isn't open in a browser tab. No terminal, no Node.js. About **20 minutes** if you've already done `SETUP_REMINDERS.md`'s Part 2 (the Firebase service account) — otherwise add ~10 minutes for that.

## What you'll set up

1. A **service account** in Firebase, so a PHP script can read your users. (Skip if you already did this for email reminders — it's the exact same file, reused.)
2. Upload **two things** to Hostinger: the `private/vendor/` folder (a pre-built PHP library, no Composer needed on your end) and `send-push-reminders.php`.
3. Create one **cron job** in Hostinger that runs the script every 15 minutes.

From then on: every 15 minutes, the script checks which signed-in users have a reminder due in the next quarter hour (their local time), and — if they still have unread sprints for today — sends a push notification straight to their device, tab open or not.

**Note:** push notifications only reach *signed-in* accounts. A guest (never signed in) has no server-reachable record for the script to find — this is a hard requirement of how the whole system works, not a bug.

---

## Part 1 — Firebase service account (skip if already done)

If you already completed Part 2 of `SETUP_REMINDERS.md`, skip straight to Part 2 below — this step is identical and the same file works for both.

1. Open <https://console.firebase.google.com> → your `bible-sprint` project.
2. Click the **gear icon** (top-left) → **Project settings**.
3. Click the **Service accounts** tab.
4. Scroll to "Firebase Admin SDK" → **Generate new private key** → confirm.
5. Your browser downloads a JSON file. **Rename it to exactly `firebase-service-account.json`.**
6. In Hostinger hPanel → **File Manager** → go **one level up from `public_html`** (so you're sitting alongside it, not inside it) → **New folder** named `private` (if it doesn't already exist) → open it → **Upload** the JSON file.

Treat this file like a password — it grants full read access to your user database. The `private` folder sits outside the web root, so it's never publicly reachable.

---

## Part 2 — Upload the push files

### 2.1 The `private/vendor/` folder

This is a pre-built PHP library (handles the push encryption/signing) — already assembled, no installation needed on your end.

1. In File Manager, go to the same `private` folder from Part 1 (one level above `public_html`).
2. Upload the `vendor` folder from the `private/` folder in this project (drag the whole folder, or zip it first and use Hostinger's "upload zip, then extract" option if your browser struggles with a folder that has ~1,200 files in it — extraction is usually faster than a raw folder upload).
3. When done, `private/` should contain both `firebase-service-account.json` (Part 1) and `vendor/` (this step), sitting next to (not inside) `public_html/`.

### 2.2 `send-push-reminders.php`

1. Go back into `public_html/`.
2. Upload `send-push-reminders.php` (same folder as `app.html`, `cron-reminders.php`, etc.)

---

## Part 3 — Create the cron job

1. hPanel → **Advanced** → **Cron Jobs**.
2. Under **Create a New Cron Job**:
   - **PHP** (already selected)
   - **Command to Run**: in the first box, leave the PHP path as shown (or pick your PHP version from the dropdown if there is one — see the note below). In the second box, type: `public_html/send-push-reminders.php`
   - **Minute**: `*/15` (every 15 minutes)
   - **Hour** / **Day** / **Month** / **Weekday**: leave as `*` (every one)
3. Click **Save**.

**Before you do this step**, check hPanel → **Advanced** → **PHP Configuration** and confirm the site is running **PHP 8.1 or newer** — the push library needs it. If it's older, switch it to 8.1+ there first (a standard dropdown change, doesn't affect anything else on the site).

---

## Part 4 — Test it

1. Open BibleSprint on your phone or laptop, **signed in** to your account.
2. Settings → tap **Enable notifications**. If you're signed in, it should say something like "Notifications on — you'll get reminders even when the app's closed."
3. Settings → **Daily reminder** → set the time to a few minutes from now.
4. Close the app / lock your phone.
5. Wait for the next 15-minute cron slot to pass your chosen time.
6. A notification should arrive, tap it — it opens (or focuses) BibleSprint.

If nothing arrives:
- In hPanel → Cron Jobs, check whether there's a way to view the job's output/logs (append `>> /home/USERNAME/push-log.txt 2>&1` to the command to capture one, same trick as `SETUP_REMINDERS.md`).
- Common errors in the log: "Service account not found" (Part 1 not done, or wrong path), "Class not found" (Part 2.1's `vendor` folder missing or incomplete).

---

## Privacy note

Same as email reminders: this runs entirely on your own Hostinger server against your own Firebase project. No third-party push service holds your users' data — Apple/Google/Mozilla's push infrastructure only ever sees an opaque, encrypted message and an endpoint URL, never reading-plan content.

---

## Changing things later

**Rotate the VAPID keys**: only do this if you suspect they've leaked — every existing subscriber would need to re-enable notifications, since the old key stops being trusted. If you ever do, the new public key must be updated in *both* `app.html` (`VAPID_PUBLIC_KEY`) and `send-push-reminders.php` (`$VAPID_PUBLIC_KEY`/`$VAPID_PRIVATE_KEY`) together.

**Pause push reminders temporarily**: hPanel → Cron Jobs → disable the job. Re-enable any time.
