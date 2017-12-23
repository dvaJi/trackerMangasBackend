<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Staffs extends REST_Controller {

  protected $headers;

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('series');
    $this->load->model('seriesaltnames');
    $this->load->model('roles');
    $this->load->model('staff');
    $this->load->model('staffaltnames');
    $this->load->model('staffcovers');
    $this->load->model('covers_model');

    $this->headers = apache_request_headers();

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function index_get() {

    if ($this->get('id') != NULL) {
      // DETALLE
      $id = (int) $this->get('id');

      if ($id <= 0) {
        $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
      }

      $staff = $this->staff->relate()->find($id);

      if (!empty($staff)) {
        $staff->name = $this->staff->getDefaultName($staff->names);
        $staff->names = $this->staff->getNames($staff->names);
        $staff->cover = $this->covers_model->getCovers($staff, 'staff', 'id_staff', $staff->cover);
        $staff->series = $this->staff->getSeries($staff->series);

        $this->set_response($staff, REST_Controller::HTTP_OK);
      } else {
        $this->set_response([
          'status' => FALSE,
          'message' => 'Staff could not be found'
        ], REST_Controller::HTTP_NOT_FOUND);
      }

    } else {
      // LISTA
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

  }

  public function search_get() {

    if ($this->get('q') != NULL) {
      $q = $this->get('q');

      if (strlen($q) > 30) {
        $q = substr($q, -30);
      }

      $staff = $this->staffaltnames->getStaffByName($q);

      $this->set_response($staff, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'Parameter required'
      ], REST_Controller::HTTP_METHOD_NOT_ALLOWED);
    }

  }

  public function index_post() {
    try {
      
      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);
      $data = json_decode(file_get_contents('php://input'));
      $staff = new \stdClass;

      $staff->stub = url_title($data->name, 'underscore', TRUE);
      if ($staff->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
      $staff->uniqid = uniqid();
      $staff->birth_place = (isset($data->birthPlace)) ? $data->birthPlace : NULL;
      if (isset($data->birthDate) && $data->birthDate != null) {
        $staff->birth_date = $data->birthDate->year . "-" . $data->birthDate->month . "-" . $data->birthDate->day;
      }
      $staff->gender = (isset($data->gender)) ? intval($data->gender) : NULL;
      $staff->description = (isset($data->description)) ? $data->description : NULL;
      $staff->website = (isset($data->website)) ? $data->website : NULL;
      $staff->twitter = (isset($data->twitter)) ? $data->twitter : NULL;
      $staff->pixiv = (isset($data->pixiv)) ? $data->pixiv : NULL;
      $staff->created = date("Y-m-d H:i:s");
      $staff->updated = date("Y-m-d H:i:s");

      $result = $this->staff->insert($staff);
      if ($result->status !== true) {
        throw new RuntimeException($result);
      }
      $staff->id = $result->id;

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $covers = $this->covers_model->uploadCover($staff, 'staff', 'id_staff', $data->cover);
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

      $this->set_response("asd", REST_Controller::HTTP_OK);

    } catch (Exception $e) {
      $response = [
        'id' => 100, // Automatically generated by the model
        'name' => $this->post('name'),
        'email' => $this->post('email'),
        'message' => 'Added a resource'
      ];
      $this->set_response($response, REST_Controller::HTTP_BAD_REQUEST);
    }
  }
}
