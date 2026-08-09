# Bible Sprint — deploy checklist

A single clean reference for uploading Bible Sprint to Hostinger. Use this whenever you're doing a fresh re-deploy or a partial update.

## Where files live

The Hostinger account hosts `biblesprint.com` from a folder called `public_html/`. Anything in there is publicly served at `https://www.biblesprint.com/{filename}`. Anything in a subfolder is served at `https://www.biblesprint.com/{subfolder}/{filename}`.

There's nothing else to configure. No build step, no compile, no DNS work — that's all done. Upload files; they're live.

## The full file inventory

Here's exactly what `public_html/` should contain when everything is up to date.

```
public_html/
├── index.html                      ← the home/landing page
├── home.html                       ← duplicate of index.html, for direct links
├── app.html                        ← the Bible Sprint web app
├── about.html                      ← founder bio + TLC company info
├── contact.html                    ← Formspree contact form
├── privacy.html                    ← privacy policy
├── google25a592169f9aa8b7.html     ← Google Search Console verification (don't delete!)
│
├── hero.jpg                        ← banner image, JPEG fallback for older browsers
├── hero.webp                       ← banner image, smaller modern format
├── og-image.png                    ← 1200×630 social-share preview image
│
├── sitemap.xml                     ← lists every public page for Google
├── robots.txt                      ← crawler instructions
│
└── blog/                           ← blog subfolder
    ├── index.html                  ← blog listing page
    ├── why-i-read-the-whole-bible-in-43-days.html  ← launch post
    └── _template.html              ← template for new posts (has noindex, safe to leave)
```

That's it. Nothing else should be in `public_html/`.

## What you should NEVER upload

These files live on your Desktop next to the website files, but they are **not** for the public site. They're reference docs for you (and anyone else maintaining Bible Sprint). Don't put them in `public_html/`.

```
SETUP_DEPLOY.md      ← this file
SETUP_AUDIO.md       ← Bible Brain (FCBH) API setup walkthrough
SETUP_BLOG.md        ← how to publish a blog post
SETUP_CIRCLES.md     ← Firestore security rules for Sprint Circles
SETUP_SEO.md         ← Search Console / Bing Webmaster onboarding
SETUP_REMINDERS.md   ← email reminder cron job walkthrough
SETUP_PUSH.md        ← push notification cron job walkthrough
```

If you ever accidentally upload one, just delete it from `public_html/` — Markdown files render as raw text in browsers, so nothing dangerous happens, just noise.

## Doing a full upload from scratch

If you ever rebuild the site from a blank Hostinger setup, follow these in order. Order matters because some files reference others.

### Step 1 — Open Hostinger File Manager

Hostinger dashboard → **Websites** → click the row for `biblesprint.com` → **File Manager** in the left sidebar. Double-click `public_html/`.

### Step 2 — Clear out anything that's not yours

Delete any leftover placeholders inside `public_html/` (`default.php`, `index.php`, etc.) — but **never** delete `google25a592169f9aa8b7.html` if it's already there. That's your Search Console verification file; deleting it un-verifies the site.

### Step 3 — Create the blog folder first

Click **New Folder** in the File Manager toolbar. Name it `blog` (lowercase, no quotes). Enter the new folder.

### Step 4 — Upload the three blog files

Inside `blog/`, click **Upload** and select these three files from your Desktop:

- `blog/index.html`
- `blog/why-i-read-the-whole-bible-in-43-days.html`
- `blog/_template.html`

### Step 5 — Go back up to public_html

Click `..` or the breadcrumb `public_html` to leave `blog/`.

### Step 6 — Upload everything else

Click **Upload** and select all the top-level files at once (Hostinger lets you multi-select):

- `index.html`
- `home.html`
- `app.html`
- `about.html`
- `contact.html`
- `privacy.html`
- `hero.jpg`
- `hero.webp`
- `og-image.png`
- `sitemap.xml`
- `robots.txt`

(If the Search Console verification file `google25a592169f9aa8b7.html` isn't already there, you'll need to re-add it from your Desktop too.)

