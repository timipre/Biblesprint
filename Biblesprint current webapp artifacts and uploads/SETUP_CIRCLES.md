# Sprint Circles — Firebase rules to deploy

> ⚠️ **Use `firestore.rules` (in this same folder) as the authoritative ruleset to paste into Firebase.**
> It is kept up to date and now also includes the **admin messages / announcements** permissions.
> The rules block further down is older (no messaging) and is kept only for reference.


Bible Sprint now supports **Sprint Circles**, small accountability groups (up to 12 people) where members can see each other's day count, current streak, and last-active date. Journals stay private.

For circles to work, the Firestore security rules need to be updated. This is a one-time job — copy the rules below into Firebase Console once and circles will work for everyone.

## How circles are stored

Circles add three new collections in Firestore:

- `circles/{circleId}` — circle metadata: name, inviteCode, createdBy, memberUids[]
- `circles/{circleId}/members/{uid}` — per-member status: displayName, currentStreak, daysFullyDone, lastActive (no journal content)
- `inviteCodes/{code}` — a small lookup doc that maps a 6-character code to a circleId (so people joining can find the circle without being able to read every other circle in the database)

## Updating the rules

1. Go to <https://console.firebase.google.com>, select your **bible-sprint** project.
2. In the left sidebar, click **Firestore Database** → **Rules** tab.
3. Replace the entire file with the rules below. (You'll see your existing `users/{uid}` rule — keep it; the version below already includes it.)
4. Click **Publish**.

```firestore
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {

    // -----------------------------------------------------------
    // Each user owns their own profile/plan/journal document.
    // -----------------------------------------------------------
    match /users/{uid} {
      allow read, write: if request.auth != null && request.auth.uid == uid;
    }

    // -----------------------------------------------------------
    // Sprint Circles
    // -----------------------------------------------------------
    match /circles/{circleId} {
      // Members can read the full circle metadata (name, code, member list).
      allow read: if request.auth != null
                  && request.auth.uid in resource.data.memberUids;

      // Anyone signed in can create a circle, provided they put themselves
      // in as the only member and as the creator.
      allow create: if request.auth != null
                    && request.resource.data.createdBy == request.auth.uid
                    && request.resource.data.memberUids.size() == 1
                    && request.resource.data.memberUids[0] == request.auth.uid;

      // Updates: members can edit metadata; non-members can only join (i.e.
      // append themselves to memberUids without changing anything else).
      allow update: if request.auth != null && (
        // Existing member edits
        (request.auth.uid in resource.data.memberUids)
        ||
        // Joiner: must be adding only themselves, growing memberUids by 1, and the rest of the doc is unchanged
        (
          request.auth.uid in request.resource.data.memberUids
          && !(request.auth.uid in resource.data.memberUids)
          && request.resource.data.memberUids.size() == resource.data.memberUids.size() + 1
          && request.resource.data.name == resource.data.name
          && request.resource.data.inviteCode == resource.data.inviteCode
          && request.resource.data.createdBy == resource.data.createdBy
        )
      );

      // Only the admin (creator) can delete the whole circle.
      allow delete: if request.auth != null
                    && request.auth.uid == resource.data.createdBy;

      // -------- Per-member status sub-documents --------
      match /members/{memberUid} {
        // Any member of the parent circle can read everyone's status.
        allow read: if request.auth != null
                    && request.auth.uid in get(/databases/$(database)/documents/circles/$(circleId)).data.memberUids;

        // You can only write your OWN member doc — and only while you are a member.
        allow write: if request.auth != null
                     && request.auth.uid == memberUid
                     && memberUid in get(/databases/$(database)/documents/circles/$(circleId)).data.memberUids;

        // The admin can also delete a member doc when removing them.
        allow delete: if request.auth != null
                      && request.auth.uid == get(/databases/$(database)/documents/circles/$(circleId)).data.createdBy;
      }
    }

    // -----------------------------------------------------------
    // Invite codes — a small public lookup so a joiner with the
    // 6-character code can find the circleId.
    // -----------------------------------------------------------
    match /inviteCodes/{code} {
      // Anyone signed in can read a code (otherwise nobody could join).
      allow read: if request.auth != null;
      // Only signed-in users can create codes (the app does this when you create a circle).
      allow create: if request.auth != null;
      // Updates aren't permitted — a code is set once at creation.
      allow update: if false;
      // The creator deletes their code when they delete the circle.
      allow delete: if request.auth != null;
    }
  }
}
```

## Sanity check after publishing

1. Sign in to the app on your phone.
2. Settings → Sprint Circles → **Create circle** → name it "Test".
3. You should see the 6-character code, e.g. `BREAD7`.
4. Sign in on a second device (or different account) → Settings → Sprint Circles → **Join with code** → paste the code.
5. Both devices should now see each other's name in the members list within ~1 second.

If you see a **Permission denied** toast, double-check that the rules above are published exactly as written (the `get()` calls inside the `members` subcollection are easy to mistype).

## Privacy

Circles only sync the fields you can see in the members list — `displayName`, `pace`, `currentDay`, `daysFullyDone`, `currentStreak`, `longestStreak`, `lifetimeCompletions`, `lastActive`. The journal content (`verse`, `observations`, `application`, `prayer`) is **never** written to the circle subcollection. It stays in `users/{uid}`, which only the user themselves can read.

Members can leave a circle at any time. The admin can remove other members or delete the whole circle. When a circle is deleted, all member status docs and the invite code are removed too.



