<?php
/**
 * PBX bridge for the CRM's pbxpopup module.
 *
 * Runs on the eCare Asterisk box itself (not on the CRM). The CRM's shared
 * cPanel hosting has no shell/root/VPN access (confirmed: jailshell, no
 * sudo, no /dev/net/tun), so a Tailscale/VPN mesh between CRM and PBX is
 * not possible - this HTTPS + shared-secret bridge is the only viable path.
 *
 * Two actions:
 *   list   - server-to-server call (CRM curls this with the master token).
 *            Returns CDR rows matching a phone number, each carrying an
 *            HMAC-signed relpath the browser can use directly against the
 *            stream action - the master token itself never reaches the
 *            agent's browser/page source.
 *   stream - hit directly by the <audio src> tag in the agent's browser.
 *            Verifies the HMAC signature instead of the master token.
 *            Serves the cached MP3 if the background transcode watcher
 *            already produced one; otherwise transcodes on the fly.
 */

define('PBX_API_TOKEN', getenv('PBX_BRIDGE_TOKEN') ?: '063dcdfddef828a26565ab5fc29f52358083a1d33cfb8d310546b20704cb65e6');
define('RECORDINGS_ROOT', '/var/spool/asterisk/recordings');
define('MP3_CACHE_ROOT', '/var/spool/asterisk/mp3_cache');
define('CDR_CSV', '/var/log/asterisk/cdr-csv/Master.csv');

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    handle_list();
} elseif ($action === 'stream') {
    handle_stream();
} else {
    http_response_code(400);
    echo json_encode(['error' => 'unknown action']);
}
exit;

function require_token(): void
{
    $token = $_GET['token'] ?? '';
    if (!hash_equals(PBX_API_TOKEN, (string) $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'invalid token']);
        exit;
    }
}

function normalize_last10(string $raw): string
{
    $digits = preg_replace('/\D/', '', $raw);
    return substr($digits, -10);
}

/**
 * Locate the actual recording file on disk for a CDR row without needing
 * an agent-name DB lookup. The outbound dialplan branch embeds the dialed
 * number in the filename (HHMMSS_out_to_<number>.gsm); the queue/inbound
 * branch embeds the Asterisk channel uniqueid instead
 * (HHMMSS_<uniqueid>.gsm). Searching by date + either pattern covers both
 * without touching the live dialplan.
 */
function find_recording(string $startDatetime, string $last10, string $uniqueid): ?string
{
    $date = substr($startDatetime, 0, 10);
    $parts = explode('-', $date);
    if (count($parts) !== 3) {
        return null;
    }
    $dayDir = RECORDINGS_ROOT . '/' . implode('/', $parts);
    if (!is_dir($dayDir)) {
        return null;
    }

    $uniqSafe = preg_replace('/[^0-9.]/', '', $uniqueid);
    $cmd = 'find ' . escapeshellarg($dayDir) . ' -type f '
        . '\( -iname ' . escapeshellarg('*' . $last10 . '*') . ' '
        . '-o -iname ' . escapeshellarg('*' . $uniqSafe . '*') . ' \) 2>/dev/null';
    $found = shell_exec($cmd);
    if (!$found) {
        return null;
    }
    $file = trim(explode("\n", trim($found))[0]);
    if ($file === '' || !is_file($file)) {
        return null;
    }
    // Return path relative to RECORDINGS_ROOT.
    return ltrim(substr($file, strlen(RECORDINGS_ROOT)), '/');
}

function sign_relpath(string $relpath): string
{
    return hash_hmac('sha256', $relpath, PBX_API_TOKEN);
}

