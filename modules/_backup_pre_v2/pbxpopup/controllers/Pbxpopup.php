<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpopup extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_staff_member()) {
            access_denied('PBX Popup');
        }
    }

    /**
     * GET /admin/pbxpopup/find?phone=<raw caller id>
     * MicroSIP's cmdIncomingCall opens this URL with the caller's number.
     * Redirects straight to the matching lead/customer; falls back to a
     * search-results page when nothing matches confidently.
     */
    public function find()
    {
        $raw    = (string) $this->input->get('phone');
        $digits = preg_replace('/\D/', '', $raw);
        $last10 = substr($digits, -10);

        if (strlen($last10) < 7) {
            redirect(admin_url('leads'));
            return;
        }

        // Self-contained lookup (no dependency on the pbxpilot module -
        // this must keep working whether or not pbxpilot is active).
        $link = $this->_find_contact_link($last10);

        if ($link) {
            redirect($link);
            return;
        }

        // No confident match - show Perfex's own global search results for
        // this number instead of guessing.
        $this->load->model('misc_model');
        $data['phone']   = $raw;
        $data['digits']  = $last10;
        $data['results'] = $this->misc_model->perform_search($last10);
        $data['title']   = 'PBX Popup';
        $this->load->view('results', $data);
    }

    /**
     * Exact match on the last 10 digits (tolerant of stray spaces/dashes
     * and of the +880/880/0 country-code variants). Checks leads, then
     * clients, then client contacts. Returns an admin_url() link or null.
     */
    private function _find_contact_link($last10)
    {
        $norm = "RIGHT(REPLACE(REPLACE(phonenumber,' ',''),'-',''), 10)";

        $lead = $this->db->query(
            'SELECT id FROM ' . db_prefix() . "leads
             WHERE $norm = ?
             ORDER BY dateadded DESC LIMIT 1",
            [$last10]
        )->row();
        if ($lead) {
            return admin_url('leads/index/' . $lead->id);
        }

        $client = $this->db->query(
            'SELECT userid FROM ' . db_prefix() . "clients
             WHERE $norm = ?
             ORDER BY userid DESC LIMIT 1",
            [$last10]
        )->row();
        if ($client) {
            return admin_url('clients/client/' . $client->userid);
        }

        $contact = $this->db->query(
            'SELECT userid FROM ' . db_prefix() . "contacts
             WHERE $norm = ?
             ORDER BY id DESC LIMIT 1",
            [$last10]
        )->row();
        if ($contact) {
            return admin_url('clients/client/' . $contact->userid);
        }

        return null;
    }
}
