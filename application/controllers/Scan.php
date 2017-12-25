<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Scan extends REST_Controller {

  protected $headers;

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('series');
    $this->load->model('seriesaltnames');
    $this->load->model('scans');
    $this->load->model('scanscovers');
    $this->load->model('covers_model');
    $this->load->model('releases');
    $this->load->model('staff');
    $this->load->model('staffaltnames');

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

      $scans = $this->scans->relate()->find($id);

      if (!empty($scans)) {
        $scans->cover = $this->covers_model->getCovers($scans, 'scans', 'id_scans', $scans->cover);
        $scans->releases = $this->scans->getReleases($scans->releases);

        $this->set_response($scans, REST_Controller::HTTP_OK);
      } else {
        $this->set_response([
          'status' => FALSE,
          'message' => 'Staff could not be found'
        ], REST_Controller::HTTP_NOT_FOUND);
      }

    } else {
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

  /**
	 * POST: index
   * Crea un nuevo Scan, pero esta se almacena en
   * la tabla pending_scan hasta que se apruebe.
	 *
	 * @param $scan
	 * @author DvaJi
	 */
  public function index_post() {
    try {

      $this->load->model('scans/pendingScans', 'pendingScans');

      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      // Obtener el token para validar y obtener el usuario.
      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);

      $data = json_decode(file_get_contents('php://input'));
      $scan = new \stdClass;

      $scan->id_user = $token->id;
      $scan->name = $data->name;
      $scan->stub = url_title($data->name, 'underscore', TRUE);
      if ($scan->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
      $scan->uniqid = uniqid();
      if (isset($data->creationDate)) {
        $scan->creation_date = $data->creationDate->year . "-" . $data->creationDate->month . "-" . $data->creationDate->day;
      }
      $scan->description = (isset($data->description)) ? intval($data->description) : NULL;
      $scan->website = (isset($data->website)) ? $data->website : NULL;
      $scan->twitter = (isset($data->twitter)) ? intval($data->twitter) : NULL;
      $scan->facebook = (isset($data->facebook)) ? intval($data->facebook) : NULL;
      $scan->created = date("Y-m-d H:i:s");
      $scan->updated = date("Y-m-d H:i:s");

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $scan->cover_filename = $this->covers_model->uploadPendingCover($scan, 'scans', 'id_scans', $data->cover);
      }

      $result = $this->pendingScans->insert($scan);
      if ($result->status !== TRUE) {
        throw new RuntimeException($result->message);
      }

      $scan->id = $result->id;

      $response = [
        'status' => TRUE,
        'message' => 'Scan creado con éxito, espere a que nuestro staff lo apruebe.',
      ];
      $this->response($response, REST_Controller::HTTP_CREATED);

    } catch (Exception $e) {
      $response = [
        'status' => FALSE,
        'message' => $e->getMessage(),
      ];
      $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
    }
  }

  public function aprobar_scan_get() {

    $this->load->model('scans/pendingScans', 'pendingScans');

    try {

      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      // Obtener el token para validar y obtener el usuario.
      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);

      $user_groups = $this->ion_auth->get_users_groups($token->id)->result();

      // Validar de que el usuario tenga los provilegios para aprobar.
      $canApproval = FALSE;
      foreach ($user_groups as $key => $group) {
        if ($group->name == 'admin') {
          $canApproval = TRUE;
        }
      }

      if (! $canApproval) {
        throw new RuntimeException('No tienes permisos suficientes para realizar esta acción.');
      }

      $id = $this->get('id');

      if ($id === NULL || !is_numeric($id)) {
        throw new RuntimeException('No se ha ingresado una id válida.');
      }

      // Obtener el scan por su id
      $data = $this->pendingScans->relate()->find($id);

      if ($data === NULL || intval($data->status_approval) !== 0) {
        throw new RuntimeException('No se ha encontrado el Scan solicitado.');
      }

      $scan = new \stdClass;
      $scan->name = $data->name;
      $scan->stub = $data->stub;
      $scan->uniqid = $data->uniqid;
      $scan->creation_date = $data->creation_date;
      $scan->description = (isset($data->description)) ? intval($data->description) : NULL;
      $scan->website = (isset($data->website)) ? $data->website : NULL;
      $scan->twitter = (isset($data->twitter)) ? intval($data->twitter) : NULL;
      $scan->facebook = (isset($data->facebook)) ? intval($data->facebook) : NULL;
      $scan->created = $data->created;
      $scan->updated = date("Y-m-d H:i:s");

      $result = $this->scans->insert($scan);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }

      $scan->id = $result->id;

      // Covers
      if (isset($data->cover_filename) && $data->cover_filename != NULL) {
        $this->load->model('covers_model');
        $covers = $this->covers_model->MoveCoverAndCreateThumbs($scan, 'scans', 'id_scans', $data->cover_filename);
        $this->scanscovers->insertBatch($covers);
      }

      // Actualizar el estado del scan de pending_scan a aprobado [1]
      $row = array('status_approval' => 1);
      $this->pendingScans->update($data->id, $row);

      $response = [
        'status' => TRUE,
        'message' => 'Scan aprobado con éxito.',
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

  /**
	 * GET: search
	 *
	 * @param $q (query)
	 * @author DvaJi
	 */
  public function search_get() {

    if ($this->get('q') != NULL) {
      $q = $this->get('q');

      if (strlen($q) > 35) {
        $q = substr($q, -35);
      }

      $scans= $this->scans->searchScans($q);

      $this->set_response($scans, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'Parameter required'
      ], REST_Controller::HTTP_METHOD_NOT_ALLOWED);
    }

  }

}
