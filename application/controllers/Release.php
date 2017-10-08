<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Release extends REST_Controller {

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('releases');
    $this->load->model('releasesgroups');

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function list_get() {
    $id = $this->get('id');
    // If the id parameter doesn't exist return all the releases
    if ($id === NULL) {
      $this->load->model('scans');
      $this->load->model('series');
      $this->load->model('seriesaltnames');
      $page = ($this->get('page') === NULL) ? 1 : $this->get('page');
      $releases = $this->releases->order_by('id', 'DESC')->relate()->paginate(10, (int) $page);
      foreach ($releases as $key => $value) {
        $value->serie = json_decode(json_encode($value->serie->{0}), false);
        $conditions = array('id_series' => $value->serie->id, 'def' => 1);
        $value->serie->name = $this->seriesaltnames->where($conditions)->get()->row()->name;
        $value->groups = $this->releases->getGroups($value->groups);
      }

      if ($releases) {
        header('X-TOTAL-ROWS: ' . $this->releases->countAll());
        $this->response($releases, REST_Controller::HTTP_OK);
      } else {
        $this->response([
          'status' => FALSE,
          'message' => 'No releases were found'
        ], REST_Controller::HTTP_NOT_FOUND);
      }
    } else {
      $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
    }
  }

  public function index_post() {
    $data = json_decode(file_get_contents('php://input'));

    try {

      if ($data->chapter == NULL || empty($data->serie) || empty($data->scans)) {
        throw new RuntimeException("No se han ingresado los valores necesarios.");
      }

      $release = new \stdClass;
      $release->chapter = $data->chapter;
      $release->volume = $data->volume;
      $release->series_id = $data->serie[0]->value;
      $release->created = (isset($data->publicationDate)) ? $data->publicationDate : date("Y-m-d H:i:s");
      $release->updated = (isset($data->publicationDate)) ? $data->publicationDate : date("Y-m-d H:i:s");

      $release->id = $this->releases->insert($release);

      if ($release->id == NULL) {
        throw new RuntimeException("Ocurrió un error al insertar release.");
      }

      if (! empty($data->scans)) {
        $scans = array();

        foreach ($data->scans as $key => $scan) {
          $scanObj = new \stdClass;
          $scanObj->release_id = $release->id;
          $scanObj->group_id = intval($scan->value);
          array_push($scans, $scanObj);
        }

        $this->releasesgroups->insertBatch($scans);
      }

      $response = [
        'status' => TRUE,
        'message' => 'Release creado con éxito.',
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
