<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pulls new rows from the PBX's CDR database (asteriskcdrdb.cdr, written by
 * Asterisk's cdr_adaptive_odbc — see provision_pbx.sh) into pbxpilot_calls,
 * with basic direction inference and lead/contact phone matching.
 *
 * Connection is settings-driven (§8a) — a second, ad-hoc CI database
 * connection, not the app's default one. Fails soft: sync() never throws,
 * always returns a result array and records status in pbxpilot_cdr_sync.
 */
class Cdr_sync_service
{
    private const BATCH_SIZE = 200;

    // Asterisk writes a CDR row only when a call ENDS, not when it starts —
    // so rows become visible to this query in call-END order, not the
    // `start`-time order the query sorts/filters by. A long call that
    // started earlier can still surface after a short call that started
    // later but ended first. If the watermark only ever advances to the
    // short call's start time, the long call's earlier `start` then falls
    // behind `start > $last_start` and is excluded forever — silently and
    // permanently dropping a real, recorded call (confirmed live
    // 2026-09-07: an 11-minute answered call was lost exactly this way
    // while a 10-second call that started 79s later, and so ended first,
    // pushed the watermark past it). Re-querying from a fixed distance
    // behind the watermark — well past any plausible call duration — and
    // relying on import_row()'s existing per-uniqueid dedup to skip
    // already-synced rows closes the gap without a schema change.
    private const WATERMARK_LOOKBACK_SECONDS = 3600;

    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /** @return array{ok: bool, synced: int, error: ?string} */
    public function sync(): array
    {
        $cdr_db = $this->connect_cdr_db();
        if ($cdr_db === null) {
            $this->log_sync(0, 'error', 'Could not connect to CDR database (Settings → Connection).');
            return ['ok' => false, 'synced' => 0, 'error' => 'CDR DB connection failed'];
        }

        $last_start = $this->last_synced_start();
        $fetch_from = date('Y-m-d H:i:s', strtotime($last_start) - self::WATERMARK_LOOKBACK_SECONDS);

        $rows = $cdr_db->select('*')
            ->where('start >', $fetch_from)
            ->order_by('start', 'asc')
            ->limit(self::BATCH_SIZE)
            ->get('cdr')
            ->result_array();

        $agents_by_ext = $this->agents_by_extension();
        $synced = 0;
        $last_uniqueid = null;
        $failed_uniqueid = null;
        $failed_error = null;

        foreach ($rows as $row) {
            $result = $this->import_row($row, $agents_by_ext);
            if ($result === 'inserted') {
                $synced++;
            } elseif ($result === 'failed') {
                // Stop here rather than continuing past a row we couldn't
                // insert: `last_synced_start()` derives its watermark from
                // MAX(calldate) already IN pbxpilot_calls, so if we let later
                // rows in this batch import successfully, their calldate
                // becomes the new floor and this row's `start > floor` check
                // would never be true again on a future run — silently
                // losing it forever instead of retrying it.
                $failed_uniqueid = $row['uniqueid'];
                $failed_error    = $this->CI->db->error()['message'] ?? 'unknown error';
                break;
            }
            $last_uniqueid = $row['uniqueid'];
        }

        if ($failed_uniqueid !== null) {
            $error = "Insert failed for uniqueid {$failed_uniqueid}: {$failed_error}";
            $this->log_sync($synced, 'error', $error, $last_uniqueid);

            return ['ok' => false, 'synced' => $synced, 'error' => $error];
        }

        $this->log_sync($synced, 'ok', null, $last_uniqueid);

        return ['ok' => true, 'synced' => $synced, 'error' => null];
    }

