<?php
/**
 * BibleSprint — daily reminder email sender
 * Runs on Hostinger cron every 15 minutes.
 * See SETUP_REMINDERS.md for installation.
 */

// ============================================================
// CONFIG — edit these after uploading
// ============================================================
$SERVICE_ACCOUNT_PATH = dirname(__DIR__) . '/private/firebase-service-account.json';
$PROJECT_ID           = 'bible-sprint';
$FROM_EMAIL           = 'reminders@biblesprint.com';
$FROM_NAME            = 'BibleSprint';

// Optional SMTP (if you prefer SMTP over PHP mail()). Leave blank to use mail().
$SMTP_HOST = 'smtp.hostinger.com';
$SMTP_PORT = 465;
$SMTP_USER = 'reminders@biblesprint.com';
$SMTP_PASS = '';  // leave blank to use mail() instead of SMTP

// ============================================================
// Bible data (must match the app)
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
// Bible helpers
// ============================================================
function idxToRef($idx) {
    global $BIBLE_BOOKS;
    $cur = 0;
    foreach ($BIBLE_BOOKS as [$book, $n]) {
        if ($idx < $cur + $n) return ['book' => $book, 'chapter' => $idx - $cur + 1];
        $cur += $n;
    }
    return null;
}
function bookChapterCount($book) {
    global $BIBLE_BOOKS;
    foreach ($BIBLE_BOOKS as [$b, $n]) if ($b === $book) return $n;
    return 0;
}
function formatSprint($startIdx, $endIdx) {
    $groups = [];
    for ($i = $startIdx; $i <= $endIdx; $i++) {
        $ref = idxToRef($i);
        $count = count($groups);
        if ($count === 0 || $groups[$count - 1]['book'] !== $ref['book']) {
            $groups[] = ['book' => $ref['book'], 'start' => $ref['chapter'], 'end' => $ref['chapter']];
        } else {
            $groups[$count - 1]['end'] = $ref['chapter'];
        }
    }
    $parts = [];
    foreach ($groups as $g) {
        if (bookChapterCount($g['book']) === 1) { $parts[] = $g['book']; continue; }
        if ($g['start'] === $g['end']) $parts[] = $g['book'] . ' ' . $g['start'];
        else $parts[] = $g['book'] . ' ' . $g['start'] . '–' . $g['end'];
    }
    return implode(', ', $parts);
}
function buildPlan($sprintsPerDay) {
    $sprints = [];
    for ($i = 0; $i < TOTAL_CHAPTERS; $i += SPRINT_SIZE) {
        $end = min($i + SPRINT_SIZE - 1, TOTAL_CHAPTERS - 1);
        $sprints[] = [
            'startIdx' => $i, 'endIdx' => $end,
            'chapters' => $end - $i + 1,
            'text' => formatSprint($i, $end),
        ];
    }
    $days = [];
    for ($i = 0; $i < count($sprints); $i += $sprintsPerDay) {
        $days[] = array_slice($sprints, $i, $sprintsPerDay);
    }
    return $days;
}
function bibleGatewayUrl($ref, $version) {
    $clean = str_replace(['–','—'], '-', $ref);
    return 'https://www.biblegateway.com/passage/?search=' . urlencode($clean)
         . '&version=' . urlencode($version ?: 'NIV');
}

// ============================================================
// Firestore REST helpers
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
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
        ],
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
    if (isset($v['arrayValue'])) {
        return array_map('parseValue', $v['arrayValue']['values'] ?? []);
    }
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
// Email sender
// ============================================================
function sendMailSMTP($to, $subject, $html, $text) {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $FROM_EMAIL, $FROM_NAME;
    $boundary = 'BS' . bin2hex(random_bytes(8));
    $eol = "\r\n";
    $fp = fsockopen("ssl://$SMTP_HOST", $SMTP_PORT, $errno, $errstr, 20);
    if (!$fp) throw new Exception("SMTP connect: $errstr");
    $read = fn() => fgets($fp, 1024);
    $send = function($cmd) use ($fp, &$read, $eol) {
        fwrite($fp, $cmd . $eol);
        $line = $read();
        while ($line && strlen($line) >= 4 && $line[3] === '-') $line = $read();
        return $line;
    };
    $read();
    $send("EHLO biblesprint.com");
    $send("AUTH LOGIN");
    $send(base64_encode($SMTP_USER));
    $send(base64_encode($SMTP_PASS));
    $send("MAIL FROM:<$FROM_EMAIL>");
    $send("RCPT TO:<$to>");
    $send("DATA");
    $headers  = "From: $FROM_NAME <$FROM_EMAIL>$eol";
    $headers .= "To: <$to>$eol";
    $headers .= "Subject: $subject$eol";
    $headers .= "MIME-Version: 1.0$eol";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"$eol";
    $headers .= "$eol";
    $body  = "--$boundary$eol";
    $body .= "Content-Type: text/plain; charset=UTF-8$eol$eol";
    $body .= "$text$eol";
    $body .= "--$boundary$eol";
    $body .= "Content-Type: text/html; charset=UTF-8$eol$eol";
    $body .= "$html$eol";
    $body .= "--$boundary--$eol";
    fwrite($fp, $headers . $body . "$eol.$eol");
    $read();
    $send("QUIT");
    fclose($fp);
}

