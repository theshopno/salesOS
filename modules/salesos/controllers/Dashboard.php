<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(SALESOS_MODULE_NAME . '/salesos_model');
        $this->load->helper(SALESOS_MODULE_NAME . '/' . SALESOS_MODULE_NAME);

        if (!staff_can('view', SALESOS_MODULE_NAME)) {
            access_denied('SalesOS Dashboard');
        }
    }

    public function index()
    {
        $date_from = $this->input->get('date_from') ?: date('Y-m-d');
        $date_to   = $this->input->get('date_to') ?: date('Y-m-d');

        $effective_seconds = (int) salesos_get_option('salesos_effective_call_seconds', '120');

        $data['title']             = _l('salesos_dashboard_title');
        $data['date_from']         = $date_from;
        $data['date_to']          = $date_to;
        $data['effective_seconds'] = $effective_seconds;
        $data['stats']             = $this->salesos_model->get_dashboard_stats($date_from, $date_to, $effective_seconds);
        $data['agent_breakdown']   = $this->salesos_model->get_agent_breakdown($date_from, $date_to, $effective_seconds);
        $data['presets']           = $this->_date_presets();
        $data['active_preset']     = $this->_active_preset($date_from, $date_to, $data['presets']);

        $this->load->view('salesos/dashboard', $data);
    }

    /** Quick date-range shortcuts for the filter bar — Sat is the configured
     *  start of the business week here (matches the PBX dialplan's own
     *  "sat-thu" office-hours schedule), not a hardcoded Monday assumption. */
    private function _date_presets(): array
    {
        $today          = date('Y-m-d');
        $yesterday      = date('Y-m-d', strtotime('-1 day'));
        $week_start_dow = 6; // Saturday (date('w') Sunday=0..Saturday=6)
        $days_since_sat = (((int) date('w') - $week_start_dow) + 7) % 7;
        $week_start     = date('Y-m-d', strtotime("-{$days_since_sat} days"));
        $month_start    = date('Y-m-01');

        return [
            'today'     => ['label' => _l('salesos_today'), 'from' => $today, 'to' => $today],
            'yesterday' => ['label' => _l('salesos_yesterday'), 'from' => $yesterday, 'to' => $yesterday],
            'week'      => ['label' => _l('salesos_this_week'), 'from' => $week_start, 'to' => $today],
            'month'     => ['label' => _l('salesos_this_month'), 'from' => $month_start, 'to' => $today],
        ];
    }

    private function _active_preset(string $date_from, string $date_to, array $presets): ?string
    {
        foreach ($presets as $key => $p) {
            if ($p['from'] === $date_from && $p['to'] === $date_to) {
                return $key;
            }
        }

        return null;
    }
}
