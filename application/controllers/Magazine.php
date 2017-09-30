<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Magazine extends REST_Controller {

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('magazines');

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function list_get() {
    $magazines = $this->magazines->order_by('name', 'ASC')->getAll();
    if ($magazines) {
      header('X-TOTAL-ROWS: ' . $this->magazines->countAll());
      $this->response($magazines, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'No magazines were found'
      ], REST_Controller::HTTP_NOT_FOUND);
    }
  }
}