function sendMailNative($to, $subject, $html, $text) {
    global $FROM_EMAIL, $FROM_NAME;
    $boundary = 'BS' . bin2hex(random_bytes(8));
    $headers = [
        "From: $FROM_NAME <$FROM_EMAIL>",
        "Reply-To: $FROM_EMAIL",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
    ];
    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= "$text\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= "$html\r\n";
    $body .= "--$boundary--\r\n";
    mail($to, $subject, $body, implode("\r\n", $headers));
}

function sendMail($to, $subject, $html, $text) {
    global $SMTP_PASS;
    if (!empty($SMTP_PASS)) sendMailSMTP($to, $subject, $html, $text);
    else sendMailNative($to, $subject, $html, $text);
}

// ============================================================
// Email template
// ============================================================
function buildEmailHtml($user, $day, $sprints) {
    $name = explode(' ', $user['name'] ?? 'there')[0];
    $totalCh = array_sum(array_map(fn($s) => $s['chapters'], $sprints));
    $ver = $user['bibleVersion'] ?? 'NIV';
    $rows = '';
    foreach ($sprints as $i => $s) {
        $url = bibleGatewayUrl($s['text'], $ver);
        $n = $i + 1;
        $rows .= '<tr><td style="padding:12px 0; border-bottom:1px solid #E3E6EB;">'
              . '<div style="font-size:12px; color:#667080; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">Sprint ' . $n . ' &middot; ' . $s['chapters'] . ' ch</div>'
              . '<a href="' . htmlspecialchars($url) . '" style="color:#2E75B6; font-size:16px; text-decoration:none;">' . htmlspecialchars($s['text']) . '</a>'
              . '</td></tr>';
    }
    $sprintCount = count($sprints);
    $sprintsLabel = $sprintCount . ' ' . ($sprintCount === 1 ? 'sprint' : 'sprints');
    return <<<HTML
<!doctype html><html><body style="margin:0; padding:0; background:#F7F8FA; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif; color:#1F2328;">
<div style="max-width:600px; margin:0 auto; padding:20px;">
  <div style="background:#000000; color:#A0A0A0; padding:22px; text-align:center; border-radius:14px 14px 0 0;">
    <div style="font-size:18px; letter-spacing:3px;">BIBLE</div>
    <div style="font-size:18px; letter-spacing:3px;">SPRINT</div>
  </div>
  <div style="background:white; padding:28px 24px; border-radius:0 0 14px 14px; border:1px solid #E3E6EB; border-top:none;">
    <h1 style="color:#2E75B6; font-size:28px; margin:0 0 6px; letter-spacing:-0.02em;">Day $day</h1>
    <p style="color:#667080; margin:0 0 18px; font-size:15px;">Morning, $name. Today&rsquo;s reading awaits.</p>
    <p style="font-size:14px; color:#667080; margin:0 0 6px;"><strong style="color:#1F2328;">{$totalCh} chapters</strong> in {$sprintsLabel}</p>
    <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:8px;">$rows</table>
    <div style="margin-top:26px; text-align:center;">
      <a href="https://www.biblesprint.com" style="background:#2E75B6; color:white; padding:14px 32px; border-radius:10px; text-decoration:none; font-weight:600; display:inline-block; font-size:15px;">Open BibleSprint</a>
    </div>
  </div>
  <div style="text-align:center; color:#8E96A4; font-size:12px; padding:20px; line-height:1.6;">
    You&rsquo;re receiving this because you set a daily reminder in BibleSprint.<br>
    Turn off in app: Settings &rarr; Daily reminder &rarr; uncheck &ldquo;Email me&rdquo;<br>
    <a href="https://www.biblesprint.com" style="color:#2E75B6;">biblesprint.com</a> &middot;
    <a href="https://www.biblesprint.com/privacy.html" style="color:#2E75B6;">Privacy</a><br><br>
    &copy; 2026 TLC Consortium Limited. BibleSprint&trade; is a trademark of TLC Consortium Limited.
  </div>
</div></body></html>
HTML;
}

