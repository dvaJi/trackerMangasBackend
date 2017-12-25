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

  /**
	 * GET: search
	 *
	 * @param $q (query)
	 * @author DvaJi
	 */
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

  /**
	 * POST: index
   * Crea un nuevo staff, pero esta se almacena en
   * la tabla pending_staff hasta que se apruebe.
	 *
	 * @param $staff
	 * @author DvaJi
	 */
  public function index_post() {

    $this->load->model('staff/pendingStaff', 'pendingStaff');
    $this->load->model('staff/pendingStaffNames', 'pendingStaffNames');

    try {

      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      // Obtener el token para validar y obtener el usuario.
      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);

      $data = json_decode(file_get_contents('php://input'));
      $staff = new \stdClass;

      $staff->stub = url_title($data->name, 'underscore', TRUE);
      if ($staff->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }

      $staff->id_user = $token->id;
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

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $staff->cover_filename = $this->covers_model->uploadPendingCover($staff, 'staff', 'id_staff', $data->cover);
      }

      $result = $this->pendingStaff->insert($staff);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $staff->id = $result->id;

      // Nombres Alt.
      $nombresAlt = array();
      $nombresAltObj = new \stdClass;
      $nombresAltObj->id_pending_staff = $staff->id;
      $nombresAltObj->name = $data->name;
      $nombresAltObj->def = 1;
      array_push($nombresAlt, $nombresAltObj);

      if (! empty($data->altNames)) {
        foreach ($data->altNames as $key => $altNames) {
          $nombresAltObj = new \stdClass;
          $nombresAltObj->id_pending_staff = $staff->id;
          $nombresAltObj->name = $altNames->value;
          $nombresAltObj->def = 0;
          array_push($nombresAlt, $nombresAltObj);
        }
      }

      $this->pendingStaffNames->insertBatch($nombresAlt);

      $response = [
        'status' => TRUE,
        'message' => 'Staff creado con éxito, espere a que nuestro staff lo apruebe.',
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

  public function aprobar_staff_get() {
    try {

      $this->load->model('staff/pendingStaff', 'pendingStaff');

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

      $data = $this->pendingStaff->relate()->find($id);

      if ($data === NULL || intval($data->status_approval) !== 0) {
        throw new RuntimeException('No se ha encontrado el Staff solicitado.');
      }

      $staff = new \stdClass;
      $staff->stub = $data->stub;
      $staff->uniqid = $data->uniqid;
      $staff->birth_place = (isset($data->birth_place)) ? $data->birth_place : NULL;
      $staff->birth_date = (isset($data->birth_date)) ? $data->birth_date : NULL;
      $staff->gender = (isset($data->gender)) ? intval($data->gender) : NULL;
      $staff->description = (isset($data->description)) ? $data->description : NULL;
      $staff->website = (isset($data->website)) ? $data->website : NULL;
      $staff->twitter = (isset($data->twitter)) ? $data->twitter : NULL;
      $staff->pixiv = (isset($data->pixiv)) ? $data->pixiv : NULL;
      $staff->created = $data->created;
      $staff->updated = date("Y-m-d H:i:s");

      $result = $this->staff->insert($staff);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $staff->id = $result->id;

      // Covers
      if (isset($data->cover_filename) && $data->cover_filename != NULL) {
        $covers = $this->covers_model->MoveCoverAndCreateThumbs($staff, 'staff', 'id_staff', $data->cover_filename);
        $this->staffcovers->insertBatch($covers);
      }

      // Nombres Alt.
      $staffAltNames = array();
      if (! empty($data->names)) {
        foreach ($data->names as $key => $altNames) {
          $nombresAltObj = new \stdClass;
          $nombresAltObj->id_staff = $staff->id;
          $nombresAltObj->name = $altNames->name;
          $nombresAltObj->def = $altNames->def;
          array_push($staffAltNames, $nombresAltObj);
        }
      }

      $this->staffaltnames->insertBatch($staffAltNames);

      // Actualizar el estado del staff de pending_staff a aprobado [1]
      $row = array('status_approval' => 1);
      $this->pendingStaff->update($data->id, $row);

      $response = [
        'status' => TRUE,
        'message' => 'Staff aprobado con éxito.',
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