    /**
     * One-time historical recovery for calls the watermark bug (see
     * WATERMARK_LOOKBACK_SECONDS above) already dropped before that fix
     * existed — a plain date-range scan, independent of the watermark, that
     * imports whatever in [$from, $until] isn't already in pbxpilot_calls.
     * Unlike sync(), never stops early on a single failed row (a failure
     * here can't corrupt any watermark — there isn't one — so skipping past
     * it and recovering everything else it can is strictly better than
     * halting a recovery pass over one bad row). Safe to re-run or overlap
     * ranges: import_row()'s per-uniqueid dedup makes this idempotent.
     *
     * @return array{ok: bool, synced: int, failed: int, batches: int, error: ?string}
     */
    public function backfill_range(string $from, string $until, int $max_batches = 50): array
    {
        $cdr_db = $this->connect_cdr_db();
        if ($cdr_db === null) {
            return ['ok' => false, 'synced' => 0, 'failed' => 0, 'batches' => 0, 'error' => 'CDR DB connection failed'];
        }

        $agents_by_ext = $this->agents_by_extension();
        $cursor = $from;
        $synced = 0;
        $failed = 0;
        $batches = 0;

        for (; $batches < $max_batches; $batches++) {
            $rows = $cdr_db->select('*')
                ->where('start >', $cursor)
                ->where('start <=', $until)
                ->order_by('start', 'asc')
                ->limit(self::BATCH_SIZE)
                ->get('cdr')
                ->result_array();

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $result = $this->import_row($row, $agents_by_ext);
                if ($result === 'inserted') {
                    $synced++;
                } elseif ($result === 'failed') {
                    $failed++;
                }
                $cursor = $row['start'];
            }

            if (count($rows) < self::BATCH_SIZE) {
                break;
            }
        }

        $this->log_sync($synced, 'ok', "Backfill {$from} .. {$until}: {$synced} recovered, {$failed} failed, {$batches} batch(es)");

