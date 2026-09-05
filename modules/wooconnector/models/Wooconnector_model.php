<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wooconnector_model extends App_Model
{
    private $custom_field_ids = [];

    const LOST_STATUSES = ['cancelled', 'refunded', 'failed'];

    public function __construct()
    {
        parent::__construct();
    }

    // ── Sites ─────────────────────────────────────────────────────────────────

    public function get_sites()
    {
        return $this->db->get(db_prefix() . 'wooconnector_sites')->result_array();
    }

    public function get_site($id)
    {
        $this->db->where('id', $id);

        return $this->db->get(db_prefix() . 'wooconnector_sites')->row_array();
    }

    public function save_site($data, $id = null)
    {
        $fields = [
            'name'                   => $data['name'],
            'site_url'               => rtrim($data['site_url'], '/'),
            'consumer_key'           => $data['consumer_key'],
            'consumer_secret'        => $data['consumer_secret'],
            'default_lead_status_id' => (int) $data['default_lead_status_id'],
        ];

        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'wooconnector_sites', $fields);

            return $id;
        }

        $this->db->insert(db_prefix() . 'wooconnector_sites', $fields);

        return $this->db->insert_id();
    }

    public function get_lead_statuses()
    {
        $this->db->order_by('statusorder', 'asc');

        return $this->db->get(db_prefix() . 'leads_status')->result_array();
    }

    // ── Website Orders queue ────────────────────────────────────────────────────

    /**
     * Statuses telesales may pick from the Website Orders queue. "Customer" is
     * deliberately excluded — selecting it would only relabel the lead without
     * actually creating a client, bypassing confirm_lead_as_customer() entirely.
     */
    public function get_selectable_statuses()
    {
        $this->db->where('name !=', 'Customer');
        $this->db->order_by('statusorder', 'asc');

        return $this->db->get(db_prefix() . 'leads_status')->result_array();
    }

    public function get_pending_orders()
    {
        $cancelled_id = (int) get_option('wooconnector_cancelled_status_id');

        $this->db->select('l.id, l.name, l.phonenumber, l.email, l.status, s.name as status_name, o.wc_order_id, o.order_date');
        $this->db->from(db_prefix() . 'wooconnector_orders o');
        $this->db->join(db_prefix() . 'leads l', 'l.id = o.lead_id', 'inner');
        $this->db->join(db_prefix() . 'leads_status s', 's.id = l.status', 'left');
        $this->db->where('l.date_converted IS NULL');
        $this->db->where('l.lost', 0);
        $this->db->where('l.junk', 0);
        if ($cancelled_id) {
            $this->db->where('l.status !=', $cancelled_id);
        }
        $this->db->order_by('o.order_date', 'asc');
        $leads = $this->db->get()->result_array();

        if (empty($leads)) {
            return [];
        }

        $lead_ids = array_column($leads, 'id');
        $this->db->select('cfv.relid, cf.slug, cfv.value');
        $this->db->from(db_prefix() . 'customfieldsvalues cfv');
        $this->db->join(db_prefix() . 'customfields cf', 'cf.id = cfv.fieldid');
        $this->db->where_in('cfv.relid', $lead_ids);
        $this->db->where('cfv.fieldto', 'leads');
        $this->db->like('cf.slug', 'wooconnector_', 'after');
        $cf_rows = $this->db->get()->result_array();

        $custom_by_lead = [];
        foreach ($cf_rows as $row) {
            $custom_by_lead[$row['relid']][$row['slug']] = $row['value'];
        }

        foreach ($leads as &$lead) {
            $lead['custom'] = $custom_by_lead[$lead['id']] ?? [];
        }

        return $leads;
    }

    /**
     * Replicates Leads.php::convert_to_customer()'s real side effects (build client + contact,
     * mark the lead converted, fire the same hook) without depending on that $_POST-bound
     * controller method. Idempotent — safe to call again if a previous attempt failed.
     */
    public function confirm_lead_as_customer($lead_id)
    {
        $this->db->where('id', $lead_id);
        $lead = $this->db->get(db_prefix() . 'leads')->row();

        if (!$lead || !empty($lead->date_converted)) {
            return false;
        }

        try {
            $name_parts = preg_split('/\s+/', trim($lead->name), 2);
            $firstname  = $name_parts[0] !== '' ? $name_parts[0] : 'Customer';
            $lastname   = $name_parts[1] ?? '';

            $email = $lead->email;
            if (empty($email)) {
                $email = preg_replace('/\D+/', '', (string) $lead->phonenumber) . '@no-email.invalid';
            }

            $client_data = [
                'company'               => $lead->company ?: $lead->name,
                'address'               => $lead->address,
                'city'                  => $lead->city,
                'state'                 => $lead->state,
                'zip'                   => $lead->zip,
                'country'               => $lead->country,
                'phonenumber'           => $lead->phonenumber,
                'firstname'             => $firstname,
                'lastname'              => $lastname,
                'email'                 => $email,
                'donotsendwelcomeemail' => true,
            ];

            $this->load->model('clients_model');
            $client_id = $this->clients_model->add($client_data, true);

            if (!$client_id) {
                log_activity('WooConnector: failed to create client for lead #' . $lead_id . ' during confirmation');

                return false;
            }

            $default_status = $this->db->where('isdefault', 1)->get(db_prefix() . 'leads_status')->row();

            $this->db->where('id', $lead_id);
            $this->db->update(db_prefix() . 'leads', [
                'date_converted' => date('Y-m-d H:i:s'),
                'status'         => $default_status ? $default_status->id : $lead->status,
                'junk'           => 0,
                'lost'           => 0,
            ]);

            hooks()->do_action('lead_converted_to_customer', ['lead_id' => $lead_id, 'customer_id' => $client_id]);

            return $client_id;
        } catch (Exception $e) {
            log_activity('WooConnector: exception converting lead #' . $lead_id . ' — ' . $e->getMessage());

            return false;
        }
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function get_stats($date_from, $date_to)
    {
        $base = function ($extra_where = '') use ($date_from, $date_to) {
            $this->db->where('order_date >=', $date_from . ' 00:00:00');
            $this->db->where('order_date <=', $date_to . ' 23:59:59');
            if ($extra_where) {
                $this->db->where($extra_where);
            }

            return (int) $this->db->count_all_results(db_prefix() . 'wooconnector_orders');
        };

        $converted = function () use ($date_from, $date_to) {
            // Table (with its alias) must be registered via from() before any where()/join() call
            // references that alias, otherwise CI's identifier protection mis-prefixes "o" itself
            // (producing "tblo") instead of recognizing it as the wooconnector_orders alias.
            $this->db->from(db_prefix() . 'wooconnector_orders o');
            $this->db->join(db_prefix() . 'leads l', 'l.id = o.lead_id', 'inner');
            $this->db->where('o.order_date >=', $date_from . ' 00:00:00');
            $this->db->where('o.order_date <=', $date_to . ' 23:59:59');
            $this->db->where('l.date_converted IS NOT NULL');

            return (int) $this->db->count_all_results();
        };

        $total    = $base();
        $new_lead = $base('lead_id IS NOT NULL AND matched_client_id IS NULL');

        return [
            'total_orders'    => $total,
            'new_leads'       => $new_lead,
            'existing_client' => $base('matched_client_id IS NOT NULL'),
            'lost_cancelled'  => $base("wc_status IN ('cancelled','refunded','failed')"),
            'converted'       => $converted(),
        ];
    }

    public function get_daily_breakdown($date_from, $date_to)
    {
        $this->db->select('DATE(order_date) as day, COUNT(*) as orders');
        $this->db->where('order_date >=', $date_from . ' 00:00:00');
        $this->db->where('order_date <=', $date_to . ' 23:59:59');
        $this->db->group_by('DATE(order_date)');
        $this->db->order_by('day', 'desc');

        return $this->db->get(db_prefix() . 'wooconnector_orders')->result_array();
    }

    // ── Sync ──────────────────────────────────────────────────────────────────

    public function sync()
    {
        $totals = $this->empty_counts();

        foreach ($this->get_sites() as $site) {
            $counts = $this->sync_site($site);
            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }
        }

        return $totals;
    }

    public function sync_site($site)
    {
        $counts = $this->empty_counts();
        $page   = 1;

        do {
            $orders = $this->fetch_orders($site, $page);

            if ($orders === false) {
                throw new Exception('Failed to fetch orders from ' . $site['site_url'] . ' — check the WooCommerce REST API credentials.');
            }

            foreach ($orders as $order) {
                try {
                    $result = $this->handle_order($site, $order);
                    $counts[$result]++;
                } catch (Exception $e) {
                    $counts['failed']++;
                    log_activity('WooConnector: failed to import order #' . ($order['id'] ?? '?') . ' — ' . $e->getMessage());
                }
            }

            $page++;
        } while (count($orders) >= 100);

        $this->db->where('id', $site['id']);
        $this->db->update(db_prefix() . 'wooconnector_sites', [
            'last_synced_at' => date('Y-m-d H:i:s'),
        ]);

        return $counts;
    }

    private function empty_counts()
    {
        return [
            'new_lead'        => 0,
            'existing_client' => 0,
            'existing_lead'   => 0,
            'status_updated'  => 0,
            'unchanged'       => 0,
            'failed'          => 0,
        ];
    }

    private function fetch_orders($site, $page = 1)
    {
        $url = $site['site_url'] . '/wp-json/wc/v3/orders?' . http_build_query(array_filter([
            'per_page'       => 100,
            'page'           => $page,
            'orderby'        => 'date',
            'order'          => 'asc',
            'modified_after' => !empty($site['last_synced_at']) ? date('c', strtotime($site['last_synced_at'])) : null,
        ]));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $site['consumer_key'] . ':' . $site['consumer_secret'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $http_code !== 200 || $curl_error) {
            log_activity('WooConnector: failed to fetch orders from ' . $site['site_url'] . ' (HTTP ' . $http_code . ') ' . $curl_error);

            return false;
        }

        $orders = json_decode($response, true);

        return is_array($orders) ? $orders : [];
    }

    /**
     * @return string one of: new_lead, existing_client, existing_lead, status_updated, unchanged
     */
    private function handle_order($site, $order)
    {
        $this->db->where('site_id', $site['id']);
        $this->db->where('wc_order_id', $order['id']);
        $existing = $this->db->get(db_prefix() . 'wooconnector_orders')->row();

        if ($existing) {
            return $this->sync_order_status($existing, $order);
        }

        return $this->import_new_order($site, $order);
    }

    private function sync_order_status($existing, $order)
    {
        $new_status = $order['status'] ?? '';

        if ($new_status === $existing->wc_status) {
            return 'unchanged';
        }

        $this->db->where('id', $existing->id);
        $this->db->update(db_prefix() . 'wooconnector_orders', ['wc_status' => $new_status]);

        if (!$existing->lead_id || !in_array($new_status, self::LOST_STATUSES, true)) {
            return 'status_updated';
        }

        $this->db->where('id', $existing->lead_id);
        $lead = $this->db->get(db_prefix() . 'leads')->row();

        // Already converted to a customer — a later refund on this one order shouldn't undo that relationship.
        if ($lead && empty($lead->date_converted) && !$lead->lost && !$lead->junk) {
            $this->load->model('leads_model');
            $this->leads_model->mark_as_lost($existing->lead_id);
        }

        return 'status_updated';
    }

    private function import_new_order($site, $order)
    {
        $billing  = $order['billing'] ?? [];
        $shipping = $order['shipping'] ?? [];
        $phone    = $this->normalize_phone($billing['phone'] ?? '');

        $matched_client_id = $phone ? $this->find_client_by_phone($phone) : null;

        if ($matched_client_id) {
            $this->db->insert(db_prefix() . 'wooconnector_orders', [
                'site_id'           => $site['id'],
                'wc_order_id'       => $order['id'],
                'lead_id'           => null,
                'matched_client_id' => $matched_client_id,
                'wc_status'         => $order['status'] ?? '',
                'order_date'        => $this->to_sql_datetime($order['date_created'] ?? null),
            ]);
            log_activity('WooConnector: order #' . ($order['number'] ?? $order['id']) . ' matched existing customer [Client ID: ' . $matched_client_id . ']');

            return 'existing_client';
        }

        $existing_lead_id = $phone ? $this->find_open_lead_by_phone($phone) : null;

        if ($existing_lead_id) {
            $this->load->model('leads_model');
            $this->leads_model->log_lead_activity(
                $existing_lead_id,
                'New WooCommerce order #' . ($order['number'] ?? $order['id']) . ' received (' . ($order['total'] ?? '0') . ' ' . ($order['currency'] ?? '') . ')'
            );

            $this->db->insert(db_prefix() . 'wooconnector_orders', [
                'site_id'     => $site['id'],
                'wc_order_id' => $order['id'],
                'lead_id'     => $existing_lead_id,
                'wc_status'   => $order['status'] ?? '',
                'order_date'  => $this->to_sql_datetime($order['date_created'] ?? null),
            ]);

            return 'existing_lead';
        }

        $lead_id = $this->create_lead($site, $order, $billing, $shipping);

        $this->db->insert(db_prefix() . 'wooconnector_orders', [
            'site_id'     => $site['id'],
            'wc_order_id' => $order['id'],
            'lead_id'     => $lead_id,
            'wc_status'   => $order['status'] ?? '',
            'order_date'  => $this->to_sql_datetime($order['date_created'] ?? null),
        ]);

        return 'new_lead';
    }

    private function create_lead($site, $order, $billing, $shipping)
    {
        $name = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        if ($name === '') {
            $name = trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? ''));
        }
        if ($name === '') {
            $name = 'WooCommerce Order #' . ($order['number'] ?? $order['id']);
        }

        $address_source = !empty($billing['address_1']) ? $billing : $shipping;

        $lead_data = [
            'name'        => $name,
            'email'       => $billing['email'] ?? '',
            'phonenumber' => $billing['phone'] ?? '',
            'address'     => trim(($address_source['address_1'] ?? '') . ' ' . ($address_source['address_2'] ?? '')),
            'city'        => $address_source['city'] ?? '',
            'state'       => $address_source['state'] ?? '',
            'zip'         => $address_source['postcode'] ?? '',
            'country'     => $this->resolve_country_id($address_source['country'] ?? ''),
            'description' => 'Imported from WooCommerce order #' . ($order['number'] ?? $order['id']) . ' (' . ($order['total'] ?? '0') . ' ' . ($order['currency'] ?? '') . ')',
            'status'      => (int) $site['default_lead_status_id'],
            'source'      => $this->get_or_create_source_id(),
            'addedfrom'   => 0,
            'custom_fields' => [
                'leads' => [
                    $this->get_custom_field_id('wooconnector_order_id')        => (string) ($order['number'] ?? $order['id']),
                    $this->get_custom_field_id('wooconnector_items')          => $this->format_line_items($order['line_items'] ?? []),
                    $this->get_custom_field_id('wooconnector_shipping_charge') => (string) ($order['shipping_total'] ?? '0'),
                    $this->get_custom_field_id('wooconnector_total_amount')   => (string) ($order['total'] ?? '0'),
                    $this->get_custom_field_id('wooconnector_order_note')     => (string) ($order['customer_note'] ?? ''),
                    $this->get_custom_field_id('wooconnector_payment_method') => (string) ($order['payment_method_title'] ?? ''),
                ],
            ],
        ];

        $this->load->model('leads_model');

        return $this->leads_model->add($lead_data);
    }

    // ── Repeat-customer / duplicate-lead matching ────────────────────────────

    private function normalize_phone($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' ? substr($digits, -10) : '';
    }

    private function phone_suffix_sql($column)
    {
        return "RIGHT(REPLACE(REPLACE(REPLACE({$column}, '+', ''), ' ', ''), '-', ''), 10)";
    }

    private function find_client_by_phone($phone_suffix)
    {
        $sql = 'SELECT userid FROM ' . db_prefix() . 'contacts WHERE ' . $this->phone_suffix_sql('phonenumber') . ' = ? LIMIT 1';
        $row = $this->db->query($sql, [$phone_suffix])->row();

        return $row ? (int) $row->userid : null;
    }

    private function find_open_lead_by_phone($phone_suffix)
    {
        $sql = 'SELECT id FROM ' . db_prefix() . 'leads WHERE lost = 0 AND junk = 0 AND ' . $this->phone_suffix_sql('phonenumber') . ' = ? LIMIT 1';
        $row = $this->db->query($sql, [$phone_suffix])->row();

        return $row ? (int) $row->id : null;
    }

    // ── Small helpers ─────────────────────────────────────────────────────────

    private function to_sql_datetime($iso_date)
    {
        return $iso_date ? date('Y-m-d H:i:s', strtotime($iso_date)) : null;
    }

    private function format_line_items($line_items)
    {
        $lines = [];
        foreach ($line_items as $item) {
            $lines[] = ($item['name'] ?? 'Item') . ' x' . ($item['quantity'] ?? 1);
        }

        return implode("\n", $lines);
    }

    private function get_or_create_source_id()
    {
        $this->db->where('name', 'WooCommerce');
        $source = $this->db->get(db_prefix() . 'leads_sources')->row();

        if ($source) {
            return $source->id;
        }

        $this->db->insert(db_prefix() . 'leads_sources', ['name' => 'WooCommerce']);

        return $this->db->insert_id();
    }

    private function resolve_country_id($iso2)
    {
        if (!empty($iso2)) {
            $this->db->where('iso2', $iso2);
            $row = $this->db->get(db_prefix() . 'countries')->row();
            if ($row) {
                return $row->country_id;
            }
        }

        $this->db->where('iso2', 'BD');
        $row = $this->db->get(db_prefix() . 'countries')->row();

        return $row ? $row->country_id : 0;
    }

    private function get_custom_field_id($slug)
    {
        if (isset($this->custom_field_ids[$slug])) {
            return $this->custom_field_ids[$slug];
        }

        $this->db->where('slug', $slug);
        $this->db->where('fieldto', 'leads');
        $field = $this->db->get(db_prefix() . 'customfields')->row();

        $this->custom_field_ids[$slug] = $field ? $field->id : 0;

        return $this->custom_field_ids[$slug];
    }
}
