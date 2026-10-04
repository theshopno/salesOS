<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Agents_model extends App_Model
{
    public function get_by_staff_id(int $staff_id): ?array
    {
        $row = $this->db->where('staff_id', $staff_id)
            ->where('is_active', 1)
            ->get(db_prefix() . 'pbxpilot_agents')
            ->row_array();

        return $row ?: null;
    }

    public function get_all(): array
    {
        return $this->db->select('a.*, CONCAT(s.firstname, " ", s.lastname) as staff_name')
            ->from(db_prefix() . 'pbxpilot_agents a')
            ->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left')
            ->order_by('a.is_active', 'desc')
            ->get()->result_array();
    }

    public function save(int $staff_id, string $extension, int $id = 0): bool
    {
        return $this->save_unified($staff_id, ['extension' => $extension], $id);
    }

    /**
     * Get all active staff members combined with their PBX Extension,
     * Bizbot WhatsApp GUID, and Intro Aliases.
     *
     * @return array<int, array>
     */
    public function get_unified_roster(): array
    {
        // 1. Fetch all active CRM staff
        $staff_list = $this->db->select('staffid, firstname, lastname, email, phonenumber, profile_image, admin')
            ->where('active', 1)
            ->order_by('firstname', 'asc')
            ->get(db_prefix() . 'staff')
            ->result_array();

        // 2. Fetch all mapped agents
        $agent_rows = $this->db->get(db_prefix() . 'pbxpilot_agents')->result_array();
        $agents_by_staff = [];
        foreach ($agent_rows as $a) {
            $agents_by_staff[(int) $a['staff_id']] = $a;
        }

        // 3. Fallback: check SalesOS options for any unmigrated Bizbot data
        $opt_mapping = json_decode(get_option('salesos_bizbot_agent_mapping') ?: '[]', true) ?: [];
        $staff_to_guid = array_flip($opt_mapping); // [staff_id => guid]
        $opt_aliases = json_decode(get_option('salesos_bizbot_staff_aliases') ?: '[]', true) ?: [];

        $roster = [];
        foreach ($staff_list as $stf) {
            $sid = (int) $stf['staffid'];
            $agent = $agents_by_staff[$sid] ?? null;

            $extension = $agent['extension'] ?? '';
            $guid      = $agent['bizbot_agent_guid'] ?? ($staff_to_guid[$sid] ?? '');
            $aliases   = $agent['whatsapp_aliases'] ?? '';
            if (empty($aliases) && isset($opt_aliases[$sid])) {
                $aliases = is_array($opt_aliases[$sid]) ? implode(', ', $opt_aliases[$sid]) : (string) $opt_aliases[$sid];
            }
            $caller_id = $agent['caller_id'] ?? '';
            $is_active = $agent ? (int) $agent['is_active'] : 1;

            $roster[] = [
                'staff_id'          => $sid,
                'staff_name'        => trim($stf['firstname'] . ' ' . $stf['lastname']),
                'firstname'         => $stf['firstname'],
                'lastname'          => $stf['lastname'],
                'email'             => $stf['email'],
                'phonenumber'       => $stf['phonenumber'],
                'profile_image'     => $stf['profile_image'],
                'is_admin'          => (int) $stf['admin'],
                'agent_record_id'   => $agent['id'] ?? 0,
                'extension'         => $extension,
                'bizbot_agent_guid' => $guid,
                'whatsapp_aliases'  => $aliases,
                'caller_id'         => $caller_id,
                'is_active'         => $is_active,
            ];
        }

        return $roster;
    }

    /**
     * Save or update a staff member's unified channel mapping (PBX + WhatsApp)
     * and atomically sync with SalesOS JSON options.
     */
    public function save_unified(int $staff_id, array $data, int $id = 0): bool
    {
        if ($staff_id <= 0) {
            return false;
        }

        $existing = $this->db->where('staff_id', $staff_id)->get(db_prefix() . 'pbxpilot_agents')->row_array();

        $update_data = [
            'staff_id' => $staff_id,
        ];

        if (array_key_exists('extension', $data)) {
            $ext = trim((string) $data['extension']);
            $update_data['extension'] = ($ext !== '' && $ext !== 'none') ? $ext : null;
        }

        if (array_key_exists('bizbot_agent_guid', $data)) {
            $guid = trim((string) $data['bizbot_agent_guid']);
            $update_data['bizbot_agent_guid'] = $guid !== '' ? $guid : null;
        }

        if (array_key_exists('whatsapp_aliases', $data)) {
            $aliases = trim((string) $data['whatsapp_aliases']);
            $update_data['whatsapp_aliases'] = $aliases !== '' ? $aliases : null;
        }

        if (array_key_exists('caller_id', $data)) {
            $cid = trim((string) $data['caller_id']);
            $update_data['caller_id'] = $cid !== '' ? $cid : null;
        }

        if (array_key_exists('is_active', $data)) {
            $update_data['is_active'] = (int) $data['is_active'];
        }

        if ($existing) {
            $ok = (bool) $this->db->where('staff_id', $staff_id)->update(db_prefix() . 'pbxpilot_agents', $update_data);
        } else {
            $ok = (bool) $this->db->insert(db_prefix() . 'pbxpilot_agents', $update_data);
        }

        // Sync with SalesOS options
        $this->sync_salesos_options();

        return $ok;
    }

    /**
     * Compile tblpbxpilot_agents into salesos_bizbot_agent_mapping
     * and salesos_bizbot_staff_aliases options so SalesOS webhook remains 100% compatible.
     */
    public function sync_salesos_options(): void
    {
        $all = $this->db->where('is_active', 1)->get(db_prefix() . 'pbxpilot_agents')->result_array();

        $guid_map = [];
        $alias_map = [];

        foreach ($all as $a) {
            $sid  = (int) $a['staff_id'];
            $guid = trim((string) ($a['bizbot_agent_guid'] ?? ''));
            if ($guid !== '') {
                $guid_map[$guid] = $sid;
            }

            $raw_aliases = trim((string) ($a['whatsapp_aliases'] ?? ''));
            if ($raw_aliases !== '') {
                $split = array_filter(array_map('trim', explode(',', $raw_aliases)));
                if (!empty($split)) {
                    $alias_map[$sid] = array_values(array_unique($split));
                }
            }
        }

        update_option('salesos_bizbot_agent_mapping', json_encode($guid_map));
        update_option('salesos_bizbot_staff_aliases', json_encode($alias_map, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Smart helper to generate Bengali & English intro aliases from a staff member's name.
     */
    public function generate_aliases_for_name(string $firstname, string $lastname = ''): array
    {
        $fn = trim(mb_strtolower($firstname));
        $ln = trim(mb_strtolower($lastname));
        $full = trim($fn . ' ' . $ln);

        $aliases = [$fn];
        if ($full !== '' && $full !== $fn) {
            $aliases[] = $full;
        }

        // Common Bengali transliteration heuristics
        $bengali_map = [
            'mostafizur' => ['মোস্তাফিজুর', 'মোস্তাফিজ', 'mostafiz'],
            'mostafiz'   => ['মোস্তাফিজ', 'mostafizur'],
            'al amin'    => ['আল আমিন', 'আলামিন', 'আল-আমিন', 'alamin', 'amin'],
            'alamin'     => ['আলামিন', 'আল আমিন', 'amin'],
            'amin'       => ['আমিন'],
            'asafunnahar'=> ['আসাফুন্নাহার', 'pakhi', 'পাখি'],
            'pakhi'      => ['পাখি', 'asafunnahar'],
            'rahman'     => ['রহমান'],
            'hasan'      => ['হাসান', 'hassan'],
            'hossain'    => ['হোসেন', 'hossain'],
            'islam'      => ['ইসলাম'],
            'khan'       => ['খান'],
            'ahmed'      => ['আহমেদ'],
        ];

        foreach ([$fn, $ln, $full] as $part) {
            if (isset($bengali_map[$part])) {
                $aliases = array_merge($aliases, $bengali_map[$part]);
            }
        }

        return array_values(array_unique($aliases));
    }

    public function delete(int $id): bool
    {
        $ok = (bool) $this->db->where('id', $id)->delete(db_prefix() . 'pbxpilot_agents');
        $this->sync_salesos_options();
        return $ok;
    }
}
