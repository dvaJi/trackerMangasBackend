<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Staffs extends REST_Controller {

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('staff');
    $this->load->model('staffaltnames');
    $this->load->model('staffcovers');

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function index_get() {
    $page = ($this->get('page') === NULL) ? 1 : $this->get('page');
    $staffs = $this->staff->order_by('stub', 'ASC')->relate()->paginate(15, (int) $page);

    foreach ($staffs as $key => $staff) {
      $staff->name = $this->staff->getDefaultName($staff->names);
      $staff->names = $this->staff->getNames($staff->names);
    }

    if ($staffs) {
      header('X-TOTAL-ROWS: ' . $this->staff->countAll());
      $this->response($staffs, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'No staffs were found'
      ], REST_Controller::HTTP_NOT_FOUND);
    }
  }

  public function index_post() {
    $data = json_decode(file_get_contents('php://input'));

    try {
      $staff = new \stdClass;

      $staff->stub = url_title($data->name, 'underscore', TRUE);
      if ($staff->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
      $staff->uniqid = uniqid();
      $staff->birth_place = (isset($data->birthPlace)) ? $data->birthPlace : NULL;
      $staff->birth_date = (isset($data->birthDate)) ? $data->birthDate : NULL;
      $staff->gender = (isset($data->gender)) ? intval($data->gender) : NULL;
      $staff->description = (isset($data->description)) ? intval($data->description) : NULL;
      $staff->website = (isset($data->website)) ? $data->website : NULL;
      $staff->twitter = (isset($data->twitter)) ? intval($data->twitter) : NULL;
      $staff->created = date("Y-m-d H:i:s");
      $staff->updated = date("Y-m-d H:i:s");

      $staff->id = $this->staff->insert($staff);

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $covers = $this->staff->uploadCover($staff, $data->cover);
        $this->staffcovers->insertBatch($covers);
      }

      // Nombres Alt.
      $nombresAlt = array();
      $nombresAltObj = new \stdClass;
      $nombresAltObj->id_staff = $staff->id;
      $nombresAltObj->name = $data->name;
      $nombresAltObj->def = 1;
      array_push($nombresAlt, $nombresAltObj);

      if (! empty($data->altNames)) {
        foreach ($data->altNames as $key => $altNames) {
          $nombresAltObj = new \stdClass;
          $nombresAltObj->id_staff = $staff->id;
          $nombresAltObj->name = $altNames->value;
          $nombresAltObj->def = 0;
          array_push($nombresAlt, $nombresAltObj);
        }
      }

      $this->staffaltnames->insertBatch($nombresAlt);

      $response = [
        'status' => TRUE,
        'message' => 'Staff creado con éxito.',
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
