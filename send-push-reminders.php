<?php
/**
 * BibleSprint — push notification reminder sender
 * Runs on Hostinger cron every 15 minutes, alongside (but independent of)
 * cron-reminders.php's email path.
 *
 * Unlike cron-reminders.php, this computes "today's reading" the same way
 * app.html does post progress-locked rearchitecture: the oldest day that
 * isn't fully ticked yet, not a calendar-elapsed-days calculation. If you
 * ever bring cron-reminders.php's email path back to life, port this same
 * day-number logic into it — see the note left in that file.
 *
 * See SETUP_PUSH.md for installation.
 */

require __DIR__ . '/../private/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// ============================================================
// CONFIG — edit these after uploading
// ============================================================
$SERVICE_ACCOUNT_PATH = dirname(__DIR__) . '/private/firebase-service-account.json';
$PROJECT_ID           = 'bible-sprint';

// VAPID keypair — the public half is also embedded in app.html (VAPID_PUBLIC_KEY).
// If you ever regenerate these, both places must be updated together, or every
// existing subscription breaks and everyone has to re-enable notifications.
$VAPID_SUBJECT     = 'mailto:reminders@biblesprint.com';
$VAPID_PUBLIC_KEY  = 'BPCK6wol4VWZqrovUGHZFGHaBAXuB2KPbSVqv6h95yxZJnrF5IhyRCGeOln7s3Jt5aAroE6Jp2QuAqfrvnTROtQ';
$VAPID_PRIVATE_KEY = '***REMOVED-ROTATED-VAPID-KEY***';

// ============================================================
// Bible data (must match app.html)
// ============================================================
$BIBLE_BOOKS = [
    ['Matthew',28],['Mark',16],['Luke',24],['John',21],
    ['Acts',28],['Romans',16],['1 Corinthians',16],['2 Corinthians',13],
    ['Galatians',6],['Ephesians',6],['Philippians',4],['Colossians',4],
    ['1 Thessalonians',5],['2 Thessalonians',3],
    ['1 Timothy',6],['2 Timothy',4],['Titus',3],['Philemon',1],
    ['Hebrews',13],['James',5],['1 Peter',5],['2 Peter',3],
    ['1 John',5],['2 John',1],['3 John',1],['Jude',1],['Revelation',22],
    ['Genesis',50],['Exodus',40],['Leviticus',27],['Numbers',36],['Deuteronomy',34],
    ['Joshua',24],['Judges',21],['Ruth',4],['1 Samuel',31],['2 Samuel',24],
    ['1 Kings',22],['2 Kings',25],['1 Chronicles',29],['2 Chronicles',36],
    ['Ezra',10],['Nehemiah',13],['Esther',10],
    ['Job',42],['Psalms',150],['Proverbs',31],['Ecclesiastes',12],['Song of Songs',8],
    ['Isaiah',66],['Jeremiah',52],['Lamentations',5],
    ['Ezekiel',48],['Daniel',12],
    ['Hosea',14],['Joel',3],['Amos',9],['Obadiah',1],['Jonah',4],
    ['Micah',7],['Nahum',3],['Habakkuk',3],['Zephaniah',3],['Haggai',2],
    ['Zechariah',14],['Malachi',4],
];
define('TOTAL_CHAPTERS', 1189);
define('SPRINT_SIZE', 7);

// ============================================================
// Bible helpers (identical to cron-reminders.php — NT-first order only;
// pre-existing limitation, see that file's own note on 'gr' order)
// ============================================================
function buildPlan($sprintsPerDay) {
    $sprints = [];
    for ($i = 0; $i < TOTAL_CHAPTERS; $i += SPRINT_SIZE) {
        $end = min($i + SPRINT_SIZE - 1, TOTAL_CHAPTERS - 1);
        $sprints[] = ['startIdx' => $i, 'endIdx' => $end, 'chapters' => $end - $i + 1];
    }
    $days = [];
    for ($i = 0; $i < count($sprints); $i += $sprintsPerDay) {
        $days[] = array_slice($sprints, $i, $sprintsPerDay);
    }
    return $days;
}