        return ['ok' => true, 'synced' => $synced, 'failed' => $failed, 'batches' => $batches, 'error' => null];
    }

    // ── Row import ───────────────────────────────────────────────────────────

    /** @return string 'inserted'|'skipped'|'failed' */
    private function import_row(array $row, array $agents_by_ext): string
    {
        if ($this->CI->db->where('uniqueid', $row['uniqueid'])->count_all_results(db_prefix() . 'pbxpilot_calls') > 0) {
            return 'skipped'; // already imported — sync is idempotent
        }

        // Which side of the call is "the agent" depends on direction, and the
        // agent's extension shows up in a different CDR column each way:
        //   outbound (agent dials out): channel=PJSIP/<ext>-..., dst=<external number>
        //   inbound  (trunk dials in):  dstchannel=PJSIP/<ext>-..., dst=an internal
        //     dialplan label like "trydesk"/"s" (Gosub/Goto target), never the
        //     extension itself — so `dst` can't be used to detect inbound at all,
        //     only `dstchannel` can. `src` is reliably the external caller's
        //     number in both directions (it's never rewritten by internal hops).
        $src_ext = $this->extract_extension($row['channel'] ?? '');
        $dst_ext = $this->extract_extension($row['dstchannel'] ?? '');

        $extension    = null;
        $agent        = null;
        $direction    = 'unknown';
        $other_number = null;

        if ($src_ext !== null && isset($agents_by_ext[$src_ext])) {
            $extension    = $src_ext;
            $agent        = $agents_by_ext[$src_ext];
            $direction    = 'outbound';
            $other_number = $row['dst'] ?? '';
        } elseif ($dst_ext !== null && isset($agents_by_ext[$dst_ext])) {
            $extension    = $dst_ext;
            $agent        = $agents_by_ext[$dst_ext];
            $direction    = 'inbound';
            $other_number = $row['src'] ?? '';
        }

        $match = $other_number ? $this->match_entity($other_number) : ['type' => 'none', 'id' => null];

        $data = [
            'uniqueid'      => $row['uniqueid'],
            'calldate'      => $row['start'],
            'direction'     => $direction,
            'src'           => $row['src']  ?? '',
            'dst'           => $row['dst']  ?? '',
            'extension'     => $extension,
            'duration'      => (int) ($row['duration'] ?? 0),
            'billsec'       => (int) ($row['billsec']  ?? 0),
            'disposition'   => $row['disposition'] ?? '',
            // MixMonitor is started with the ,b flag (record-while-bridged only —
            // see extensions_custom.conf) and never explicitly stopped/deleted
            // outside the voicemail-fallback branch, so a call that never bridged
            // (no answer, busy, etc.) always leaves a real but 0-byte .gsm behind.
            // billsec > 0 is the same signal Asterisk itself uses for "was
            // bridged" — if it's 0, no audio was ever captured, so don't claim a
            // recording exists (confirmed against live Munzu data 2026-09-07:
            // 281/497 monitor files were 0-byte, all billsec=0 calls).
            'recordingfile' => ((int) ($row['billsec'] ?? 0) > 0 && ($row['uniqueid'] ?? '') !== '')
                ? $row['uniqueid'] . '.wav'
                : null,
            'agent_id'      => $agent['staff_id'] ?? null,
            'match_type'    => $match['type'],
            'lead_id'       => $match['type'] === 'lead'    ? $match['id'] : null,
            'contact_id'    => $match['type'] === 'contact' ? $match['id'] : null,
            'client_id'     => $match['type'] === 'client'  ? $match['id'] : null,
        ];

        return $this->CI->db->insert(db_prefix() . 'pbxpilot_calls', $data) ? 'inserted' : 'failed';
    }

    private function extract_extension(string $channel): ?string
    {
        // e.g. "PJSIP/1002-00000001" -> "1002"
        if (preg_match('/^PJSIP\/(\d+)-/', $channel, $m)) {
            return $m[1];
        }

        return null;
    }

    /** @return array{type: string, id: ?int} */
    private function match_entity(string $number): array
    {
        $normalized = $this->normalize_phone($number);
        if ($normalized === '') {
            return ['type' => 'none', 'id' => null];
        }

        $lead = $this->CI->db->select('id')
            ->like('phonenumber', $normalized, 'both')
            ->get(db_prefix() . 'leads')
            ->row_array();
        if ($lead) {
            return ['type' => 'lead', 'id' => (int) $lead['id']];
        }

        $contact = $this->CI->db->select('id')
            ->like('phonenumber', $normalized, 'both')
            ->get(db_prefix() . 'contacts')
            ->row_array();
        if ($contact) {
            return ['type' => 'contact', 'id' => (int) $contact['id']];
        }

        return ['type' => 'none', 'id' => null];
    }

    /** Strip everything but digits, keep the last 8 (loose match across
     *  country-code/leading-zero variations). */
    private function normalize_phone(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        return $digits === '' ? '' : substr($digits, -8);
    }

    // ── Bookkeeping ──────────────────────────────────────────────────────────

    /**
     * Watermark is the max `calldate` already imported into pbxpilot_calls —
     * never the importing app's own wall-clock time. The PBX may be in a
     * different timezone than the CRM server (kutumbari runs BST, this app
     * runs +06) and both store naive datetimes with no tz info, so comparing
     * CDR rows against `date('Y-m-d H:i:s')` from PHP silently drops every
     * row forever once the two clocks disagree by more than zero. Staying
     * entirely within the PBX's own timestamp domain avoids that.
     */
    private function last_synced_start(): string
    {
        $row = $this->CI->db->select_max('calldate')
            ->get(db_prefix() . 'pbxpilot_calls')->row_array();

        return $row['calldate'] ?? '1970-01-01 00:00:00';
    }

    private function log_sync(int $synced, string $status, ?string $error, ?string $last_uniqueid = null): void
    {
        $this->CI->db->insert(db_prefix() . 'pbxpilot_cdr_sync', [
            'last_sync'      => date('Y-m-d H:i:s'),
            'last_uniqueid'  => $last_uniqueid,
            'records_synced' => $synced,
            'status'         => $status,
            'error_msg'      => $error,
        ]);
    }

    /** @return array<string, array{staff_id: int, extension: string}> keyed by extension */
    private function agents_by_extension(): array
    {
        $rows = $this->CI->db->select('staff_id, extension')
            ->where('is_active', 1)
            ->get(db_prefix() . 'pbxpilot_agents')->result_array();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['extension']] = $r;
        }

        return $out;
    }

    // ── CDR DB connection ────────────────────────────────────────────────────

    private function connect_cdr_db()
    {
        $host = pbxpilot_get_option('pbxpilot_cdr_db_host', '');
        if ($host === '') {
            return null;
        }

        $config = [
            'hostname' => $host,
            'port'     => (int) pbxpilot_get_option('pbxpilot_cdr_db_port', 3306),
            'username' => pbxpilot_get_option('pbxpilot_cdr_db_user', ''),
            'password' => pbxpilot_get_option('pbxpilot_cdr_db_password', ''),
            'database' => pbxpilot_get_option('pbxpilot_cdr_db_name', 'asteriskcdrdb'),
            'dbdriver' => 'mysqli',
            'dbprefix' => '',
            'pconnect' => false,
            'db_debug' => false,
            'cache_on' => false,
            'char_set' => 'utf8mb4',
            'dbcollat' => 'utf8mb4_unicode_ci',
        ];

        try {
            return $this->CI->load->database($config, true);
        } catch (Exception $e) {
            return null;
        }
    }
}
