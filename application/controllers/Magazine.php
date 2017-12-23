<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Magazine extends REST_Controller {

  protected $headers;

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('series');
    $this->load->model('seriesaltnames');
    $this->load->model('magazines');
    $this->load->model('publishers');
    $this->load->model('magazinescovers');
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
      $magazine = $this->magazines->relate()->find($id);

      if (!empty($magazine)) {
        $magazine->cover = $this->covers_model->getCovers($magazine, 'magazine', 'id_magazine', $magazine->cover);
        $magazine->series = $this->magazines->getSeries($magazine->series);

        $this->set_response($magazine, REST_Controller::HTTP_OK);
      } else {
        $this->set_response([
          'status' => FALSE,
          'message' => 'Staff could not be found'
        ], REST_Controller::HTTP_NOT_FOUND);
      }

    } else {
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

  public function index_post() {
    try {

      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      // Obtener el token para validar y obtener el usuario.
      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);

      $data = json_decode(file_get_contents('php://input'));
      $magazine = new \stdClass;

      $magazine->name = $data->name;
      $magazine->stub = url_title($data->name, 'underscore', TRUE);

      // El nombre debe ser obligatorio, por lo tanto el stub tambien.
      if ($magazine->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }

      $magazine->uniqid = uniqid();
      $magazine->native_name = (isset($data->nameAltInput)) ? $data->nameAltInput : NULL;
      $magazine->id_publisher = (isset($data->publisher)) ? intval($data->publisher->id) : NULL;
      $magazine->description = (isset($data->description)) ? $data->description : NULL;

      if (isset($data->circulation) && $data->circulation != null) {
        $magazine->circulation = $data->circulation->year . "-" . $data->circulation->month . "-" . $data->circulation->day;
      }

      $magazine->release_schedule = (isset($data->releaseSchedule)) ? $data->releaseSchedule : NULL;
      $magazine->website = (isset($data->website)) ? $data->website : NULL;
      $magazine->twitter = (isset($data->twitter)) ? $data->twitter : NULL;
      $magazine->created = date("Y-m-d H:i:s");
      $magazine->updated = date("Y-m-d H:i:s");

      $result = $this->magazines->insert($magazine);

      if ($result->status !== true) {
        throw new RuntimeException($result);
      }

      $magazine->id = $result->id;

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

  /*
  * Obtiene las revistas según su nombre.
  */
  public function search_get() {

    if ($this->get('q') != NULL) {
      $q = $this->get('q');

      if (strlen($q) > 35) {
        $q = substr($q, -35);
      }

      $magazines= $this->magazines->searchMagazines($q);

      $this->set_response($magazines, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'Parameter required'
      ], REST_Controller::HTTP_METHOD_NOT_ALLOWED);
    }

  }

  /*
  * Obtiene las editoriales según su nombre.
  */
  public function publisher_get() {

    if ($this->get('q') != NULL) {
      $q = $this->get('q');

      if (strlen($q) > 35) {
        $q = substr($q, -35);
      }

      $publishers = $this->publishers->searchPublisher($q);

      $this->set_response($publishers, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'Parameter required'
      ], REST_Controller::HTTP_METHOD_NOT_ALLOWED);
    }
  }
}
