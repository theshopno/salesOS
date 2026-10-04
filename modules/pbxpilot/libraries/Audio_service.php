<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Service to manage, transcode and deploy Asterisk IVR sound prompts.
 * Automatically converts user MP3/WAV uploads to telephony formats (8kHz mono WAV, ULAW, ALAW, GSM)
 * and deploys them to Asterisk custom sounds directory on the PBX server.
 */
class Audio_service
{
    private const ALLOWED_EXTENSIONS = ['mp3', 'wav', 'ogg', 'm4a'];
    private const PROMPT_SLOTS = [
        'main'    => 'ivr_confirmation',
        'confirm' => 'ivr_press_1',
        'cancel'  => 'ivr_press_2',
    ];

    private string $upload_dir;

    public function __construct()
    {
        $this->upload_dir = FCPATH . 'uploads/pbxpilot_prompts/';
        if (!is_dir($this->upload_dir)) {
            @mkdir($this->upload_dir, 0755, true);
        }
    }

    /**
     * Get list of prompt slots with current status and preview URLs.
     */
    public function get_prompt_slots(): array
    {
        $slots = [];
        foreach (self::PROMPT_SLOTS as $key => $default_name) {
            $configured = pbxpilot_get_option("pbxpilot_ivr_prompt_{$key}", "custom/{$default_name}");
            $clean_name = str_replace('custom/', '', $configured);
            
            // Check if local master file exists
            $preview_url = null;
            $local_file  = $this->upload_dir . $clean_name . '.mp3';
            if (!is_file($local_file)) {
                $local_file = $this->upload_dir . $clean_name . '.wav';
            }
            if (!is_file($local_file)) {
                // Fallback to initial media folder
                if (is_file(FCPATH . "media/{$clean_name}.mp3")) {
                    $preview_url = base_url("media/{$clean_name}.mp3");
                } elseif (is_file(FCPATH . "media/{$clean_name}.wav")) {
                    $preview_url = base_url("media/{$clean_name}.wav");
                }
            } else {
                $preview_url = base_url('uploads/pbxpilot_prompts/' . basename($local_file));
            }

            $labels = [
                'main'    => '১. অর্ডার কনফার্মেশন মেইন মেনু (Intro & Instruction)',
                'confirm' => '২. ১ চাপলে যা বলবে (Order Confirmed Audio)',
                'cancel'  => '৩. ২ চাপলে যা বলবে (Order Cancelled Audio)',
            ];

            $descriptions = [
                'main'    => 'কল রিসিভ হওয়ার সাথে সাথে কাস্টমারকে এই অডিওটি শোনানো হবে (যেমন: "অর্ডার নিশ্চিত করতে ১ চাপুন...")।',
                'confirm' => 'কাস্টমার ফোনে ১ চাপার সাথে সাথে এই অডিওটি বাজবে (যেমন: "ধন্যবাদ, অর্ডার কনফার্ম হয়েছে...")।',
                'cancel'  => 'কাস্টমার ফোনে ২ চাপার সাথে সাথে এই অডিওটি বাজবে (যেমন: "আপনার অর্ডারটি বাতিল করা হয়েছে...")।',
            ];

            $slots[$key] = [
                'key'         => $key,
                'name'        => $clean_name,
                'full_path'   => "custom/{$clean_name}",
                'label'       => $labels[$key] ?? ucfirst($key),
                'description' => $descriptions[$key] ?? '',
                'preview_url' => $preview_url,
            ];
        }

        return $slots;
    }

    /**
     * Upload, transcode and deploy a new audio file for a slot.
     *
     * @param string $slot 'main'|'confirm'|'cancel'
     * @param array $file $_FILES['audio_file']
     * @return array{success: bool, message: string, preview_url?: string}
     */
    public function upload_and_deploy(string $slot, array $file): array
    {
        if (!isset(self::PROMPT_SLOTS[$slot])) {
            return ['success' => false, 'message' => "অবৈধ অডিও স্লট: {$slot}"];
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'কোনো অডিও ফাইল আপলোড করা হয়নি।'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'message' => 'শুধুমাত্র MP3, WAV, OGG অথবা M4A অডিও ফাইল গ্রহণযোগ্য।'];
        }

