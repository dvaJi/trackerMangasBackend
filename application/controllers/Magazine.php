<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Magazine extends REST_Controller {

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('magazines');
    $this->load->model('publishers');
    $this->load->model('magazinescovers');
    $this->load->model('covers_model');

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function index_get() {
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

  public function index_post() {
    $data = json_decode(file_get_contents('php://input'));

    try {
      $magazine = new \stdClass;

      $magazine->name = $data->name;
      $magazine->stub = url_title($data->name, 'underscore', TRUE);
      if ($magazine->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
      $magazine->uniqid = uniqid();
      $magazine->native_name = (isset($data->nameAltInput)) ? $data->nameAltInput : NULL;
      $magazine->id_publisher = (isset($data->publisher)) ? intval($data->publisher) : NULL;
      $magazine->description = (isset($data->description)) ? intval($data->description) : NULL;
      $magazine->circulation = (isset($data->circulation)) ? $data->circulation : NULL;
      $magazine->release_schedule = (isset($data->releaseSchedule)) ? intval($data->releaseSchedule) : NULL;
      $magazine->website = (isset($data->website)) ? $data->website : NULL;
      $magazine->twitter = (isset($data->twitter)) ? intval($data->twitter) : NULL;
      $magazine->created = date("Y-m-d H:i:s");
      $magazine->updated = date("Y-m-d H:i:s");

      $magazine->id = $this->magazines->insert($magazine);

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $covers = $this->covers_model->uploadCover($magazine, 'magazine', 'id_magazine', $data->cover);
        $this->magazinescovers->insertBatch($covers);
      }

      $response = [
        'status' => TRUE,
        'message' => 'Revista creada con éxito.',
      ];
      $this->set_response($response, REST_Controller::HTTP_CREATED);

    } catch (Exception $e) {
      $response = [
        'status' => FALSE,
        'message' => $e->getMessage(),
      ];
      $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
    }
  }

  public function publisher_get() {
    $publishers = $this->publishers->order_by('name', 'ASC')->getAll();
    if ($publishers) {
      header('X-TOTAL-ROWS: ' . $this->publishers->countAll());
      $this->response($publishers, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'No publishers were found'
      ], REST_Controller::HTTP_NOT_FOUND);
    }
  }
}