// ============================================================
// Firestore REST helpers (auth + list identical to cron-reminders.php;
// array-field update is new — needed to prune expired subscriptions)
// ============================================================
function getAccessToken($saPath) {
    if (!file_exists($saPath)) throw new Exception("Service account not found: $saPath");
    $sa = json_decode(file_get_contents($saPath), true);
    if (empty($sa['client_email']) || empty($sa['private_key'])) {
        throw new Exception('Invalid service account JSON');
    }
    $now = time();
    $payload = [
        'iss' => $sa['client_email'],
        'sub' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now, 'exp' => $now + 3600,
    ];
    $b64url = fn($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $jwtHeader = $b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtBody = $b64url(json_encode($payload));
    $toSign = "$jwtHeader.$jwtBody";
    $sig = '';
    openssl_sign($toSign, $sig, $sa['private_key'], 'SHA256');
    $jwt = $toSign . '.' . $b64url($sig);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception('OAuth: ' . curl_error($ch));
    $data = json_decode($resp, true);
    if (empty($data['access_token'])) throw new Exception('No access token: ' . $resp);
    return $data['access_token'];
}

function firestoreListUsers($projectId, $token) {
    $users = []; $pageToken = null;
    do {
        $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users?pageSize=300"
             . ($pageToken ? '&pageToken=' . urlencode($pageToken) : '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $data = json_decode($resp, true);
        if (isset($data['error'])) throw new Exception('List users: ' . $resp);
        foreach (($data['documents'] ?? []) as $d) $users[] = $d;
        $pageToken = $data['nextPageToken'] ?? null;
    } while ($pageToken);
    return $users;
}

function firestoreUpdateField($projectId, $token, $uid, $field, $stringValue) {
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid?updateMask.fieldPaths=$field";
    $body = ['fields' => [$field => ['stringValue' => $stringValue]]];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
}

// Records which reminder slot(s) (sprint indices within today) already got a
// push today, so a slot already handled this run doesn't fire again on a
// later cron tick — mirrors app.html's per-slot reminderTimes model.
function firestoreUpdatePushSentState($projectId, $token, $uid, $dateISO, array $slots) {
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid"
         . '?updateMask.fieldPaths=lastPushSentDate&updateMask.fieldPaths=lastPushSentSlots';
    $body = ['fields' => [
        'lastPushSentDate' => ['stringValue' => $dateISO],
        'lastPushSentSlots' => ['arrayValue' => ['values' => array_map(
            fn($i) => ['integerValue' => (string)$i], $slots
        )]],
    ]];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
}

// Encodes the same {endpoint, keys:{p256dh,auth}, addedAt} shape app.html
// writes (see subscribeToPush() in app.html) into Firestore's typed JSON.
function encodeSubscriptionValue($sub) {
    return ['mapValue' => ['fields' => [
        'endpoint' => ['stringValue' => $sub['endpoint'] ?? ''],
        'keys' => ['mapValue' => ['fields' => [
            'p256dh' => ['stringValue' => $sub['keys']['p256dh'] ?? ''],
            'auth' => ['stringValue' => $sub['keys']['auth'] ?? ''],
        ]]],
        'addedAt' => ['stringValue' => $sub['addedAt'] ?? ''],
    ]]];
}

function firestoreReplacePushSubscriptions($projectId, $token, $uid, array $subs) {
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid?updateMask.fieldPaths=pushSubscriptions";
    $body = ['fields' => ['pushSubscriptions' => ['arrayValue' => [
        'values' => array_map('encodeSubscriptionValue', $subs),
    ]]]];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
}

function parseValue($v) {
    if (isset($v['stringValue']))    return $v['stringValue'];
    if (isset($v['integerValue']))   return (int)$v['integerValue'];
    if (isset($v['booleanValue']))   return (bool)$v['booleanValue'];
    if (isset($v['doubleValue']))    return (float)$v['doubleValue'];
    if (array_key_exists('nullValue', $v)) return null;
    if (isset($v['timestampValue'])) return $v['timestampValue'];
    if (isset($v['arrayValue'])) return array_map('parseValue', $v['arrayValue']['values'] ?? []);
    if (isset($v['mapValue'])) {
        $out = [];
        foreach ($v['mapValue']['fields'] ?? [] as $k => $val) $out[$k] = parseValue($val);
        return $out;
    }
    return null;
}

function parseDoc($doc) {
    $out = [];
    foreach (($doc['fields'] ?? []) as $k => $v) $out[$k] = parseValue($v);
    $parts = explode('/', $doc['name']);
    $out['_uid'] = end($parts);
    return $out;
}

// ============================================================
// Day cursor — mirrors app.html's openingDay(): the oldest day that isn't
// fully ticked yet. NOT calendar-elapsed-days. See app.html's own comment
// above openingDay() for the reasoning (falling behind must never skip
// unread days).
// ============================================================
function currentDayCursor(array $days, array $completion) {
    foreach ($days as $i => $sprints) {
        $dayNum = $i + 1;
        $done = $completion[(string)$dayNum] ?? [];
        $expected = count($sprints);
        $fullyDone = $expected > 0 && count($done) >= $expected;
        if ($fullyDone) {
            for ($j = 0; $j < $expected; $j++) {
                if (empty($done[$j])) { $fullyDone = false; break; }
            }
        }
        if (!$fullyDone) return $dayNum;
    }
    return count($days);
}

// ============================================================
// Main
// ============================================================
function log_line($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

try {
    $token = getAccessToken($SERVICE_ACCOUNT_PATH);
    $users = firestoreListUsers($PROJECT_ID, $token);
    log_line('Loaded ' . count($users) . ' users');

    $webPush = new WebPush(['VAPID' => [
        'subject' => $VAPID_SUBJECT,
        'publicKey' => $VAPID_PUBLIC_KEY,
        'privateKey' => $VAPID_PRIVATE_KEY,
    ]]);

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $sent = 0; $skipped = 0; $errors = 0; $pruned = 0;

    foreach ($users as $doc) {
        $data = parseDoc($doc);
        $uid  = $data['_uid'];
        try {
            $subs = $data['pushSubscriptions'] ?? [];
            if (empty($subs)) { $skipped++; continue; }
            // reminderTimes[i] is the reminder time for sprint i within the day
            // (one slot per sprint-per-day — a 4-sprint/day plan gets up to 4).
            $times = $data['currentPlan']['reminderTimes'] ?? [];
            if (empty(array_filter($times))) { $skipped++; continue; }
            if (empty($data['currentPlan']['pace'])) { $skipped++; continue; }

            $tz = $data['timezone'] ?? 'Europe/London';
            try { $userTz = new DateTimeZone($tz); }
            catch (Exception $e) { $userTz = new DateTimeZone('Europe/London'); }
            $userNow = (clone $now)->setTimezone($userTz);

            $h = (int)$userNow->format('H');
            $m = (int)$userNow->format('i');
            $nowMinutes = $h * 60 + $m;

            $todayISO = $userNow->format('Y-m-d');
            $sentToday = (($data['lastPushSentDate'] ?? '') === $todayISO) ? ($data['lastPushSentSlots'] ?? []) : [];

            $days = buildPlan((int)$data['currentPlan']['pace']);
            $completion = $data['currentPlan']['completion'] ?? [];
            $dayNum = currentDayCursor($days, $completion);
            $sprints = $days[$dayNum - 1];

            $done = $completion[(string)$dayNum] ?? [];
            $doneCount = count(array_filter($done));
            $totalCount = count($sprints);
            $name = explode(' ', $data['name'] ?? 'there')[0];

            // A slot is due if it has a time set, hasn't already been sent today,
            // its sprint isn't already read, and its time falls in this run's window.
            $dueSlots = [];
            foreach ($times as $i => $t) {
                if (empty($t)) continue;
                if (in_array($i, $sentToday, true)) continue;
                if (!empty($done[$i])) continue;
                [$rh, $rm] = array_map('intval', explode(':', $t));
                $diff = $nowMinutes - ($rh * 60 + $rm);
                if ($diff < 0 || $diff >= 15) continue;
                $dueSlots[] = $i;
            }
            if (empty($dueSlots)) { $skipped++; continue; }

            // Multiple due slots in the same run still share one notification —
            // no point texting the same "Day N is waiting" message twice back to back.
            $body = "$name, Day $dayNum is waiting. $doneCount/$totalCount sprints done.";
            $payload = json_encode(['title' => 'BibleSprint', 'body' => $body, 'url' => '/app.html']);

            $remaining = [];
            $anySent = false;
            foreach ($subs as $sub) {
                if (empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) continue;
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $sub['endpoint'],
                        'keys' => ['p256dh' => $sub['keys']['p256dh'], 'auth' => $sub['keys']['auth']],
                    ]);
                    $report = $webPush->sendOneNotification($subscription, $payload);
                    $status = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
                    if ($report->isSuccess()) {
                        $anySent = true;
                        $remaining[] = $sub; // keep — still a live subscription
                    } elseif ($status === 404 || $status === 410) {
                        // Expired or unsubscribed on the browser's side — drop it silently.
                        $pruned++;
                    } else {
                        // Transient failure (rate limit, network blip) — keep it, try again next run.
                        $remaining[] = $sub;
                        log_line("Push warning for $uid: " . $report->getReason());
                    }
                } catch (Exception $e) {
                    $remaining[] = $sub; // don't drop a subscription over a one-off exception
                    log_line("Push exception for $uid: " . $e->getMessage());
                }
            }

            if (count($remaining) !== count($subs)) {
                firestoreReplacePushSubscriptions($PROJECT_ID, $token, $uid, $remaining);
            }

            $newSentSlots = array_values(array_unique(array_merge($sentToday, $dueSlots)));
            if ($anySent) {
                firestoreUpdatePushSentState($PROJECT_ID, $token, $uid, $todayISO, $newSentSlots);
                $sent++;
                log_line("Sent to uid $uid (day $dayNum, slots " . implode(',', $dueSlots) . ", tz $tz, " . count($remaining) . " device(s))");
            } elseif (empty($remaining)) {
                // Every subscription this user had turned out to be dead.
                firestoreUpdatePushSentState($PROJECT_ID, $token, $uid, $todayISO, $newSentSlots);
                $skipped++;
            } else {
                $errors++;
            }

        } catch (Exception $e) {
            $errors++;
            log_line("ERROR for $uid: " . $e->getMessage());
        }
    }

    log_line("Done. sent=$sent skipped=$skipped errors=$errors pruned_subscriptions=$pruned");
} catch (Exception $e) {
    log_line('FATAL: ' . $e->getMessage());
    exit(1);
}