        $base_name = self::PROMPT_SLOTS[$slot];
        $master_path = $this->upload_dir . "{$base_name}.mp3";

        // Save master copy
        if (!move_uploaded_file($file['tmp_name'], $master_path)) {
            return ['success' => false, 'message' => 'সার্ভারে ফাইল সংরক্ষণ করতে সমস্যা হয়েছে।'];
        }

        // Transcode to Asterisk Telephony Formats (8000Hz 16-bit Mono)
        $wav_file  = $this->upload_dir . "{$base_name}.wav";
        $ulaw_file = $this->upload_dir . "{$base_name}.ulaw";
        $alaw_file = $this->upload_dir . "{$base_name}.alaw";
        $gsm_file  = $this->upload_dir . "{$base_name}.gsm";

        $ffmpeg = '/usr/bin/ffmpeg';
        if (!is_executable($ffmpeg)) {
            $ffmpeg = trim(shell_exec('which ffmpeg 2>/dev/null') ?: 'ffmpeg');
        }

        // 1. WAV 8kHz 16-bit Mono PCM
        exec("{$ffmpeg} -y -i " . escapeshellarg($master_path) . " -ar 8000 -ac 1 -acodec pcm_s16le " . escapeshellarg($wav_file) . " 2>&1", $out1, $ret1);
        // 2. ULAW 8kHz
        exec("{$ffmpeg} -y -i " . escapeshellarg($master_path) . " -ar 8000 -ac 1 -f mulaw " . escapeshellarg($ulaw_file) . " 2>&1", $out2, $ret2);
        // 3. ALAW 8kHz
        exec("{$ffmpeg} -y -i " . escapeshellarg($master_path) . " -ar 8000 -ac 1 -f alaw " . escapeshellarg($alaw_file) . " 2>&1", $out3, $ret3);
        // 4. GSM 8kHz
        exec("{$ffmpeg} -y -i " . escapeshellarg($master_path) . " -ar 8000 -ac 1 -f gsm " . escapeshellarg($gsm_file) . " 2>&1", $out4, $ret4);

        if ($ret1 !== 0 || !is_file($wav_file)) {
            return [
                'success' => false,
                'message' => 'অডিও ট্রান্সকোডিং ব্যর্থ হয়েছে: ' . implode(' ', array_slice($out1, -3)),
            ];
        }

        // Deploy to Munzu PBX server via SCP
        $deploy_cmd = "scp -o BatchMode=yes -o ConnectTimeout=8 "
            . escapeshellarg($wav_file) . " "
            . escapeshellarg($ulaw_file) . " "
            . escapeshellarg($alaw_file) . " "
            . escapeshellarg($gsm_file) . " "
            . "munzu:/usr/share/asterisk/sounds/custom/ 2>&1";
        
        exec($deploy_cmd, $scp_out, $scp_ret);

        if ($scp_ret !== 0) {
            return [
                'success' => false,
                'message' => 'Munzu PBX সার্ভারে ফাইল স্থাপন ব্যর্থ হয়েছে। SCP এরর: ' . implode(' ', $scp_out),
            ];
        }

        // Fix permissions on remote Asterisk
        exec("ssh munzu 'chown -R asterisk:asterisk /usr/share/asterisk/sounds/custom/ && chmod -R 644 /usr/share/asterisk/sounds/custom/*'", $perm_out, $perm_ret);

        // Update option
        pbxpilot_update_option("pbxpilot_ivr_prompt_{$slot}", "custom/{$base_name}");

        return [
            'success'     => true,
            'message'     => 'অডিও ফাইল সফলভাবে আপলোড, অপ্টিমাইজ এবং PBX সার্ভারে অ্যাক্টিভ করা হয়েছে!',
            'preview_url' => base_url("uploads/pbxpilot_prompts/{$base_name}.mp3?v=" . time()),
        ];
    }
}
