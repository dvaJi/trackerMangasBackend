<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Release extends REST_Controller {

  protected $headers;

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('releases');
    $this->load->model('releasesgroups');

    $this->headers = apache_request_headers();

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

  /**
	 * POST: index
   * Crea un nuevo Release, pero esta se almacena en
   * la tabla pending_releases hasta que se apruebe.
	 *
	 * @param $release
	 * @author DvaJi
	 */
  public function index_post() {

    $this->load->model('releases/pendingReleases', 'pendingReleases');
    $this->load->model('releases/pendingReleasesGroups', 'pendingReleasesGroups');

    try {

      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      // Obtener el token para validar y obtener el usuario.
      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);

      $data = json_decode(file_get_contents('php://input'));

      if ($data->chapter == NULL || empty($data->serie) || empty($data->scans)) {
        throw new RuntimeException("No se han ingresado los valores necesarios.");
      }

      $release = new \stdClass;
      $release->id_user = $token->id;
      $release->chapter = intval($data->chapter);
      $release->volume = intval($data->volume);
      $release->id_serie = intval($data->serie->id);
      if (isset($data->publicationDate)) {
        $release->release_date = $data->publicationDate->year . "-" . $data->publicationDate->month . "-" . $data->publicationDate->day;
      } else {
        $release->release_date = date("Y-m-d H:i:s");
      }
      $release->created = date("Y-m-d H:i:s");
      $release->updated = date("Y-m-d H:i:s");

      $result = $this->pendingReleases->insert($release);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $release->id = $result->id;

      // Scans
      if (! empty($data->scans)) {
        $scans = array();
        foreach ($data->scans as $key => $scan) {
          $scanObj = new \stdClass;
          $scanObj->id_pending_releases = $release->id;
          $scanObj->id_scan = intval($scan->id);
          array_push($scans, $scanObj);
        }

        $this->pendingReleasesGroups->insertBatch($scans);
      }

      $response = [
        'status' => TRUE,
        'message' => 'Release creado con éxito, espere a que nuestro staff lo apruebe.',
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

  public function aprobar_release_get() {
    try {

      $this->load->model('releases/pendingReleases', 'pendingReleases');

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
      $data = $this->pendingReleases->relate()->find($id);

      if ($data === NULL || intval($data->status_approval) !== 0) {
        throw new RuntimeException('No se ha encontrado el Release solicitado.');
      }

      $release = new \stdClass;
      $release->chapter = $data->chapter;
      $release->volume = $data->volume;
      $release->series_id = $data->id_serie;
      $release->release_date = $data->release_date;
      $release->created = $data->created;
      $release->updated = date("Y-m-d H:i:s");

      $result = $this->releases->insert($release);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $release->id = $result->id;

      if (! empty($data->scans)) {
        $scans = array();
        foreach ($data->scans as $key => $scan) {
          $scanObj = new \stdClass;
          $scanObj->release_id = $release->id;
          $scanObj->group_id = intval($scan->id_scan);
          array_push($scans, $scanObj);
        }

        $this->releasesgroups->insertBatch($scans);
      }

      // Actualizar el estado del release de pending_releases a aprobado [1]
      $row = array('status_approval' => 1);
      $this->pendingReleases->update($data->id, $row);

      $response = [
        'status' => TRUE,
        'message' => 'Release aprobado con éxito.',
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
