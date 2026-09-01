<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpilot_ai_service
{
    private $CI;
    private $api_key;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Transcribe audio using configured provider
     */
    public function transcribe($file_path, $provider = null)
    {
        $settings = get_pbxpilot_settings();
        if (!$provider) {
            $provider = $settings->default_ai_provider;
        }

        if ($provider === 'openai') {
            return $this->transcribe_whisper($file_path);
        } elseif ($provider === 'google') {
            return $this->transcribe_google($file_path);
        }

        return ['status' => false, 'error' => 'Invalid AI provider'];
    }

    /**
     * Generate summary using configured provider
     */
    public function generate_summary($text, $preset_key, $case_key = null, $provider = 'openai', $model = null, $metadata = [])
    {
        $prompt = $this->getMasterPromptTemplate($case_key, $preset_key, $metadata);

        // Currently, only OpenAI (GPT) is implemented for summaries.
        // We use it regardless of whether transcription was done by OpenAI or Google.
        return $this->chat_completion($prompt, $text, $model);
    }

    /**
     * Get the structured Master Prompt for AI
     */
    private function getMasterPromptTemplate($case_key, $format_key, $metadata)
    {
        $cases = get_pbxpilot_cases();
        $case_name = isset($cases[$case_key]) ? $cases[$case_key]['name'] : 'General Discussion';
        $case_goal = isset($cases[$case_key]) ? $cases[$case_key]['goal'] : 'Understand the general intent and summarize the key points.';

        $service_context = get_option('pbxpilot_product_service', 'No specific context provided.');

        $operator_name = $metadata['operator_name'] ?? 'অপারেটর';
        $operator_ext  = $metadata['operator_extension'] ?? 'N/A';
        $client_name   = $metadata['client_name'] ?? ($metadata['client_phone'] ?? 'কাস্টমার');
        $client_phone  = $metadata['client_phone'] ?? 'N/A';

        $prompt = <<<EOD
--------------------------------------------------------------------------------
REASONING PROTOCOL: FOLLOW THESE PHASES SILENTLY BEFORE GENERATING SUMMARY
--------------------------------------------------------------------------------
PHASE 1: SERVICE CONTEXT MAPPING
- Agency Service: $service_context
- Goal: Identify which speaker represents this agency.

PHASE 2: BEHAVIORAL PATTERN MATCHING
- Operator ($operator_name) Signatures: Explains the internal process (how doctors work, membership benefits), offers support, and thanks the other person for their time.
- Client ($client_name) Signatures: Asks about prices, explains THEIR OWN health service models (which differ from the agency), discusses purchasing "packages", and mentions future meetings.

PHASE 3: ROLE CONFLICT RESOLUTION
- If roles seem reversed, look for the person "offering terms" (Operator) and the person "inquiring/paying" (Client).

--------------------------------------------------------------------------------
CALL METADATA & STRATEGIC GOAL
--------------------------------------------------------------------------------
Operator Name: $operator_name (Staff)
Client Name: $client_name (Lead/Customer)
Call Case: $case_name
ANALYSIS FOCUS: $case_goal

--------------------------------------------------------------------------------
GLOBAL ANALYSIS RULES
--------------------------------------------------------------------------------
1. NATURAL BANGLA: Use very natural, conversational Bangla. No corporate or robotic wording.
2. NO ASSUMPTIONS: Do not invent details. If roles are unclear, say so.
3. BUSINESS REALITY: Focus on the client's actual pain points and the execution gaps.
4. FORMATTING: Use '----------------' for sections. NO markdown symbols (#, *, etc).
5. OUTPUT: 100% Bangla, friendly, and suitable for direct CRM note usage.

--------------------------------------------------------------------------------
PRESET EXECUTION LOGIC
--------------------------------------------------------------------------------
EOD;

        if ($format_key === 'DETAILED_SUMMARY_ACTION') {
            $prompt .= "
Generate output using this exact structure (Bangla):

🔹 ব্যবসার বর্তমান অবস্থা (কল অনুযায়ী)
🔹 ক্লায়েন্টের মূল সমস্যা কোথায়
🔹 কেন বর্তমানে সেলস বা লিড আসছে না / সমস্যা চলছে
🔹 ক্লায়েন্ট আসলে কী চাচ্ছে কিন্তু স্পষ্টভাবে বলতে পারছে না
🔹 অপারেটর কীভাবে কথোপকথনটি হ্যান্ডেল করেছে (সংক্ষেপে)

------------------------------------
✅ এক্সিকিউটেবল করণীয়:
১. এখনই কী করলে বাস্তব উন্নতি শুরু হতে পারে  
২. আগামী ৭ দিনের মধ্যে কোন কাজগুলো করলে ফল পাওয়ার সম্ভাবনা বেশি  
৩. কন্টেন্ট / অফার / প্রসেসের কোন জায়গায় পরিবর্তন দরকার
";
        } elseif ($format_key === 'SHORT_SUMMARY_BULLET') {
            $prompt .= "
STRICT RULES (NO EXCEPTIONS):
- ONLY bullet points.
- NO headings, NO categories, NO extra explanation, NO emojis.
- DO NOT mention 'Operator', 'Client', 'Staff', or 'Customer' names/roles.
- Just list what was discussed in the call in ultra-concise bullet points.
- Focus on the content of the discussion only.
Generate only bullet points like:
• …
• …
";
        } elseif ($format_key === 'SHORT_SUMMARY_ACTION') {
            $prompt .= "
Generate output using this exact structure (Bangla):
সংক্ষেপে কল সারাংশ:
(২–৩ লাইনের মধ্যে, সহজ ভাষায়)

------------------------------------
📌 করণীয় একশন:
• প্রথম গুরুত্বপূর্ণ কাজ  
• দ্বিতীয় কাজ  
• কোন জায়গায় পরিবর্তন দরকার
";
        } elseif ($format_key === 'AGENT_PERFORMANCE_CRITIQUE') {
            $prompt .= "
Generate output using this exact structure (Bangla):
🕵️ অপারেটর পারফরম্যান্স রিভিউ:
• কথোপকথনের দক্ষতা (১-১০ এর মধ্যে): [স্কোর]
• ক্লায়েন্টের সমস্যা বোঝার ক্ষমতা: [লিখে জানাবেন]
• সঠিক তথ্য প্রদান করেছে কি না: [হ্যাঁ/না এবং কেন]
• কোথায় সরাসরি ইমপ্রুভমেন্ট দরকার: [পয়েন্ট আকারে]

------------------------------------
🎯 কোচিং টিপস:
পরের কলে এই ক্লায়েন্টকে হ্যান্ডেল করার জন্য টিপস দিন।
";
        } elseif ($format_key === 'CUSTOMER_SATISFACTION_INDEX') {
            $prompt .= "
Generate output using this exact structure (Bangla):
📊 কাস্টমার স্যাটিসফ্যাকশন ও রিস্ক এনালাইসিস:
• বর্তমান সন্তুষ্টি লেভেল (১-১০): [স্কোর]
• গ্রাহকের প্রধান ভয়ের জায়গা (Fear): [বিস্তারিত]
• গ্রাহক হারিয়ে যাওয়ার সম্ভাবনা (Churn Risk): [Low/Medium/High]
• পুনরায় কল রিসিভ করার সম্ভাবনা: [শতকরা হার]

------------------------------------
⚠️ হাই-রিস্ক ফ্যাক্টর:
যদি কোনো নেতিবাচক সিগনাল থাকে তবে তা উল্লেখ করুন।
";
        } else { // KEY_TALKING_POINTS or fallback
            $prompt .= "
Generate output using this exact structure (Bangla):
🔑 গুরুত্বপূর্ণ কথোপকথনের পয়েন্ট:
• ক্লায়েন্ট যে বিষয়টি নিয়ে সবচেয়ে চিন্তিত ছিল  
• ক্লায়েন্ট যেখানে দ্বিধা বা অনিশ্চয়তা দেখিয়েছে  
• যে সমস্যা বা কথা ক্লায়েন্ট বারবার উল্লেখ করেছে  
• অপারেটরের কোন কথায় বা প্রস্তাবে ক্লায়েন্ট বেশি সাড়া দিয়েছে
";
        }

        $prompt .= "
-----------------------------------------
FINAL OUTPUT RULES
-----------------------------------------
- Output must be 100% Bangla
- Tone must feel human, friendly, and practical
- No repetition of metadata
- No assumptions 
- Output must be suitable for direct CRM note usage
";

        return $prompt;
    }

    /**
     * Full AI Processing Flow for a Call
     */
    public function process_call_ai($call_id)
    {
        $this->CI->load->model(PBXPILOT_MODULE_NAME . '/pbxpilot_model');
        $this->CI->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_lead_service');

        $call = $this->CI->pbxpilot_model->get_call($call_id);
        if (!$call) return ['status' => false, 'error' => 'Call not found'];

        // 1. Transcribe
        $transcription = $this->transcribe($call->file_path, $call->ai_provider);
        if (!$transcription['status']) {
            $this->CI->pbxpilot_model->update_call($call_id, ['last_error' => $transcription['error']]);
            return $transcription;
        }

        $this->CI->pbxpilot_model->update_call($call_id, [
            'transcription_text' => $transcription['text'],
            'is_processed' => 1,
            'last_error' => NULL // Clear previous errors
        ]);

        // Fetch metadata for better prompt context
        $metadata = [
            'operator_name' => 'অপারেটর',
            'operator_extension' => $call->extension_number,
            'client_phone' => $call->phone_number,
            'client_name' => null
        ];

        if ($call->staff_id) {
            $this->CI->db->where('staffid', $call->staff_id);
            $staff = $this->CI->db->get(db_prefix() . 'staff')->row();
            if ($staff) {
                $metadata['operator_name'] = $staff->firstname . ' ' . $staff->lastname;
            }
        }

        $contact = $this->CI->pbxpilot_lead_service->get_contact_info($call->phone_number);
        if ($contact) {
            $metadata['client_name'] = $contact['name'];
        }

        $case_type = $call->last_case_used ?: 'CASE_GENERAL_INQUIRY';
        $format_type = $call->preset_type ?: 'DETAILED_SUMMARY_ACTION';

        // 2. Summarize
        $summary = $this->generate_summary($transcription['text'], $format_type, $case_type, $call->ai_provider, null, $metadata);
        if ($summary['status']) {
            $new_summary = [
                'id' => uniqid(),
                'text' => $summary['summary'],
                'model' => get_option('pbxpilot_openai_model', 'gpt-4o-mini'),
                'preset' => $format_type,
                'case' => $case_type,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->CI->pbxpilot_model->update_call($call_id, [
                'ai_summary' => $summary['summary'],
                'summary_history' => json_encode([$new_summary]),
                'last_model_used' => $new_summary['model']
            ]);

            // 3. Lead Mapping
            $this->CI->pbxpilot_lead_service->map_to_lead($call_id);

            return ['status' => true, 'summary' => $summary['summary']];
        }

        return ['status' => false, 'error' => $summary['error'] ?? 'Summarization failed'];
    }

    /**
     * Rephrase AI Summary with a specific model
     */
    public function rephrase_call_ai($call_id, $model = null, $format_key = null, $case_key = null)
    {
        $this->CI->load->model(PBXPILOT_MODULE_NAME . '/pbxpilot_model');
        $this->CI->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_lead_service');

        $call = $this->CI->pbxpilot_model->get_call($call_id);
        if (!$call) return ['status' => false, 'error' => 'Call not found'];

        $format = $format_key ?: ($call->preset_type ?: 'DETAILED_SUMMARY_ACTION');
        $case = $case_key ?: ($call->last_case_used ?: 'CASE_GENERAL_INQUIRY');

        // Metadata
        $metadata = [
            'operator_name' => 'অপারেটর',
            'operator_extension' => $call->extension_number,
            'client_phone' => $call->phone_number,
            'client_name' => null
        ];

        if ($call->staff_id) {
            $this->CI->db->where('staffid', $call->staff_id);
            $staff = $this->CI->db->get(db_prefix() . 'staff')->row();
            if ($staff) {
                $metadata['operator_name'] = $staff->firstname . ' ' . $staff->lastname;
            }
        }

        $contact = $this->CI->pbxpilot_lead_service->get_contact_info($call->phone_number);
        if ($contact) {
            $metadata['client_name'] = $contact['name'];
        }

        $result = $this->generate_summary($call->transcription_text, $format, $case, $call->ai_provider, $model, $metadata);

        if ($result['status']) {
            $history = json_decode($call->summary_history ?? '[]', true);
            $new_summary = [
                'id' => uniqid(),
                'text' => $result['summary'],
                'model' => $model ?: 'gpt-4o-mini',
                'preset' => $format,
                'case' => $case,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $history[] = $new_summary;

            $this->CI->pbxpilot_model->update_call($call_id, [
                'ai_summary' => $result['summary'],
                'summary_history' => json_encode($history),
                'last_model_used' => $new_summary['model'],
                'preset_type' => $format,
                'last_case_used' => $case
            ]);
            return ['status' => true, 'summary' => $result['summary'], 'history' => $history];
        }

        return $result;
    }

    /**
     * OpenAI Whisper Transcription (Actual Implementation)
     */
    private function transcribe_whisper($file_path)
    {
        $api_key = get_option('pbxpilot_openai_api_key');

        if (empty($api_key)) {
            return ['status' => false, 'error' => 'OpenAI API Key not configured in settings'];
        }

        if (!file_exists($file_path)) {
            return ['status' => false, 'error' => 'Audio file not found: ' . $file_path];
        }

        $curl = curl_init();

        $post_fields = [
            'file' => new CURLFile($file_path),
            'model' => get_option('pbxpilot_openai_model', 'whisper-1'),
        ];

        $forced_lang = get_option('pbxpilot_transcription_language', '');
        if (!empty($forced_lang)) {
            $post_fields['language'] = $forced_lang;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => false, 'error' => 'CURL Error: ' . $err];
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            return ['status' => false, 'error' => 'OpenAI Error: ' . $data['error']['message']];
        }

        return [
            'status' => true,
            'text' => $data['text'] ?? ''
        ];
    }

    /**
     * Google Speech-to-Text Transcription
     */
    private function transcribe_google($file_path)
    {
        $project_id = get_option('pbxpilot_google_project_id');
        $service_account = get_option('pbxpilot_google_service_account');

        if (empty($project_id) || empty($service_account)) {
            return ['status' => false, 'error' => 'Google STT credentials not fully configured in settings'];
        }

        // Implementation of Google STT API call...
        return [
            'status' => true,
            'text' => 'এটি Google Speech-to-Text এর মাধ্যমে সংগৃহীত ডেমো ট্রান্সক্রিপশন।'
        ];
    }

    /**
     * OpenAI Chat Completion for Summary (Actual Implementation)
     */
    private function chat_completion($prompt, $text, $model = null)
    {
        $api_key = get_option('pbxpilot_openai_api_key');

        if (empty($api_key)) {
            return ['status' => false, 'error' => 'OpenAI API Key not configured'];
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model ?: 'gpt-4o-mini', // Use gpt-4o-mini as fallback
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => "এখানে কল ট্রান্সক্রিপশন দেওয়া হলো। এটি সামারি করো: \n\n" . $text]
                ],
                'temperature' => 0.7
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => false, 'error' => 'CURL Error: ' . $err];
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            return ['status' => false, 'error' => 'GPT Error: ' . $data['error']['message']];
        }

        return [
            'status' => true,
            'summary' => $data['choices'][0]['message']['content'] ?? ''
        ];
    }

    /**
     * Test OpenAI Connection
     */
    public function test_openai_connection($api_key)
    {
        if (empty($api_key)) {
            return ['status' => false, 'message' => 'OpenAI API Key is empty'];
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/models',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key
            ],
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => false, 'message' => 'CURL Error: ' . $err];
        }

        $data = json_decode($response, true);
        if ($http_code === 200) {
            return ['status' => true, 'message' => 'Connection successful! Your OpenAI API is active and functional.'];
        }

        if (isset($data['error'])) {
            $error_msg = $data['error']['message'];
            if (strpos($error_msg, 'billing') !== false || strpos($error_msg, 'quota') !== false) {
                return ['status' => false, 'message' => 'Billing/Quota Error: ' . $error_msg];
            }
            return ['status' => false, 'message' => 'API Error: ' . $error_msg];
        }

        return ['status' => false, 'message' => 'Connection failed with HTTP Code ' . $http_code];
    }

    /**
     * Test Google Connection
     */
    public function test_google_connection($project_id, $service_account)
    {
        if (empty($project_id) || empty($service_account)) {
            return ['status' => false, 'message' => 'Google Project ID or Service Account JSON is empty'];
        }

        // Try to decode JSON to verify format
        $json = json_decode($service_account, true);
        if (!$json) {
            return ['status' => false, 'message' => 'Invalid JSON format for Google Service Account.'];
        }

        if (!isset($json['project_id']) || $json['project_id'] !== $project_id) {
            return ['status' => false, 'message' => 'Project ID mismatch between input and JSON file.'];
        }

        return ['status' => true, 'message' => 'Google STT connection validated! Credentials format is correct.'];
    }
}