### Step 7 — Sanity check

Open these in fresh browser tabs and confirm each loads:

- <https://www.biblesprint.com/> → home page with the Bible banner and *Open app* button
- <https://www.biblesprint.com/about.html> → About page with your bio
- <https://www.biblesprint.com/contact.html> → contact form
- <https://www.biblesprint.com/blog/> → blog listing with the launch post
- <https://www.biblesprint.com/app.html> → the Bible Sprint app
- <https://www.biblesprint.com/sitemap.xml> → raw XML listing your pages

If anything 404s, the file isn't where it should be — check `public_html/` for the missing file.

## Doing a partial update (the common case)

You won't usually re-upload everything. Most updates touch one or two files. Here's the typical pattern.

| What changed | Files to re-upload |
|---|---|
| App-only fix (new feature, bug fix, copy tweak inside the app) | `app.html` |
| Home-page copy or styling | `home.html` and `index.html` (always re-upload both — they're identical) |
| About page | `about.html` |
| Contact page | `contact.html` |
| Privacy policy | `privacy.html` |
| New blog post | the new post HTML file (into `blog/`), `blog/index.html` (with the new card), `sitemap.xml` (with the new entry) |
| New banner image | `hero.jpg` and `hero.webp` |
| Search Console / Bing additions | `sitemap.xml`, `robots.txt` |

When you upload a file with the same name as one already in the folder, Hostinger asks if you want to replace it. Click **Yes / Replace**.

## After uploading — clearing the cache

Browsers and CDNs sometimes serve the old version for a few minutes after you upload. To force a refresh:

- **In your own browser:** open the live page, then press **Cmd+Shift+R** (Mac) or **Ctrl+Shift+R** (Windows). That's a "hard refresh" — bypasses local cache.
- **For users you've shared the link with:** they'll see the new version within ~5 minutes automatically. There's nothing you need to do.
- **If even hard refresh doesn't show the new version after 10 minutes:** the file you uploaded isn't actually the latest one. Open it in Hostinger File Manager → Edit → confirm the contents match what you expect. Common cause: dragging the wrong file from Desktop, or upload silently failed.

## Things that are NOT just file uploads

A few changes can't be deployed by uploading to Hostinger — they live elsewhere:

- **Firebase security rules** (Sprint Circles permissions). Edit at <https://console.firebase.google.com> → Firestore → Rules → Publish. See `SETUP_CIRCLES.md`.
- **Firebase config** (the `FIREBASE_CONFIG` block in `app.html`). That's already wired in; only revisit if you re-create the Firebase project from scratch.
- **Formspree form** (`info@tlcblend.com` notification routing). Configured in your Formspree dashboard.
- **Bible Brain API key** (when it arrives). Open `app.html`, search for `REPLACE_ME_WITH_FCBH_KEY`, paste your key, re-upload `app.html`. See `SETUP_AUDIO.md`.
- **Domain DNS** (already configured — GoDaddy → Hostinger nameservers). Don't touch unless migrating.

## A clean checklist for a fresh deploy

If you'd like a tickable list to print or paste into a notebook:

```
[ ] Open Hostinger File Manager → public_html/
[ ] Delete any non-Bible-Sprint placeholder files
[ ] Create blog/ folder
[ ] Upload 3 files into blog/
[ ] Return to public_html/
[ ] Upload 11 top-level files
[ ] Confirm google25a592169f9aa8b7.html is present
[ ] Visit www.biblesprint.com — home page loads
[ ] Visit /about.html — loads
[ ] Visit /contact.html — loads, form looks right
[ ] Visit /blog/ — listing loads, launch post linked
[ ] Visit /app.html — Bible Sprint app loads
[ ] Send yourself a test contact-form submission — arrives at info@tlcblend.com
[ ] Hard-refresh (Cmd+Shift+R) and re-check the home page
```

That's a complete deploy. Save this file somewhere you'll find it again — it's the only document you need for the day-to-day operational side of running biblesprint.com.