function buildEmailText($user, $day, $sprints) {
    $name = explode(' ', $user['name'] ?? 'there')[0];
    $totalCh = array_sum(array_map(fn($s) => $s['chapters'], $sprints));
    $sprintsLabel = count($sprints) === 1 ? 'sprint' : 'sprints';
    $lines = [
        "Morning, $name.",
        "",
        "Day $day - today's BibleSprint reading.",
        "$totalCh chapters in " . count($sprints) . " $sprintsLabel:",
        "",
    ];
    foreach ($sprints as $i => $s) {
        $n = $i + 1;
        $lines[] = "  Sprint $n ({$s['chapters']} ch): {$s['text']}";
    }
    $lines[] = "";
    $lines[] = "Open BibleSprint: https://www.biblesprint.com";
    $lines[] = "";
    $lines[] = "-- BibleSprint";
    $lines[] = "Turn off in app: Settings > Daily reminder";
    return implode("\n", $lines);
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

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $sent = 0; $skipped = 0; $errors = 0;

    foreach ($users as $doc) {
        $data = parseDoc($doc);
        $uid  = $data['_uid'];
        try {
            if (empty($data['emailRemindersEnabled'])) { $skipped++; continue; }
            if (empty($data['email'])) { $skipped++; continue; }
            if (empty($data['currentPlan']['reminderTime'])) { $skipped++; continue; }
            if (empty($data['currentPlan']['startDate'])) { $skipped++; continue; }
            if (empty($data['currentPlan']['pace'])) { $skipped++; continue; }

            $tz = $data['timezone'] ?? 'Europe/London';
            try { $userTz = new DateTimeZone($tz); }
            catch (Exception $e) { $userTz = new DateTimeZone('Europe/London'); }
            $userNow = (clone $now)->setTimezone($userTz);

            $h = (int)$userNow->format('H');
            $m = (int)$userNow->format('i');
            [$rh, $rm] = array_map('intval', explode(':', $data['currentPlan']['reminderTime']));

            $nowMinutes = $h * 60 + $m;
            $targetMinutes = $rh * 60 + $rm;
            $diff = $nowMinutes - $targetMinutes;
            if ($diff < 0 || $diff >= 15) { $skipped++; continue; }

            $todayISO = $userNow->format('Y-m-d');
            if (($data['lastReminderSentDate'] ?? '') === $todayISO) { $skipped++; continue; }

            // Compute day number
            $start = new DateTime($data['currentPlan']['startDate'] . 'T00:00:00', $userTz);
            $today = new DateTime($todayISO . 'T00:00:00', $userTz);
            $days = buildPlan((int)$data['currentPlan']['pace']);
            $total = count($days);
            $dayNum = (int)$start->diff($today)->format('%r%a') + 1;
            $dayNum = max(1, min($total, $dayNum));

            $sprints = $days[$dayNum - 1];

            // Check if all sprints done today
            $completion = $data['currentPlan']['completion'] ?? [];
            $todaysDone = $completion[(string)$dayNum] ?? [];
            $allDone = count($todaysDone) === count($sprints)
                       && array_reduce($todaysDone, fn($c, $v) => $c && $v, true);
            if ($allDone) {
                firestoreUpdateField($PROJECT_ID, $token, $uid, 'lastReminderSentDate', $todayISO);
                $skipped++; continue;
            }

            $subject = "Day $dayNum — today’s BibleSprint reading";
            $html = buildEmailHtml($data, $dayNum, $sprints);
            $text = buildEmailText($data, $dayNum, $sprints);

            sendMail($data['email'], $subject, $html, $text);
            firestoreUpdateField($PROJECT_ID, $token, $uid, 'lastReminderSentDate', $todayISO);
            $sent++;
            log_line("Sent to {$data['email']} (day $dayNum, tz $tz)");

        } catch (Exception $e) {
            $errors++;
            log_line("ERROR for $uid: " . $e->getMessage());
        }
    }

    log_line("Done. sent=$sent skipped=$skipped errors=$errors");
} catch (Exception $e) {
    log_line('FATAL: ' . $e->getMessage());
    exit(1);
}
