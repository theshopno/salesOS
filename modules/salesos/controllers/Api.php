<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(SALESOS_MODULE_NAME . '/salesos_model');

        if (!staff_can('view', SALESOS_MODULE_NAME)) {
            access_denied('SalesOS API');
        }
    }

    /** POST — originate a click-to-call from the logged-in agent's extension. */
    public function click_to_call()
    {
        if (!$this->input->is_ajax_request() || !staff_can('make', SALESOS_MODULE_NAME)) {
            show_404();
        }

        $number = trim((string) $this->input->post('number'));
        if ($number === '') {
            echo json_encode(['success' => false, 'error' => 'Missing number']);
            return;
        }

        $this->load->library(SALESOS_MODULE_NAME . '/Ami_service');
        $result = $this->ami_service->originate($number, get_staff_user_id());

        echo json_encode($result);
    }

    /** POST — save disposition/notes on a call (wrap-up). */
    public function wrapup()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $call_id     = (int) $this->input->post('call_id');
        $disposition = trim((string) $this->input->post('disposition_code'));
        $notes       = trim((string) $this->input->post('notes'));

        if ($call_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Missing call_id']);
            return;
        }

        $ok = $this->salesos_model->save_wrapup($call_id, $disposition, $notes);
        echo json_encode(['success' => $ok]);
    }
}
