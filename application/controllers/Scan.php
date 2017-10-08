<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Scan extends REST_Controller {

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('scans');
    $this->load->model('scanscovers');

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function index_get() {
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

  public function index_post() {
    $data = json_decode(file_get_contents('php://input'));

    try {
      $scan = new \stdClass;

      $scan->name = $data->name;
      $scan->stub = url_title($data->name, 'underscore', TRUE);
      if ($scan->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
      $scan->uniqid = uniqid();
      $scan->creation_date = (isset($data->creationDate)) ? $data->creationDate->formatted : NULL;
      $scan->description = (isset($data->description)) ? intval($data->description) : NULL;
      $scan->website = (isset($data->website)) ? $data->website : NULL;
      $scan->twitter = (isset($data->twitter)) ? intval($data->twitter) : NULL;
      $scan->facebook = (isset($data->facebook)) ? intval($data->facebook) : NULL;
      $scan->created = date("Y-m-d H:i:s");
      $scan->updated = date("Y-m-d H:i:s");

      $scan->id = $this->scans->insert($scan);

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $this->load->model('covers_model');
        $covers = $this->covers_model->uploadCover($scan, 'scans', 'id_scans', $data->cover);
        $this->scanscovers->insertBatch($covers);
      }

      $response = [
        'status' => TRUE,
        'message' => 'Scan creado con éxito.',
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

}
