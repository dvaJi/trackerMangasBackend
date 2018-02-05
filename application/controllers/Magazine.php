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

  public function pending_get() {
    try {
      $this->load->model('magazines/pendingMagazines', 'pendingMagazines');

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
      $data = NULL;
      if ($id == NULL || $id == 'undefined') {
        $conditions = array('status_approval' => 0);
        $data = $this->pendingMagazines->relate()->getWhere($conditions);
      } else {
        $data = $this->pendingMagazines->relate()->find($id);
        if ($data === NULL || intval($data->status_approval) !== 0) {
          throw new RuntimeException('No se ha encontrado el staff solicitado.');
        }
      }

      if ($data !== NULL) {
        $this->set_response($data, REST_Controller::HTTP_OK);
      } else {
        $response = [
          'status' => FALSE,
          'message' => 'No se encontró ninguna revista pendiente.',
        ];
        $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
      }
    } catch (Exception $e) {
      $response = [
        'status' => FALSE,
        'message' => $e->getMessage(),
      ];
      $this->response($response, REST_Controller::HTTP_BAD_REQUEST);
    }
  }

  /**
	 * POST: index
   * Crea una nueva revista, pero esta se almacena en
   * la tabla pending_magazine hasta que se apruebe.
	 *
	 * @param $staff
	 * @author DvaJi
	 */
  public function index_post() {
    try {
      $this->load->model('magazines/pendingMagazines', 'pendingMagazines');

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
      $magazine->id_user = $token->id;
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

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $magazine->cover_filename = $this->covers_model->uploadPendingCover($magazine, 'magazine', 'id_magazine', $data->cover);
      }

      $result = $this->pendingMagazines->insert($magazine);

      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }



      $response = [
        'status' => TRUE,
        'message' => 'Revista creada con éxito, espere a que nuestro staff lo apruebe.',
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

  public function update_pending_magazine_get() {
    try {

      $this->load->model('magazines/pendingMagazines', 'pendingMagazines');

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

      $data = $this->pendingMagazines->relate()->find($id);

      if ($data === NULL || intval($data->status_approval) !== 0) {
        throw new RuntimeException('No se ha encontrado la revista solicitada.');
      }

      // Comprueba si es aprobado o rechazado
      $isApproved = ($this->get('status') === 'true');
      $reason = $this->get('reason');
      if (! $isApproved) {
        // Actualizar el estado de la serie de pending_serie a rechazado [-1]
        $row = array('status_approval' => -1, 'status_reason' => $reason);
        $this->pendingMagazines->update($id, $row);

        $response = [
          'status' => TRUE,
          'message' => 'Revista rechazada con éxito.',
        ];
        $this->response($response, REST_Controller::HTTP_OK);
      }

      $magazine = new \stdClass;

      $magazine->name = $data->name;
      $magazine->stub = $data->stub;
      $magazine->uniqid = $data->uniqid;
      $magazine->native_name = (isset($data->native_name)) ? $data->native_name : NULL;
      $magazine->id_publisher = (isset($data->id_publisher)) ? intval($data->id_publisher) : NULL;
      $magazine->description = (isset($data->description)) ? $data->description : NULL;
      $magazine->circulation = $data->circulation;
      $magazine->release_schedule = (isset($data->release_schedule)) ? $data->release_schedule : NULL;
      $magazine->website = (isset($data->website)) ? $data->website : NULL;
      $magazine->twitter = (isset($data->twitter)) ? $data->twitter : NULL;
      $magazine->created = $data->created;
      $magazine->updated = date("Y-m-d H:i:s");

      $result = $this->magazines->insert($magazine);

      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }

      $magazine->id = $result->id;

      // Covers
      if (isset($data->cover_filename) && $data->cover_filename != NULL) {
        $covers = $this->covers_model->MoveCoverAndCreateThumbs($magazine, 'magazine', 'id_magazine', $data->cover_filename);
        $this->magazinescovers->insertBatch($covers);
      }

      // Actualizar el estado de la revista de pending_magazine a aprobado [1]
      $row = array('status_approval' => 1);
      $this->pendingMagazines->update($data->id, $row);

      $response = [
        'status' => TRUE,
        'message' => 'Revista aprobada con éxito.',
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
