<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpilot_audio_service
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Parse filename to extract metadata
     */
    public function parse_filename($filename)
    {
        $metadata = [
            'phone_number' => null,
            'call_type' => 'manual',
            'extension_number' => null,
            'staff_id' => null,
            'unique_call_id' => null,
        ];

        // Remove extension for parsing
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $parts = explode('-', $name);

        // Rule 1: New Format (direction-callerID-extension-date-time-uniqueID)
        // out-01303088121-106-20260210-153818-1770716298.1098
        if (count($parts) >= 6 && ($parts[0] == 'out' || $parts[0] == 'in')) {
            $metadata['call_type'] = ($parts[0] == 'out') ? 'outgoing' : 'incoming';
            $metadata['phone_number'] = $parts[1];
            $metadata['extension_number'] = $parts[2];
            $metadata['unique_call_id'] = $parts[count($parts) - 1]; // Last part is unique ID

            // Map extension to staff
            $staff_extensions = json_decode(get_option('pbxpilot_staff_extensions', '[]'), true);
            foreach ($staff_extensions as $staff_id => $ext) {
                if ($ext == $metadata['extension_number']) {
                    $metadata['staff_id'] = $staff_id;
                    break;
                }
            }
        }
        // Fallback Rule 2: Original "out-" format
        elseif (strpos($name, 'out-') === 0) {
            if (isset($parts[1])) {
                $metadata['phone_number'] = $parts[1];
                $metadata['call_type'] = 'outgoing';
            }
        }
        // Fallback Rule 3: Original "exten-" format
        elseif (strpos($name, 'exten-') === 0) {
            if (isset($parts[1])) {
                $metadata['extension_number'] = $parts[1];
            }
            if (isset($parts[2])) {
                $metadata['phone_number'] = $parts[2];
            }
            $metadata['call_type'] = 'extension';
        }

        return $metadata;
    }

    /**
     * Generate file hash
     */
    public function generate_hash($filepath)
    {
        return md5_file($filepath);
    }

    /**
     * Get audio duration in seconds
     */
    public function get_duration($file_path)
    {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($extension === 'wav') {
            return $this->get_wav_duration($file_path);
        }

        if ($extension === 'mp3') {
            return $this->get_mp3_duration($file_path);
        }

        return 0;
    }

    /**
     * Basic MP3 duration estimate based on standard bitrates
     */
    private function get_mp3_duration($file_path)
    {
        $fileSize = filesize($file_path);
        if ($fileSize <= 0) return 0;

        // Try to find a frame header to guess bitrate
        $fp = fopen($file_path, 'r');
        if (!$fp) return 0;

        // Read first 4KB to find a frame sync
        $data = fread($fp, 4096);
        fclose($fp);

        // Very basic: just use a common bitrate if we can't parse
        // Most PBX MP3s are 128kbps or 64kbps
        $bitrate = 128000;

        // Convert to duration: (bytes * 8) / bits_per_second
        return round(($fileSize * 8) / $bitrate);
    }

    /**
     * Parse WAV header to get duration (more robust)
     */
    private function get_wav_duration($file_path)
    {
        $fp = fopen($file_path, 'r');
        if (!$fp) return 0;

        $header = fread($fp, 100); // Read more to find 'fmt ' and 'data' chunks
        fclose($fp);

        if (strlen($header) < 44) return 0;

        // Check RIFF header
        if (substr($header, 0, 4) !== 'RIFF') return 0;

        // Find 'fmt ' chunk
        $fmt_pos = strpos($header, 'fmt ');
        if ($fmt_pos === false) return 0;

        // Byte rate is 8 bytes after 'fmt ' tag (offset 28 in standard header)
        // But since we found 'fmt ', it's at $fmt_pos + 8 + 8 = $fmt_pos + 4 + 4 + 4 + 4? No.
        // fmt chunk structure: ID (4), Size (4), AudioFormat (2), Channels (2), SampleRate (4), ByteRate (4)
        $byteRate = unpack('V', substr($header, $fmt_pos + 16, 4))[1];

        // Find 'data' chunk for size
        $data_pos = strpos($header, 'data');
        if ($data_pos !== false) {
            $dataSize = unpack('V', substr($header, $data_pos + 4, 4))[1];
        } else {
            $dataSize = filesize($file_path) - 44;
        }

        if ($byteRate <= 0) return 0;

        return round($dataSize / $byteRate);
    }

    /**
     * Handle file upload
     */
    public function handle_upload($field_name = 'audio_file')
    {
        $config['upload_path']   = PBXPILOT_UPLOADS_FOLDER;
        $config['allowed_types'] = 'mp3|wav|m4u';
        $config['max_size']      = get_pbxpilot_settings()->max_audio_size * 1024;
        $config['encrypt_name']  = false; // Keep original name for parsing, then maybe rename?

        // Actually, we should keep the name to parse it, then maybe move/rename.
        // Let's keep original name but append timestamp if exists to avoid overwrite before parsing.

        $this->CI->load->library('upload', $config);

        if (!$this->CI->upload->do_upload($field_name)) {
            return ['status' => false, 'error' => $this->CI->upload->display_errors('', '')];
        }

        $upload_data = $this->CI->upload->data();
        return ['status' => true, 'data' => $upload_data];
    }
}
