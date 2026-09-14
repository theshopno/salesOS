<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Agents extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(PBXPILOT_MODULE_NAME . '/agents_model');

        if (!staff_can('manage', PBXPILOT_MODULE_NAME)) {
            access_denied('PBX Pilot Agents');
        }
    }

    public function index()
    {
        if ($this->input->post()) {
            $this->_save();
            redirect(admin_url('pbxpilot/agents'));
        }

        $data['title']    = _l('pbxpilot_agents_title');
        $data['agents']   = $this->agents_model->get_all();
        $data['staff']    = $this->_unmapped_staff();

        $this->load->view('pbxpilot/agents/manage', $data);
    }

    public function delete($id)
    {
        $this->agents_model->delete((int) $id);
        set_alert('success', _l('pbxpilot_agent_removed'));
        redirect(admin_url('pbxpilot/agents'));
    }

    private function _save(): void
    {
        $staff_id  = (int) $this->input->post('staff_id');
        $extension = trim((string) $this->input->post('extension'));

        if ($staff_id <= 0 || $extension === '') {
            set_alert('warning', _l('pbxpilot_agents_required'));
            return;
        }

        if (!ctype_digit($extension)) {
            set_alert('warning', _l('pbxpilot_agents_extension_numeric'));
            return;
        }

        $this->agents_model->save($staff_id, $extension);
        set_alert('success', _l('pbxpilot_agent_saved'));
    }

    /** Staff members who don't already have an extension mapped. */
    private function _unmapped_staff(): array
    {
        $mapped = array_column($this->agents_model->get_all(), 'staff_id');

        $this->db->select('staffid, firstname, lastname, email')
            ->where('active', 1);
        if (!empty($mapped)) {
            $this->db->where_not_in('staffid', $mapped);
        }

        return $this->db->get(db_prefix() . 'staff')->result_array();
    }
}
