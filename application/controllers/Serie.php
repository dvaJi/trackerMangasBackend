<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Serie extends REST_Controller {

  protected $headers;

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('series');
    $this->load->model('genres');
    $this->load->model('staff');
    $this->load->model('seriestaff');
    $this->load->model('magazines');
    $this->load->model('seriemagazines');
    $this->load->model('seriesaltnames');
    $this->load->model('seriecovers');
    $this->load->model('seriegenres');
    $this->load->model('roles');
    $this->load->model('covers_model');

    $this->headers = apache_request_headers();

    $this->methods['list_get']['limit'] = 500; // 500 requests per hour per user/key
    $this->methods['page_get']['limit'] = 500; // 100 requests per hour per user/key
  }

  /**
	 * GET: list
	 *
	 * @param $id
	 * @param $time
	 * @param $order
   * @param $limit
   * @param $page
	 * @author DvaJi
	 */
  public function list_get() {
    try {
      /*$token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);*/
      $id = $this->get('id');
      // If the id parameter doesn't exist return all the series
      if ($id === NULL) {
        $this->load->model('releases');
        $this->load->model('scans');
        $this->load->model('staffaltnames');

        // Filtros
        $periodo = $this->get('time'); // weekly, monthly, anually, historic
        $order = $this->get('order');
        $limit = 10;
        // no puede sobrepasar los 15
        if (!$this->get('limit') == NULL) {
          $limit = ($this->get('limit') > 15) ? 15 : $this->get('limit');
        }
        $tipo = $this->get('type');
        $page = ($this->get('page') === NULL) ? 1 : $this->get('page');

        // Validar si existen type y order
        if ($tipo == NULL || $this->series->validateType($tipo) || $order == NULL || $this->series->validateOrder($order)) {
          throw new RuntimeException("El parámetro no existe.");
        }

        $where = array(
          'type' => $tipo
        );

        $series = $this->series->relate()->where($where)->order_by($order, 'DESC')->paginate($limit, (int) $page);

        if ($series) {
          foreach ($series as $key => $serie) {
            $serie->name = $this->series->getDefaultName($serie->names);
            $serie->names = $this->series->getNames($serie->names);
            $serie->genres = $this->series->getGenres($serie->genres);
            $serie->staff = $this->series->getStaff($serie->staff);
            $serie->magazines = $this->series->getMagazines($serie->magazines);
            $serie->releases = $this->series->getReleases($this->releases->relate()->getWhere(['series_id' => $serie->id]));
            $serie->cover = $this->series->getCovers($serie->cover, $serie);
            $serie->loading = false;
          }
          $this->response($series, REST_Controller::HTTP_OK);

        } else {
          $this->response([
            'status' => FALSE,
            'message' => 'No series were found'
          ], REST_Controller::HTTP_NOT_FOUND);

        }
      } else {
        $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);

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
	 * GET: page
	 *
	 * @param $id
	 * @author DvaJi
	 */
  public function page_get() {
      try {

        $id = $this->get('id');

        $this->load->model('releases');
        $this->load->model('scans');
        $this->load->model('staffaltnames');

        $id = $this->get('id');

        if ($id !== NULL) {
          $id = (int) $id;

          if ($id <= 0) {
            $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
          }

          $serie = $this->series->relate()->find($id);

          if (!empty($serie)) {
            $serie->name = $this->series->getDefaultName($serie->names);
            $serie->names = $this->series->getNames($serie->names);
            $serie->genres = $this->series->getGenres($serie->genres);
            $serie->staff = $this->series->getStaff($serie->staff);
            $serie->staffFormated = $this->series->getStaffFormated($serie->staff, $serie->id);
            $serie->magazines = $this->series->getMagazines($serie->magazines);
            $serie->releases = $this->series->getReleases($this->releases->relate()->getWhere(['series_id' => $serie->id]));
            $serie->cover = $this->series->getCovers($serie->cover, $serie);
            $serie->loading = false;

            $this->set_response($serie, REST_Controller::HTTP_OK);
          } else {
            $this->set_response([
              'status' => FALSE,
              'message' => 'Serie could not be found'
            ], REST_Controller::HTTP_NOT_FOUND);
          }
        } else {
          $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
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
	 * POST: page
   * Crea una nueva Serie, pero esta se almacena en
   * la tabla pending_serie, hasta que se apruebe.
	 *
	 * @param $serie
	 * @author DvaJi
	 */
  public function index_post() {

    $this->load->model('series/pendingSerie', 'pendingSerie');
    $this->load->model('series/pendingSerieGenres', 'pendingSerieGenres');
    $this->load->model('series/pendingSerieStaff', 'pendingSerieStaff');
    $this->load->model('series/pendingSerieNames', 'pendingSerieNames');
    $this->load->model('series/pendingSerieMagazine', 'pendingSerieMagazine');

    try {

      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      // Obtener el token para validar y obtener el usuario.
      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);

      $data = json_decode(file_get_contents('php://input'));
      $name = $data->name;

      $serie = new \stdClass;

      $serie->stub = url_title($name, 'underscore', TRUE);
      if ($serie->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
      $serie->id_user = $token->id;
      $serie->uniqid = uniqid();
      $serie->type = (isset($data->type)) ? $data->type : NULL;
      $serie->description = (isset($data->description)) ? $data->description : NULL;
      $serie->id_demographic = (isset($data->demographic)) ? intval($data->demographic) : NULL;
      $serie->status_oc = (isset($data->statusOC)) ? intval($data->statusOC) : NULL;
      $serie->status_oc_note = (isset($data->statusOCNote)) ? $data->statusOCNote : NULL;
      $serie->completely_sc = (isset($data->statusSC)) ? intval($data->statusSC) : NULL;
      if (isset($data->publicationDate)) {
        $serie->publication_date = $data->publicationDate->year . "-" . $data->publicationDate->month . "-" . $data->publicationDate->day;
      }
      $serie->created = date("Y-m-d H:i:s");
      $serie->updated = date("Y-m-d H:i:s");

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $serie->cover_filename = $this->covers_model->uploadPendingCover($serie, 'series', 'id_series', $data->cover);
      }

      $result = $this->pendingSerie->insert($serie);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $serie->id = $result->id;


      // Nombres Alt.
      $nombresAlt = array();
      $nombresAltObj = new \stdClass;
      $nombresAltObj->id_pending_serie = $serie->id;
      $nombresAltObj->name = $name;
      $nombresAltObj->def = 1;
      $nombresAltObj->created = date("Y-m-d H:i:s");
      $nombresAltObj->updated = date("Y-m-d H:i:s");
      array_push($nombresAlt, $nombresAltObj);

      if (! empty($data->altNames)) {
        foreach ($data->altNames as $key => $altNames) {
          $nombresAltObj = new \stdClass;
          $nombresAltObj->id_pending_serie = $serie->id;
          $nombresAltObj->name = $altNames->value;
          $nombresAltObj->def = 0;
          $nombresAltObj->created = date("Y-m-d H:i:s");
          $nombresAltObj->updated = date("Y-m-d H:i:s");
          array_push($nombresAlt, $nombresAltObj);
        }
      }

      $this->pendingSerieNames->insertBatch($nombresAlt);

      // Géneros
      if (! empty($data->genres)) {
        $genres = array();
        foreach ($data->genres as $key => $genre) {
          $genreObj = new \stdClass;
          $genreObj->id_pending_serie = $serie->id;
          $genreObj->id_genre = intval($genre->id);
          $genreObj->created = date("Y-m-d H:i:s");
          $genreObj->updated = date("Y-m-d H:i:s");
          array_push($genres, $genreObj);
        }
        $this->pendingSerieGenres->insertBatch($genres);
      }

      // Staff
      $staff = array();
      if (! empty($data->author)) {
        foreach ($data->author as $key => $author) {
          $authorObj = new \stdClass;
          $authorObj->id_pending_serie = $serie->id;
          $authorObj->id_staff = intval($author->id);
          $authorObj->id_roles = 1;
          $authorObj->created = date("Y-m-d H:i:s");
          $authorObj->updated = date("Y-m-d H:i:s");
          array_push($staff, $authorObj);
        }
      }

      if (! empty($data->artist)) {
        foreach ($data->artist as $key => $artist) {
          $artistObj = new \stdClass;
          $artistObj->id_pending_serie = $serie->id;
          $artistObj->id_staff = intval($artist->id);
          $artistObj->id_roles = 2;
          $artistObj->created = date("Y-m-d H:i:s");
          $artistObj->updated = date("Y-m-d H:i:s");
          array_push($staff, $artistObj);
        }
      }

      if (! empty($staff)) {
        $this->pendingSerieStaff->insertBatch($staff);
      }

      // Revistas
      if (! empty($data->magazine)) {
        $magazines = array();
        foreach ($data->magazine as $key => $magazine) {
          $magazineObj = new \stdClass;
          $magazineObj->id_pending_serie = $serie->id;
          $magazineObj->id_magazine = intval($magazine->id);
          $magazineObj->created = date("Y-m-d H:i:s");
          $magazineObj->updated = date("Y-m-d H:i:s");
          array_push($magazines, $magazineObj);
        }

        $this->pendingSerieMagazine->insertBatch($magazines);
      }

      $response = [
        'status' => TRUE,
        'message' => 'Serie creada con éxito, espere a que nuestro staff lo apruebe.',
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

  public function aprobar_serie_get() {

    try {
      $this->load->model('series/pendingSerie', 'pendingSerie');

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

      $data = $this->pendingSerie->relate()->find($id);

      if ($data === NULL || intval($data->status_approval) !== 0) {
        throw new RuntimeException('No se ha encontrado la serie solicitada.');
      }

      $serie = new \stdClass;
      $serie->stub = $data->stub;
      $serie->uniqid = $data->uniqid;
      $serie->type = (isset($data->type)) ? $data->type : NULL;
      $serie->description = (isset($data->description)) ? $data->description : NULL;
      $serie->id_demographic = (isset($data->id_demographic)) ? intval($data->id_demographic) : NULL;
      $serie->status_oc = (isset($data->status_oc)) ? intval($data->status_oc) : NULL;
      $serie->status_oc_note = (isset($data->status_oc_note)) ? $data->status_oc_note : NULL;
      $serie->completely_sc = (isset($data->completely_sc)) ? intval($data->completely_sc) : NULL;
      $serie->publication_date = (isset($data->publication_date)) ? $data->publication_date : NULL;
      $serie->created = $data->created;
      $serie->updated = date("Y-m-d H:i:s");

      $result = $this->series->insert($serie);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $serie->id = $result->id;

      // Covers
      if (isset($data->cover_filename) && $data->cover_filename != NULL) {
        $covers = $this->covers_model->MoveCoverAndCreateThumbs($serie, 'series', 'id_series', $data->cover_filename);
        $this->seriecovers->insertBatch($covers);
      }

      // Nombres Alt.
      $nombresAlt = array();
      if (! empty($data->names)) {
        foreach ($data->names as $key => $altNames) {
          $nombresAltObj = new \stdClass;
          $nombresAltObj->id_series = $serie->id;
          $nombresAltObj->name = $altNames->name;
          $nombresAltObj->def = intval($altNames->def);
          array_push($nombresAlt, $nombresAltObj);
        }
      }

      $this->seriesaltnames->insertBatch($nombresAlt);

      // Géneros
      if (! empty($data->genres)) {
        $genres = array();
        foreach ($data->genres as $key => $genre) {
          $genreObj = new \stdClass;
          $genreObj->id_series = $serie->id;
          $genreObj->id_typegenres = intval($genre->id_genre);
          array_push($genres, $genreObj);
        }
        $this->seriegenres->insertBatch($genres);
      }

      // Staff
      $staffs = array();
      if (! empty($data->staff)) {
        foreach ($data->staff as $key => $staff) {
          $staffObj = new \stdClass;
          $staffObj->id_series = $serie->id;
          $staffObj->id_staff = intval($staff->id_staff);
          $staffObj->id_roles = intval($staff->id_roles);
          array_push($staffs, $staffObj);
        }
      }

      if (! empty($staffs)) {
        $this->seriestaff->insertBatch($staffs);
      }

      // Revistas
      if (! empty($data->magazines)) {
        $magazines = array();
        foreach ($data->magazines as $key => $magazine) {
          $magazineObj = new \stdClass;
          $magazineObj->id_series = $serie->id;
          $magazineObj->id_magazines = intval($magazine->id_magazine);
          array_push($magazines, $magazineObj);
        }

        $this->seriemagazines->insertBatch($magazines);
      }

      // Actualizar el estado de la serie de pending_serie a aprobado [1]
      $row = array('status_approval' => 1);
      $this->pendingSerie->update($data->id, $row);

      $response = [
        'status' => TRUE,
        'message' => 'Serie aprobada con éxito.',
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

      $series = $this->series->searchSeries($q);

      $this->set_response($series, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'Parameter required'
      ], REST_Controller::HTTP_METHOD_NOT_ALLOWED);
    }

  }

}
