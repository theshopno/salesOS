<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Calls extends AdminController
{
    private const PER_PAGE = 25;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(PBXPILOT_MODULE_NAME . '/pbxpilot_model');
        $this->load->helper(PBXPILOT_MODULE_NAME . '/' . PBXPILOT_MODULE_NAME);

        if (!staff_can('view', PBXPILOT_MODULE_NAME)) {
            access_denied('PBX Pilot Calls');
        }
    }

    public function index()
    {
        $page = max(1, (int) $this->input->get('page'));

        $filters = [
            'date_from'         => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-6 days')),
            'date_to'           => $this->input->get('date_to') ?: date('Y-m-d'),
            'agent_id'          => (int) $this->input->get('agent_id'),
            'direction'         => $this->input->get('direction'),
            'disposition'       => $this->input->get('disposition'),
            'effective_only'     => (bool) $this->input->get('effective_only'),
            'no_lead_note_only'  => (bool) $this->input->get('no_lead_note_only'),
            'search'            => $this->input->get('search'),
            'effective_seconds' => (int) pbxpilot_get_option('pbxpilot_effective_call_seconds', '120'),
        ];

        $result = $this->pbxpilot_model->get_calls_list($filters, self::PER_PAGE, (self::PER_PAGE * ($page - 1)));

        $data['title']       = _l('pbxpilot_calls_title');
        $data['filters']     = $filters;
        $data['calls']       = $result['rows'];
        $data['total']       = $result['total'];
        $data['page']        = $page;
        $data['per_page']    = self::PER_PAGE;
        $data['total_pages'] = (int) ceil($result['total'] / self::PER_PAGE);
        $data['agents']      = $this->_agents_for_filter();
        $data['dispositions'] = $this->pbxpilot_model->get_known_dispositions();

        $this->load->view('pbxpilot/calls/list', $data);
    }

    private function _agents_for_filter(): array
    {
        return $this->db->select('a.staff_id, CONCAT(s.firstname, " ", s.lastname) as name')
            ->from(db_prefix() . 'pbxpilot_agents a')
            ->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left')
            ->where('a.is_active', 1)
            ->order_by('name', 'asc')
            ->get()->result_array();
    }
}
