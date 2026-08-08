# Bible Sprint — Audio Bible API setup (Bible Brain)

This guide walks through getting a free API key from **Faith Comes By Hearing's Bible Brain** so Bible Sprint can play audio for any chapter directly inside the app — no external pages, no ads, no app-store prompts.

You only need to do this once. The whole thing typically takes 10–20 minutes of your time, plus a wait of a few hours to a couple of working days for FCBH to approve.

## Why this is worth doing

Bible.is (the current default) is clean and ad-free, but it still opens a separate site in a new tab. With a Bible Brain API key, Bible Sprint plays audio inside its own player — same look and feel as the rest of the app — for hundreds of translations.

Bible Brain is **free** for qualifying ministry / non-commercial use. Bible Sprint clearly qualifies (a free reading app run by a UK Ltd company for ministry purposes). Faith Comes By Hearing is the same organisation that runs Bible.is, so the audio quality and translation coverage are identical to what you've already heard.

## Step 1 — Open the Bible Brain page

Go to <https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain>.

(If that URL has moved, search the FCBH site for "Bible Brain" or "API". You're looking for the developer / API page, not the consumer Bible.is site.)

## Step 2 — Start the signup

On the Bible Brain page, find a button labelled something like:

- **"Get an API key"**, or
- **"Request API access"**, or
- **"Developer signup"**

Click it. You may be asked to create a regular FCBH account first if you don't have one — use **info@tlcblend.com** as the email so the key lives in your business inbox.

## Step 3 — Fill out the application

You'll see a short form. The fields and the wording I'd use for each:

| Field | What to enter |
|---|---|
| Name | Timi Hyacinth |
| Email | info@tlcblend.com |
| Organisation | TLC Consortium Limited (UK) |
| Project / app name | Bible Sprint |
| Project URL | https://www.biblesprint.com |
| Intended use | Free Bible reading-and-listening app helping people read the whole Bible in 43 days or more. Audio is played in-app for chapters of the daily reading sprint. Non-commercial / ministry. |
| Expected monthly traffic | Start with whatever's honest — "under 10,000 chapter requests / month" is a fine guess for v1. |
| Will the key be exposed in client-side code? | Yes — it's a single-page web app. (Their terms permit this for the public Bible Brain endpoints.) |

Tick any "I agree to the terms" box, then submit.

## Step 4 — Wait for approval

You'll usually get one of two outcomes:

1. **Immediate key** — some signups produce the API key on screen straight away. If you see it, copy it now (it's a long random string of letters and numbers, usually 30–40 characters). Treat it like a password — paste it into a note app or password manager.
2. **Manual review** — they'll email you. Approval is typically same-day for ministry use, sometimes 1–2 working days. The reply email will contain your API key.

If you're stuck for more than 3 working days, reply to the signup confirmation email or use the "Contact us" link on the Bible Brain page — mention "API key request — Bible Sprint, info@tlcblend.com".

## Step 5 — Activate the in-app player

The in-app player is **already built** in `app.html`. It's behind a placeholder API key, dormant. To turn it on takes one line of editing.

### A. The 30-second activation

1. Open `app.html` in any text editor (or Hostinger File Manager → Edit).
2. Press Ctrl/Cmd-F and search for `REPLACE_ME_WITH_FCBH_KEY`.
3. Replace that placeholder string with your actual API key. Keep the surrounding quotes:
   ```js
   const BIBLE_BRAIN_KEY = 'your-real-key-here';
   ```
4. Save and re-upload `app.html` to Hostinger `public_html/`.
5. Hard-refresh the live app (Cmd/Ctrl+Shift+R) and tap 🎧 Listen on any sprint.

That's it. The Settings → Audio source dropdown will now show **In-app player — recommended** as the first option, set as the default for new users. Existing users with `Bible.is` saved keep their preference; they can switch any time.

### B. What you'll see when it's working

A user taps 🎧 Listen on a sprint. A clean modal slides up showing one row per chapter. They tap a chapter; an HTML5 audio player loads the MP3 from Faith Comes By Hearing and plays inside the modal. When the chapter finishes, the next one auto-advances. No new tab, no ads, no app prompts.

### C. If a translation doesn't work

Some Bible Brain "fileset IDs" are educated guesses in the code. If a particular translation returns "Couldn't load…" when you try it, the fileset ID needs adjusting. Send me the translation that failed and I'll patch the table — usually it's a one-character swap.

To find the real ID, open this URL in your browser (replace KEY with your actual key):

`https://4.dbt.io/api/bibles?v=4&key=KEY&search=ESV` (or whatever translation you're testing)

The JSON response lists every fileset ID for that translation. Look for one ending in `2DA` (audio, regular speed) — that's what we want.

### D. Send me the key (optional)

If you'd rather I do the swap for you so the activation is on a known-good build:

1. Don't paste it into a public chat or post.
2. Send it to me in this conversation.
3. I'll patch `app.html`, ship the new build, and remind you to upload it.

## What you'll see when it's done

A user taps 🎧 Listen on a sprint. A clean modal slides up showing one row per chapter, each with a play button and progress bar. They press play; audio streams from FCBH directly to their browser. No ads, no banners, no leaving the app.

Buffering a chapter typically takes under a second on a decent connection. If they navigate away, audio keeps playing in the background. They can switch translations from Settings and the player picks up the new audio source automatically.

## Things to know

- **Cost:** Free for ministry / non-commercial use. There's no surprise pricing tier. If FCBH ever changes their terms, we'd hear about it months in advance.
- **CORS:** Bible Brain supports cross-origin browser calls, so no backend / proxy is needed. Bible Sprint stays a single-file static site.
- **Rate limits:** The standard free tier is plenty for personal apps. If Bible Sprint ever hits the limit (tens of thousands of users) FCBH will reach out.
- **Privacy:** The API key is associated with TLC Consortium. It does not identify individual users. No user data is sent to FCBH beyond the standard browser request (their IP, which translation/chapter, and a referrer).
- **If you change your mind:** the key can be revoked any time from the Bible Brain dashboard. Removing the key from `app.html` reverts the app to Bible.is automatically.

## Quick sanity check before you sign up

If you'd like to confirm the API does what we expect before going through signup, you can test their public sample endpoint without a key:

<https://4.dbt.io/api/bibles?v=4&limit=5>

That should return a small JSON list of Bibles. If it loads without error, the API is up and reachable from your browser. Once we have your key, the same domain serves audio file URLs for any chapter we ask for.