function handle_list(): void
{
    require_token();
    header('Content-Type: application/json');

    $phone = (string) ($_GET['phone'] ?? '');
    $last10 = normalize_last10($phone);
    if (strlen($last10) < 7) {
        echo json_encode(['recordings' => []]);
        return;
    }

    if (!is_file(CDR_CSV)) {
        echo json_encode(['recordings' => [], 'error' => 'cdr csv not found']);
        return;
    }

    $rows = [];
    $fh = fopen(CDR_CSV, 'r');
    if ($fh) {
        while (($cols = fgetcsv($fh)) !== false) {
            // accountcode,src,dst,dcontext,clid,channel,dstchannel,lastapp,
            // lastdata,start,answer,end,duration,billsec,disposition,
            // amaflags,uniqueid,userfield
            if (count($cols) < 17) {
                continue;
            }
            [$accountcode, $src, $dst, $dcontext, $clid, $channel, $dstchannel,
             $lastapp, $lastdata, $start, $answer, $end, $duration, $billsec,
             $disposition, $amaflags, $uniqueid] = $cols;

            $srcLast10 = normalize_last10($src);
            $dstLast10 = normalize_last10($dst);
            if ($srcLast10 !== $last10 && $dstLast10 !== $last10) {
                continue;
            }

            $direction = ($dstLast10 === $last10) ? 'outbound' : 'inbound';
            $relpath = find_recording($start, $last10, $uniqueid);

            $rows[] = [
                'uniqueid'    => $uniqueid,
                'calldate'    => $start,
                'direction'   => $direction,
                'disposition' => $disposition,
                'duration'    => (int) $billsec,
                'namefile'    => $relpath ? basename($relpath) : null,
                'relpath'     => $relpath,
                'sig'         => $relpath ? sign_relpath($relpath) : null,
            ];
        }
        fclose($fh);
    }

    // Most recent first, cap to 50.
    usort($rows, fn($a, $b) => strcmp($b['calldate'], $a['calldate']));
    $rows = array_slice($rows, 0, 50);

    echo json_encode(['recordings' => $rows]);
}

function handle_stream(): void
{
    $relpath = (string) ($_GET['relpath'] ?? '');
    $sig     = (string) ($_GET['sig'] ?? '');

    // Path traversal guard - only the exact charset our filenames ever use.
    if ($relpath === '' || !preg_match('#^[0-9]{4}/[0-9]{2}/[0-9]{2}/[A-Za-z0-9_.\-]+/[A-Za-z0-9_.\-]+\.gsm$#', $relpath)) {
        http_response_code(400);
        echo 'bad relpath';
        return;
    }
    if (!hash_equals(sign_relpath($relpath), $sig)) {
        http_response_code(403);
        echo 'invalid signature';
        return;
    }

    $gsmPath = RECORDINGS_ROOT . '/' . $relpath;
    $mp3Relpath = preg_replace('/\.gsm$/', '.mp3', $relpath);
    $mp3Path = MP3_CACHE_ROOT . '/' . $mp3Relpath;

    if (!is_file($mp3Path)) {
        if (!is_file($gsmPath) || filesize($gsmPath) === 0) {
            // A 0-byte .gsm is a real (if rare) case - MixMonitor started
            // and stopped without ever capturing audio (e.g. an
            // immediately-aborted call). Nothing to transcode.
            http_response_code(404);
            echo 'recording not found';
            return;
        }
        // Cache miss (watcher hasn't caught up yet, or file predates it) -
        // transcode on the fly and write into the cache for next time.
        $mp3Dir = dirname($mp3Path);
        if (!is_dir($mp3Dir)) {
            @mkdir($mp3Dir, 0755, true);
        }
        $tmp = $mp3Path . '.' . getmypid() . '.tmp';
        // -f mp3 is required: ffmpeg guesses the container from the output
        // filename's extension, and the temp name ends in ".tmp" (to avoid
        // a half-written file being served mid-transcode), not ".mp3".
        $cmd = '/usr/bin/ffmpeg -y -loglevel error -i ' . escapeshellarg($gsmPath)
            . ' -ac 1 -b:a 48k -f mp3 ' . escapeshellarg($tmp) . ' 2>&1';
        shell_exec($cmd);
        if (is_file($tmp)) {
            rename($tmp, $mp3Path);
        }
    }

    if (!is_file($mp3Path)) {
        http_response_code(500);
        echo 'transcode failed';
        return;
    }

    stream_file_with_range($mp3Path, 'audio/mpeg');
}

function stream_file_with_range(string $path, string $contentType): void
{
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;

    header('Content-Type: ' . $contentType);
    header('Accept-Ranges: bytes');

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') {
            $start = (int) $m[1];
        }
        if ($m[2] !== '') {
            $end = (int) $m[2];
        }
        $end = min($end, $size - 1);
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }

    header('Content-Length: ' . ($end - $start + 1));

    $fh = fopen($path, 'rb');
    fseek($fh, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fh)) {
        $chunk = min(65536, $remaining);
        echo fread($fh, $chunk);
        flush();
        $remaining -= $chunk;
    }
    fclose($fh);
}
