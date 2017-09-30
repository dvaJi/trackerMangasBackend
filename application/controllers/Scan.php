<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Scan extends REST_Controller {

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('scans');

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function list_get() {
    $scans = $this->scans->order_by('name', 'ASC')->getAll();

    if ($scans) {
      header('X-TOTAL-ROWS: ' . $this->scans->countAll());
      $this->response($scans, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'No scans were found'
      ], REST_Controller::HTTP_NOT_FOUND);
    }
  }
}
