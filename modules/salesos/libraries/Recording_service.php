<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Fetches a call recording from the PBX and serves it as WAV.
 *
 * The PBX records in GSM (MixMonitor(...,b), set in the dialplan) — about
 * 10x smaller than 16-bit PCM WAV for the same audio, and it's Asterisk's
 * own native, always-available codec (no external encoder). GSM itself
 * isn't something browsers play directly via <audio>, so on first request
 * for a given call this asks the PBX (over AMI's "Command" action, running
 * Asterisk's own built-in `file convert`) to produce a WAV copy next to the
 * GSM original, then fetches that. No ffmpeg or any other binary is
 * needed anywhere — not on the PBX (file convert is core Asterisk) and not
 * on the CRM host (which, on ordinary shared/jailed hosting, usually has
 * no ffmpeg and no way to install one). The converted WAV is cached
 * locally after that so a given call is only fetched/converted once.
 * Fails soft throughout: any failure returns null, never throws — a
 * missing/old recording should not break the call history tab, just hide
 * the play button.
 */
class Recording_service
{
    private const CACHE_DIR = 'uploads/salesos_recordings/';

    /** @return string|null absolute path to a local WAV, or null if unavailable */
    public function get_recording_path(string $uniqueid): ?string
    {
        $uniqueid = basename($uniqueid); // never let this become a path

        $cache_dir = FCPATH . self::CACHE_DIR;
        if (!is_dir($cache_dir) && !mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
            return null;
        }

        $wav_path = $cache_dir . $uniqueid . '.wav';
        if (is_file($wav_path)) {
            return $wav_path;
        }

        $base_url = rtrim((string) salesos_get_option('salesos_recordings_url', ''), '/');
        if ($base_url === '') {
            return null;
        }

        $wav_data = $this->fetch($base_url . '/' . rawurlencode($uniqueid) . '.wav');

        if ($wav_data === null) {
            // No WAV up there yet — the source is GSM. Ask the PBX to make
            // one, then try the fetch again. Harmless no-op if a WAV
            // somehow already exists (Asterisk's `file convert` just
            // overwrites it) or if the GSM itself doesn't exist (fails
            // soft below either way).
            $this->convert_on_pbx($uniqueid);
            $wav_data = $this->fetch($base_url . '/' . rawurlencode($uniqueid) . '.wav');
        }

        if ($wav_data === null) {
            return null;
        }

        return file_put_contents($wav_path, $wav_data) !== false ? $wav_path : null;
    }

    private function convert_on_pbx(string $uniqueid): bool
    {
        $monitor_dir = rtrim((string) salesos_get_option('salesos_recordings_monitor_dir', '/var/spool/asterisk/monitor'), '/');
        if ($monitor_dir === '') {
            return false;
        }

        $CI = &get_instance();
        $CI->load->library(SALESOS_MODULE_NAME . '/Ami_service');

        $src = $monitor_dir . '/' . $uniqueid . '.gsm';
        $dst = $monitor_dir . '/' . $uniqueid . '.wav';
        $result = $CI->ami_service->run_command('file convert ' . $src . ' ' . $dst);

        return (bool) ($result['success'] ?? false);
    }

    private function fetch(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $data      = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ok        = $data !== false && curl_errno($ch) === 0 && $http_code === 200;
        curl_close($ch);

        return $ok ? $data : null;
    }

    /**
     * Deletes cached WAVs older than $retention_days. The cache is a
     * derived artifact (re-fetchable/re-convertible from the PBX at any
     * time via get_recording_path()) as long as the PBX's own GSM
     * recording still exists there — this only trims OUR local copy, it
     * never touches anything on the PBX. Safe to call often; skips work
     * entirely if nothing is old enough yet.
     *
     * @return array{deleted: int, freed_bytes: int}
     */
    public function cleanup_old_cache(int $retention_days): array
    {
        $cache_dir = FCPATH . self::CACHE_DIR;
        if ($retention_days <= 0 || !is_dir($cache_dir)) {
            return ['deleted' => 0, 'freed_bytes' => 0];
        }

        $cutoff      = time() - ($retention_days * 86400);
        $deleted     = 0;
        $freed_bytes = 0;

        // *.mp3 covered too, purely to clean up leftover files from before
        // this module went WAV-direct (an earlier revision) and then
        // GSM-on-PBX (this revision) — see class docblock.
        foreach (array_merge(glob($cache_dir . '*.wav') ?: [], glob($cache_dir . '*.mp3') ?: []) as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                $freed_bytes += filesize($file);
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return ['deleted' => $deleted, 'freed_bytes' => $freed_bytes];
    }
}
