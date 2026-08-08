# Bible Sprint — adding a new blog post

The Bible Sprint blog lives at `https://www.biblesprint.com/blog/` and is built from plain HTML files. There's no CMS to log into and no build step. Each new post is one HTML file, copied from a template, edited, and uploaded.

If you can edit a Word document and use Hostinger's File Manager, you can publish a post in about 15 minutes.

## Files in `/blog/`

```
/blog/
├── index.html            ← the listing page (cards for each post)
├── _template.html        ← copy this file to start a new post (never publish this one as-is)
└── why-i-read-the-whole-bible-in-43-days.html   ← the launch post
```

The leading underscore on `_template.html` is just a convention — it reminds you this file shouldn't be linked or shared. The template also has a `<meta name="robots" content="noindex">` tag so Google ignores it even if someone stumbles on the URL.

## Adding a new post — the 8 steps

### 1. Decide the slug

The slug is the URL ending. Use lowercase letters, dashes, no spaces, no special characters. Keep it under 60 characters and full of meaningful words (Google reads slugs).

Good slug: `reading-leviticus-without-stalling.html`
Less good: `post3.html`, `2026-06-12.html`

### 2. Copy the template

In Hostinger File Manager, navigate to `public_html/blog/`. Copy `_template.html` and rename the copy to your slug (e.g. `reading-leviticus-without-stalling.html`).

### 3. Open the new file in the editor

Hostinger lets you edit HTML directly. Click your new file → Edit.

### 4. Replace every placeholder

Search the file (Ctrl/Cmd-F) for each placeholder and replace it. The placeholders are:

| Placeholder | What to put | Example |
|---|---|---|
| `POST TITLE` | Your post title (appears in browser tab, search results, social previews) | `Reading Leviticus without stalling` |
| `POST-SLUG` | The filename without `.html` | `reading-leviticus-without-stalling` |
| `YYYY-MM-DD` | The publish date in ISO format | `2026-06-12` |
| `HUMAN-DATE — e.g. 12 June 2026` | The same date in human form (just type your date here, replacing the whole hint) | `12 June 2026` |
| `~N min read` | A rough reading-time estimate (count your words ÷ 200) | `~4 min read` |
| `ONE-SENTENCE DESCRIPTION` | A short summary used by Google + social previews. Aim for ~150 characters. | `If you give up on Leviticus by chapter 7, here's a different way to read it — and why it's actually full of grace.` |

### 5. Replace the body

Inside `<article>`, replace the placeholder paragraphs and headings with your post.

The structure that works well:

- A `<p class="lede">` with one or two opening sentences (this is what shows in previews)
- Body paragraphs in `<p>` tags
- Section headings as `<h2>` (don't use `<h1>` — that's reserved for the post title)
- Numbered lists with `<ol><li>...</li></ol>`
- Bulleted lists with `<ul><li>...</li></ul>`
- Bold for emphasis: `<strong>like this</strong>`
- Inline links: `<a href="https://example.com">link text</a>` (or relative paths like `<a href="/about.html">About</a>`)

Don't worry about styling — the page CSS handles that automatically.

### 6. Delete the yellow "Template file" banner

At the top of the article there's a yellow note that says "Template file…". Delete that whole `<div>` block before publishing — search for `Template file.` to find it.

### 7. Add the post to the listing page

Open `/blog/index.html` and find the comment that says:

```html
<!-- Add new <article class="post-card"> blocks above this comment, newest first. -->
```

Above that comment, paste this block (substitute your values):

```html
<article class="post-card">
  <div class="post-meta">
    <span class="post-tag">YOUR TAG</span>
    <span>HUMAN-DATE</span>
    <span class="dot" aria-hidden="true"></span>
    <span>~N min read</span>
  </div>
  <h2 class="post-title">
    <a class="post-title-link" href="/blog/POST-SLUG.html">POST TITLE</a>
  </h2>
  <p class="post-excerpt">A short excerpt — one or two sentences. This is what readers see on the listing page before they click in.</p>
  <a class="post-read" href="/blog/POST-SLUG.html">Read the post →</a>
</article>
```

A "tag" is a one-word category that shows in the small purple chip — for example `Founder`, `Testimony`, `How-to`, `Reflection`, `News`. Pick whatever fits.

### 8. Update sitemap.xml

Open `/sitemap.xml` (in `public_html/`, not in `blog/`). Add a new `<url>` entry for your post just before the `</urlset>` closing tag:

```xml
<url>
  <loc>https://www.biblesprint.com/blog/POST-SLUG.html</loc>
  <lastmod>YYYY-MM-DD</lastmod>
  <changefreq>yearly</changefreq>
  <priority>0.6</priority>
</url>
```

This tells Google there's a new page to crawl. If you forget this step the post still works — Google will find it eventually via the link from the blog index — but adding the entry gets it indexed faster.

## Save, upload, refresh, share

Save the file in Hostinger and the post is live immediately at `https://www.biblesprint.com/blog/POST-SLUG.html`. Pop into a browser to check it. Hard-refresh (Cmd+Shift+R) if anything looks stale.

When you share the post on WhatsApp, X, or LinkedIn, the link preview will show your title, the one-sentence description, and the Bible Sprint social image automatically — that's the Open Graph metadata in the template doing its job.

## What appears under the hood

Each post automatically gets:

- A **canonical URL** so Google doesn't index duplicates
- **Open Graph + Twitter Card** metadata for tidy social previews
- **Schema.org BlogPosting** structured data — Google can use this to show the post date, author, and cover image directly in search results
- A **back-to-blog** link
- An **author byline** at the bottom (Timi Hyacinth + the avatar)
- A **CTA strip** inviting readers to start reading the Bible
- A **Sign in / Open app** button in the top nav so readers can convert into users

Nothing of that is in the template you have to fill in — it just works.

## Post ideas (for later)

A list of post ideas worth considering for the first six months:

- *Reading Leviticus without stalling* — a how-to for the book most people quit on
- *What I noticed when I read the gospels four times in two months* — patterns
- *Sprint Circles: how reading together changed my Sundays* — community
- *Why we don't show ads when you listen* — values / Bible.is choice
- *The 43-day plan vs. the 365-day plan: which is for you?* — practical guidance
- *A testimony from {a real reader, with permission}* — social proof

You don't need to write all of these — you don't need to write any of them. But every post you do publish becomes another page Google indexes, another link people might land on from search, and another seed for someone who'd never download a Bible app to discover Bible Sprint.

## Common mistakes

- **Forgetting to delete the yellow Template banner.** It'll show up live with a warning that says "Template file." Embarrassing but easy to fix.
- **Using non-ASCII apostrophes in the JSON-LD block.** The structured-data JSON at the top of the file is fussy about quotes. If you copy-paste from Word, sometimes Word "smart quotes" sneak in. Stick to plain `'` and `"` inside the `<script type="application/ld+json">` block. If a post mysteriously fails Google's Rich Results test, this is usually the cause.
- **Forgetting to update `index.html`.** The post page is live but unlinked, so nobody will find it via the blog. Always update the listing.
- **Publishing the slug with spaces.** `My Post.html` works in Hostinger but breaks all link sharing. Always use dashes: `my-post.html`.
