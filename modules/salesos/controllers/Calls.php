<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Calls extends AdminController
{
    private const PER_PAGE = 25;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(SALESOS_MODULE_NAME . '/salesos_model');
        $this->load->helper(SALESOS_MODULE_NAME . '/' . SALESOS_MODULE_NAME);

        if (!staff_can('view', SALESOS_MODULE_NAME)) {
            access_denied('SalesOS Calls');
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
            'effective_seconds' => (int) salesos_get_option('salesos_effective_call_seconds', '120'),
        ];

        $result = $this->salesos_model->get_calls_list($filters, self::PER_PAGE, (self::PER_PAGE * ($page - 1)));

        $data['title']       = _l('salesos_calls_title');
        $data['filters']     = $filters;
        $data['calls']       = $result['rows'];
        $data['total']       = $result['total'];
        $data['page']        = $page;
        $data['per_page']    = self::PER_PAGE;
        $data['total_pages'] = (int) ceil($result['total'] / self::PER_PAGE);
        $data['agents']      = $this->_agents_for_filter();
        $data['dispositions'] = $this->salesos_model->get_known_dispositions();

        $this->load->view('salesos/calls/list', $data);
    }

    private function _agents_for_filter(): array
    {
        return $this->db->select('a.staff_id, CONCAT(s.firstname, " ", s.lastname) as name')
            ->from(db_prefix() . 'salesos_agents a')
            ->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left')
            ->where('a.is_active', 1)
            ->order_by('name', 'asc')
            ->get()->result_array();
    }
}
