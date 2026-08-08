# Bible Sprint — SEO setup guide

Once the updated files are uploaded to Hostinger, this gets biblesprint.com discoverable on Google, Bing, and pretty when shared on WhatsApp, X, LinkedIn, and iMessage.

## What's already done in the code

- **Page metadata** — title, description, canonical URL, keywords, language, author.
- **Open Graph tags** — what shows when the link is pasted into WhatsApp, Messenger, LinkedIn, iMessage, etc.
- **Twitter Card tags** — proper large-image preview on X/Twitter.
- **Structured data (Schema.org JSON-LD)** — tells Google this is an Organization (TLC Consortium Ltd) operating a SoftwareApplication (Bible Sprint) with a free offer. Can result in rich search-result cards.
- **Social preview image** — the `og-image.png` in your upload bundle.
- **robots.txt** — tells crawlers to index the public pages and skip the app.
- **sitemap.xml** — explicit list of your public pages.

## What you need to do

### Step 1 — Upload the new files

To Hostinger `public_html/`:
- `index.html` (landing page with full SEO metadata)
- `app.html` (unchanged except for earlier features)
- `privacy.html`
- `robots.txt` (new)
- `sitemap.xml` (new)
- `og-image.png` (new — the social preview)

Verify the social image is reachable: visit <https://www.biblesprint.com/og-image.png> directly in a browser; you should see the BibleSprint banner.

Verify robots and sitemap:
- <https://www.biblesprint.com/robots.txt>
- <https://www.biblesprint.com/sitemap.xml>

### Step 2 — Register with Google Search Console

This is the single most important step for search visibility.

1. Go to <https://search.google.com/search-console/welcome>.
2. Click **Add Property → URL prefix** and enter `https://www.biblesprint.com`.
3. Google asks you to verify ownership. The easiest method is **HTML tag**: Google gives you a `<meta name="google-site-verification" content="XXXXX">` tag. Copy it, paste it into `index.html` just below the other `<meta>` tags in `<head>`, and re-upload `index.html`. Click **Verify** in Search Console.
4. Once verified, go to **Sitemaps** in the left sidebar, type `sitemap.xml`, click **Submit**.
5. Google now starts crawling. Initial indexing takes a few days; you can see progress in the **Pages** report over time.

### Step 3 — Register with Bing Webmaster Tools (optional but free)

1. Go to <https://www.bing.com/webmasters>.
2. Sign in with the same Google account.
3. Click **Import from Google Search Console** — it pulls your site over in one click with the sitemap already submitted.
4. Done. Bing now indexes you too. Covers Bing, Yahoo, DuckDuckGo, Ecosia.

### Step 4 — Set up analytics (pick one)

**Option A — Plausible** (recommended). Privacy-first, no cookies, GDPR-friendly by default, £7/month after a free trial. <https://plausible.io>. Sign up, add `biblesprint.com`, they give you one `<script>` tag to paste into the `<head>` of `index.html` and `app.html`. That's it.

**Option B — Google Analytics 4** (free but heavier). <https://analytics.google.com>. More features, needs a cookie consent banner for EU/UK users, more to configure.

**Option C — Skip for now**. Your Firebase auth dashboard already shows sign-up numbers. You can add analytics later.

### Step 5 — Validate the social preview works

Test how your link looks when shared:

- **Facebook/WhatsApp/Messenger**: <https://developers.facebook.com/tools/debug/> — paste `https://www.biblesprint.com`, click Debug. The first time, click **Scrape Again** to force Facebook to re-fetch your updated Open Graph tags.
- **X/Twitter**: <https://cards-dev.twitter.com/validator> — paste the URL.
- **LinkedIn**: <https://www.linkedin.com/post-inspector/> — paste and inspect.
- **iMessage**: just paste `https://www.biblesprint.com` into a message to yourself; the preview card should show the banner image.

## Ongoing — content and backlinks

Search ranking improves with time, content, and backlinks. Things that help:

- **Sermon notes or blog posts** on a `/blog/` subfolder — each one becomes a new indexable page that people might search for.
- **Mentions from credible sites** — church websites, devotional newsletters, Christian publications linking to biblesprint.com.
- **Social sharing** — each share of the URL (with the lovely new preview image) can drive traffic and eventually backlinks.
- **Consistent posting to the WhatsApp group** — not SEO directly, but keeps the community active, which fuels word-of-mouth and shares.

## Monitoring

Once indexed (2–4 weeks), Search Console will show you:

- Which search queries brought people to your site (e.g. "read the Bible in 43 days", "daily Bible reading plan").
- Which pages are indexed.
- Any errors (broken pages, mobile issues, etc.).

Check it monthly. Over 3–6 months you'll see what keywords you rank for and can write more content around them.
