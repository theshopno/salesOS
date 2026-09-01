<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Agents extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('salesos/agents_model');
        if (!staff_can('manage', 'salesos')) {
            access_denied('SalesOS Agents');
        }
    }

    public function index()
    {
        $data['title']  = 'SalesOS Agents';
        $data['agents'] = $this->agents_model->get_all();

        // Staff without an agent mapping
        $this->load->model('staff_model');
        $all_staff    = $this->staff_model->get('', ['active' => 1]);
        $mapped_ids   = array_column($data['agents'], 'staff_id');
        $data['unmapped_staff'] = array_filter($all_staff, fn($s) => !in_array($s['staffid'], $mapped_ids));

        // PJSIP endpoint list from AMI + live status for agents
        $this->load->library('salesos/ami_service');
        $data['pjsip_endpoints'] = [];
        if ($this->ami_service->connect()) {
            $endpoints = $this->ami_service->get_endpoint_list();
            $this->ami_service->disconnect();
            $data['pjsip_endpoints'] = $endpoints;

            // Map extension → PJSIP state (Not in use / In use / Unavailable)
            $ep_state = [];
            foreach ($endpoints as $ep) {
                $name = $ep['ObjectName'] ?? $ep['Endpoint'] ?? '';
                $name = strtok($name, '/');   // strip "/CallerID" suffix
                $ep_state[$name] = $ep['DeviceState'] ?? $ep['State'] ?? 'Unavailable';
            }

            // Overlay live status onto agents (is_logged_in = has registered contact)
            foreach ($data['agents'] as &$agent) {
                $state = $ep_state[$agent['extension']] ?? 'Unavailable';
                $agent['is_logged_in'] = ($state !== 'Unavailable') ? 1 : 0;
            }
            unset($agent);
        }

        $this->load->view('salesos/agents/manage', $data);
    }

    public function create()
    {
        $this->_check_ajax();
        $this->load->library('form_validation');

        $rules = [
            ['field' => 'staff_id',  'label' => 'Staff Member', 'rules' => 'required|is_natural_no_zero'],
            ['field' => 'extension', 'label' => 'Extension',    'rules' => 'required|max_length[20]'],
        ];
        $this->form_validation->set_rules($rules);

        if (!$this->form_validation->run()) {
            echo json_encode(['success' => false, 'error' => validation_errors()]);
            return;
        }

        $staff_id  = (int) $this->input->post('staff_id');
        $extension = trim($this->input->post('extension'));

        if ($this->agents_model->extension_exists($extension)) {
            echo json_encode(['success' => false, 'error' => 'Extension already mapped.']);
            return;
        }
        if ($this->agents_model->get_by_staff_id($staff_id)) {
            echo json_encode(['success' => false, 'error' => 'Staff member already has an extension.']);
            return;
        }

        $this->load->model('staff_model');
        $staff = $this->staff_model->get($staff_id);

        $id = $this->agents_model->create([
            'staff_id'  => $staff_id,
            'extension' => $extension,
            'fullname'  => $staff ? $staff->firstname . ' ' . $staff->lastname : '',
            'is_active' => 1,
        ]);

        echo json_encode(['success' => (bool) $id, 'id' => $id]);
    }

    public function update($id)
    {
        $this->_check_ajax();

        $extension = trim($this->input->post('extension'));
        $is_active = (int) $this->input->post('is_active');

        if ($this->agents_model->extension_exists($extension, (int) $id)) {
            echo json_encode(['success' => false, 'error' => 'Extension already in use.']);
            return;
        }

        $ok = $this->agents_model->update((int) $id, [
            'extension' => $extension,
            'is_active' => $is_active,
        ]);

        echo json_encode(['success' => $ok]);
    }

    public function set_sip_password($id)
    {
        $this->_check_ajax();

        $password = $this->input->post('sip_password');
        if ($password === null) {
            echo json_encode(['success' => false, 'error' => 'Password required']);
            return;
        }

        $encrypted = $password !== '' ? salesos_encrypt_sip_password($password) : null;
        $ok = $this->agents_model->update((int) $id, ['sip_password' => $encrypted]);
        echo json_encode(['success' => $ok]);
    }

    public function delete($id)
    {
        $this->_check_ajax();
        $ok = $this->agents_model->delete((int) $id);
        echo json_encode(['success' => $ok]);
    }

    private function _check_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
    }
}
