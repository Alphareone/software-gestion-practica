<?php

class InfoController extends Controller {

    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . URLROOT . '/auth');
            exit;
        }

        $data = [
            'version' => BRAND_VERSION,
            'company' => BRAND_COMPANY,
            'location' => BRAND_LOCATION,
            'year' => BRAND_YEAR,
        ];

        $this->view('info/index', $data);
    }
}
