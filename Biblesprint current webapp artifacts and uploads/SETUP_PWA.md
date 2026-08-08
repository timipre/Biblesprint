# Bible Sprint — PWA setup (Path A)

Bible Sprint is now a polished Progressive Web App. Users can install it from biblesprint.com — no App Store, no Play Store, no fees, no review queue. The app:

- Adds itself to a phone's home screen with a real icon
- Launches full-screen with no browser bar
- Works offline once the user has loaded it once
- Keeps progress, journal and history saved on-device
- Has its own splash screen (Android) and theme colour

## Files added in this round

```
public_html/
├── manifest.json              ← web app manifest
├── sw.js                      ← service worker (caches pages + images)
├── offline.html               ← shown when truly offline AND nothing is cached yet
├── icon-180.png               ← Apple touch icon (iOS home screen)
├── icon-192.png               ← Android home screen icon
├── icon-512.png               ← Splash screen + share-sheet icon
└── icon-maskable-512.png      ← Android adaptive icon (with safe-area padding)
```

Plus modifications to `app.html` and `home.html` (manifest link, service-worker registration, install-prompt logic).

## Deploying

Upload all seven new files plus the updated `app.html`, `home.html` and `index.html` to Hostinger `public_html/`. The service worker takes effect on each visitor's *second* visit by design (first visit registers it; second visit is when the cache kicks in).

After uploading:

1. Hard-refresh the live site (Cmd+Shift+R / Ctrl+Shift+R).
2. Open the browser dev tools → Application → Service Workers — you should see `sw.js` registered and "activated".
3. In the same panel, Application → Manifest — you should see "Bible Sprint", icons listed, no errors.

## What users will experience

### On Android (Chrome / Edge / Brave)

A small banner slides up from the bottom of the app after about 30 seconds of use, with an *Install* button. Tapping it opens the native install dialog. Once installed, Bible Sprint sits on the home screen with the orange-and-purple "BS" icon, opens full-screen with a brand-coloured splash, and looks indistinguishable from a Play Store app.

### On iOS (Safari)

A different banner slides up after 30 seconds, hinting at the share menu — *"Tap the Share icon, then Add to Home Screen."* iOS doesn't support a one-tap install (Apple restriction). After the user follows those steps, the app launches full-screen from the home screen with the rounded-corner "BS" icon.

### On desktop Chrome / Edge

A small install icon appears in the URL bar (a little screen-with-arrow). The same banner also appears in-app. Bible Sprint can be launched as a standalone window from the OS dock or Start menu.

### Existing users

Anyone who's already saved their plan / journal sees no change — the service worker simply makes their existing app load faster and work offline. No data is touched. Their plan, journal, history and preferences stay intact.

## How offline works

The service worker uses three strategies depending on what's being requested:

- **HTML pages** — *Network-first.* Every visit the browser tries to fetch the latest HTML from your server. If that fails (offline), it serves the cached version. If even the cache is empty, it serves `offline.html`.
- **Images and the manifest** — *Cache-first.* Once cached, served instantly from cache. The service worker only goes to the network if there's no cached copy.
- **Firebase, FCBH, Bible.is, Formspree** — *Pass-through.* These are cross-origin requests; the service worker doesn't touch them. Firestore handles its own offline queue.

This means a user who has opened Bible Sprint at least once on a device can later open it on a plane / Tube / camping trip with no signal, and it'll work — they can read, tick sprints, journal, look at history. When they come back online, anything they ticked will sync to Firestore automatically.

## When to bump the cache version

If you ever ship a meaningful change and want to *force* every device to refresh its cache (rather than wait for browsers to notice the new SW), edit `sw.js` and bump:

```js
const CACHE_VERSION = 'biblesprint-v1';
```

to `'biblesprint-v2'` (or higher). On their next visit, the new SW activates, deletes the old cache, and pulls fresh files. Routine updates don't usually need this — browsers check for SW updates on every navigation.

## Troubleshooting

**"My update isn't showing up after I refreshed."** Service workers can serve stale HTML if the network call fails silently. Hard refresh always fixes it. For ongoing development, in Chrome DevTools → Application → Service Workers → tick "Update on reload".

**"The install banner won't go away."** It's stored in localStorage. Clear it with `localStorage.removeItem('biblesprint:installBannerDismissed')` in the console, or just wait 14 days — that's how long the dismissal lasts before it reappears.

**"Some pages don't work offline."** Only pages cached in `CORE_ASSETS` inside `sw.js` are guaranteed offline. If you add a new page (e.g. a new blog post), add its URL to that list and bump `CACHE_VERSION` — otherwise it'll still load online but show `offline.html` when offline.

**"Can users install on a desktop browser?"** Yes — Chrome and Edge on Mac/Windows/Linux all support PWA install. The install icon appears in the URL bar.

**"What about Apple's Web Push?"** As of iOS 16.4, push notifications work for installed PWAs — but only if the user installs via "Add to Home Screen" first. We don't currently use push; if you want to add it later, the SW already has the foundation.

## What this is not

A native app. There are still things only true native apps can do — push notifications on iOS Safari (without home-screen install), background sync, deep system integration. If/when those become important, we can move to **Path B** (PWABuilder wrap for App Store / Play Store) or **Path C** (Capacitor rewrite). The PWA work done here translates directly to those paths — no wasted effort.
