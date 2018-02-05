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
            if ($serie->publication_date !== null) {
              $publicationDate = new \stdClass;
              $publicationDate->formatted = $serie->publication_date;
              $dateParts = explode('-', $serie->publication_date);
              $publicationDate->year = intval($dateParts[0]);
              $publicationDate->month = intval($dateParts[1]);
              $publicationDate->day = intval($dateParts[2]);
              $serie->publication_date = $publicationDate;
            }


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
	 * POST: add_revision
   * Crea una nueva Serie, pero esta se almacena en
   * la tabla revision_serie, hasta que se apruebe.
	 *
	 * @param $serie
	 * @author DvaJi
	 */
  public function add_revision_post() {

    $this->load->model('series/revisionSerie', 'revisionSerie');
    $this->load->model('series/revisionSerieGenres', 'revisionSerieGenres');
    $this->load->model('series/revisionSerieStaff', 'revisionSerieStaff');
    $this->load->model('series/revisionSerieNames', 'revisionSerieNames');
    $this->load->model('series/revisionSerieMagazine', 'revisionSerieMagazine');

    try {

      if(!Authorization::tokenIsExist($this->headers)) {
        throw new RuntimeException('Token not found.');
      }

      // Obtener el token para validar y obtener el usuario.
      $token = Authorization::getBearerToken();
      $token = Authorization::validateToken($token);

      // Obtener los datos del formulario
      $data = json_decode(file_get_contents('php://input'));

      // Obtener las revisiones anteriores
      $revision_type = (isset($data->revision_type)) ? $data->revision_type : 1;
      $revision_version = (isset($data->revision_version)) ? $data->revision_version : 1;
      if (isset($data->revision_type) && $data->revision_type == 2) {
        $conditions = array('id_serie' => $data->id_serie);
        $revisions = $this->revisionSerie->order_by('id', 'DESC')->getWhere($conditions);
        if ($revisions != null && count($revisions) === 1) {
          $revision_version = (intval($revisions->revision_version) + 1);
        } else if ($revisions != null && count($revisions) > 1) {
          $revision_version = (intval($revisions[0]->revision_version) + 1);
        }
      }


      $serie = new \stdClass;

      $serie->stub = url_title($data->name, 'underscore', TRUE);
      if ($serie->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
      $serie->id_user = intval($token->id);
      $serie->id_serie = ($data->id_serie != null) ? intval($data->id_serie) : NULL;
      $serie->uniqid = ($data->uniqid != null) ? $data->uniqid : uniqid();
      $serie->type = (isset($data->type)) ? $data->type : NULL;
      $serie->description = (isset($data->description)) ? $data->description : NULL;
      $serie->id_demographic = (isset($data->id_demographic)) ? intval($data->id_demographic) : NULL;
      $serie->status_oc = (isset($data->status_oc)) ? intval($data->status_oc) : NULL;
      $serie->status_oc_note = (isset($data->status_oc_note)) ? $data->status_oc_note : NULL;
      $serie->completely_sc = (isset($data->completely_sc)) ? intval($data->completely_sc) : NULL;
      if (isset($data->publicationDate)) {
        $serie->publication_date = $data->publicationDate->year . "-" . $data->publicationDate->month . "-" . $data->publicationDate->day;
      }
      $serie->created = date("Y-m-d H:i:s");
      $serie->updated = date("Y-m-d H:i:s");
      // revision status
      $serie->revision_type = $revision_type;
      $serie->revision_version = $revision_version;

      // Covers
      if (isset($data->cover) && $data->cover != NULL && isset($data->cover->filename)) {
        $serie->cover_filename = $this->covers_model->uploadPendingCover($serie, 'series', 'id_series', $data->cover);
      } else if (isset($data->cover) && $data->cover != NULL && isset($data->cover->original)) {
        $serie->cover_filename = $data->cover->original->filename;
      }

      $result = $this->revisionSerie->insert($serie);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $serie->id = $result->id;

      // Nombres Alt.
      $nombresAlt = array();
      $nombresAltObj = new \stdClass;
      $nombresAltObj->id_revision_serie = $serie->id;
      $nombresAltObj->name = $data->name;
      $nombresAltObj->def = 1;
      $nombresAltObj->created = date("Y-m-d H:i:s");
      $nombresAltObj->updated = date("Y-m-d H:i:s");
      array_push($nombresAlt, $nombresAltObj);

      if (! empty($data->altNames)) {
        foreach ($data->altNames as $key => $altNames) {
          $nombresAltObj = new \stdClass;
          $nombresAltObj->id_revision_serie = $serie->id;
          $nombresAltObj->name = $altNames->name;
          $nombresAltObj->def = 0;
          $nombresAltObj->created = date("Y-m-d H:i:s");
          $nombresAltObj->updated = date("Y-m-d H:i:s");
          array_push($nombresAlt, $nombresAltObj);
        }
      }

      var_dump($this->revisionSerieNames->insertBatch($nombresAlt));

      // Géneros
      if (! empty($data->genres)) {
        $genres = array();
        foreach ($data->genres as $key => $genre) {
          $genreObj = new \stdClass;
          $genreObj->id_revision_serie = $serie->id;
          $genreObj->id_genre = intval($genre->id);
          $genreObj->created = date("Y-m-d H:i:s");
          $genreObj->updated = date("Y-m-d H:i:s");
          array_push($genres, $genreObj);
        }
        $this->revisionSerieGenres->insertBatch($genres);
      }

      // Staff
      $staff = array();
      if (! empty($data->author)) {
        foreach ($data->author as $key => $author) {
          $authorObj = new \stdClass;
          $authorObj->id_revision_serie = $serie->id;
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
          $artistObj->id_revision_serie = $serie->id;
          $artistObj->id_staff = intval($artist->id);
          $artistObj->id_roles = 2;
          $artistObj->created = date("Y-m-d H:i:s");
          $artistObj->updated = date("Y-m-d H:i:s");
          array_push($staff, $artistObj);
        }
      }

      if (! empty($staff)) {
        $this->revisionSerieStaff->insertBatch($staff);
      }

      // Revistas
      if (! empty($data->magazine)) {
        $magazines = array();
        foreach ($data->magazine as $key => $magazine) {
          $magazineObj = new \stdClass;
          $magazineObj->id_revision_serie = $serie->id;
          $magazineObj->id_magazine = intval($magazine->id);
          $magazineObj->created = date("Y-m-d H:i:s");
          $magazineObj->updated = date("Y-m-d H:i:s");
          array_push($magazines, $magazineObj);
        }

        $this->revisionSerieMagazine->insertBatch($magazines);
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

  /**
	 * GET: pending
   * Puede retornar una lista o un objeto dependiendo
   * si se obtiene un id válido, y que estén sin aprobar (0).
	 *
	 * @param $serie
	 * @author DvaJi
	 */
  public function pending_get() {
    try {
      $this->load->model('series/revisionSerie', 'revisionSerie');
      $this->load->model('series');

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
        $conditions = array('status_approval' => 0, 'revision_type' => 1);
        $data = $this->revisionSerie->relate()->getWhere($conditions);
        foreach ($data as $key => $serie) {
          $serie->name = $this->revisionSerie->getDefaultName($serie->names);
        }
      } else {
        $data = $this->revisionSerie->relate()->find($id);
        if ($data === NULL || intval($data->status_approval) !== 0) {
          throw new RuntimeException('No se ha encontrado la serie solicitada.');
        }
        $data->name = $this->revisionSerie->getDefaultName($data->names);
        $data->staff = $this->revisionSerie->getPendingStaffFormated($data->staff, $data->id);
        $data->genres = $this->revisionSerie->getGenres($data->genres);
        $data->magazines = $this->revisionSerie->getMagazines($data->magazines);
      }

      if ($data !== NULL) {
        $this->set_response($data, REST_Controller::HTTP_OK);
      } else {
        $response = [
          'status' => FALSE,
          'message' => 'No se encontró ninguna serie pendiente.',
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
   * GET: update_pending_serie
   * Obtiene la serie pendiente y crea una nueva serie en la tabla
   * series, este sólo sirve para los tipo Add (1), dado query
   * los de tipo Edit (2) tienen su propio endpoint.
   *
   * @param $id: id de la serie
   * @param $status: boolean del estado
   * @param $reason: razón del por qué se rechazó o aprobó.
   * @author DvaJi
   */
  public function update_pending_serie_get() {
    try {
      $this->load->model('series/revisionSerie', 'revisionSerie');

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

      $data = $this->revisionSerie->relate()->find($id);

      if ($data === NULL || intval($data->status_approval) !== 0 || intval($data->revision_type) !== 1) {
        throw new RuntimeException('No se ha encontrado la serie solicitada.');
      }

      // Comprueba si es aprobado o rechazado
      $isApproved = ($this->get('status') === 'true');
      $reason = $this->get('reason');
      if (! $isApproved) {
        // Actualizar el estado de la serie de pending_serie a rechazado [-1]
        $row = array('status_approval' => -1, 'status_reason' => $reason);
        $this->revisionSerie->update($id, $row);

        $response = [
          'status' => TRUE,
          'message' => 'Serie rechazada con éxito.',
        ];
        $this->response($response, REST_Controller::HTTP_OK);
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
      $this->revisionSerie->update($data->id, $row);

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
   * GET: update_changes_serie
   * Obtiene la revisión de la serie que contiene cambios
   * y revisa las diferencias con la serie que se encuentra
   * en la tabla series, cada uno de los cambios los registrados
   * en la tabla serie_changelog y serie_changelog_detail
   *
   * @param $id
   * @param $status
   * @param $reason
   * @author DvaJi
   */
  public function update_changes_serie_get() {
    try {
      $this->load->model('series/revisionSerie', 'revisionSerie');
      $this->load->model('series/serieChangelog', 'serieChangelog');
      $this->load->model('series/serieChangelogDetail', 'serieChangelogDetail');

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

      $data = $this->revisionSerie->relate()->find($id);
      $oldSerie = $this->serie->relate()->find($data->id_serie);

      if ($data === NULL || intval($data->status_approval) !== 0 || intval($data->revision_type) !== 2) {
        throw new RuntimeException('No se ha encontrado la serie solicitada.');
      }

      // Comprueba si es aprobado o rechazado
      $isApproved = ($this->get('status') === 'true');
      $reason = $this->get('reason');
      if (! $isApproved) {
        // Actualizar el estado de la serie de pending_serie a rechazado [-1]
        $row = array('status_approval' => -1, 'status_reason' => $reason);
        $this->revisionSerie->update($id, $row);

        $response = [
          'status' => TRUE,
          'message' => 'Cambios rechazados con éxito.',
        ];
        $this->response($response, REST_Controller::HTTP_OK);
      }

      // Crear el changelog
      $changelog = [
        'id_serie'  => $oldSerie->id,
        'id_user' => $token->id,
        'status_change' => 0,
        'status_reason' => "",
        'created' = date("Y-m-d H:i:s")
        'updated' = date("Y-m-d H:i:s")
      ];
      $result = $this->serieChangelog->insert($changelog);
      if ($result->status !== true) {
        throw new RuntimeException($result->message);
      }
      $changelog->id = $result->id;

      // Comparar cada columna de la tabla series
      $serie = array();
      $changes = array();
      if ($data->stub !== $oldSerie->stub) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'stub', 'Stub', $oldSerie->stub, $data->stub);
        array_push($changes, $change);
        $serieRow = array('stub' => $data->stub);
        array_push($serie, $serieRow);
      }

      if ($data->type !== $oldSerie->type) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'type', 'Tipo', $oldSerie->type, $data->type);
        array_push($changes, $change);
        $serieRow = array('type' => $data->type);
        array_push($serie, $serieRow);
      }

      if ($data->description !== $oldSerie->description) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'description', 'Descripción', $oldSerie->description, $data->description);
        array_push($changes, $change);
        $serieRow = array('description' => $data->description);
        array_push($serie, $serieRow);
      }

      if ($data->id_demographic !== $oldSerie->id_demographic) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'id_demographic', 'Demografía', $oldSerie->id_demographic, $data->id_demographic);
        array_push($changes, $change);
        $serieRow = array('id_demographic' => $data->id_demographic);
        array_push($serie, $serieRow);
      }

      if ($data->status_oc !== $oldSerie->status_oc) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'status_oc', 'Estado en país origen', $oldSerie->status_oc, $data->status_oc);
        array_push($changes, $change);
        $serieRow = array('status_oc' => $data->status_oc);
        array_push($serie, $serieRow);
      }

      if ($data->status_oc_note !== $oldSerie->status_oc_note) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'status_oc_note', 'Notas del estado', $oldSerie->status_oc_note, $data->status_oc_note);
        array_push($changes, $change);
        $serieRow = array('status_oc' => $data->status_oc_note);
        array_push($serie, $serieRow);
      }

      if ($data->completely_sc !== $oldSerie->completely_sc) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'completely_sc', 'Traducción', $oldSerie->completely_sc, $data->completely_sc);
        array_push($changes, $change);
        $serieRow = array('completely_sc' => $data->completely_sc);
        array_push($serie, $serieRow);
      }

      if ($data->publication_date !== $oldSerie->publication_date) {
        $change = $this->serieChangelogDetail->createChangelogDetail($changelog->id, 'series', 'publication_date', 'Fecha Publicación', $oldSerie->publication_date, $data->publication_date);
        array_push($changes, $change);
        $serieRow = array('publication_date' => $data->publication_date);
        array_push($serie, $serieRow);
      }

      $serieRow = array('updated' => date("Y-m-d H:i:s"));
      array_push($serie, $serieRow);

      // Actualizar serie
      $this->serie->update($oldSerie->id, $serie);

      // Covers
      // Sólo considerar si no es null
      if ($data->cover_filename !== null) {
        // En caso en que ambas portadas tengan distinto nombre
        if ($oldSerie->covers !== null && $data->cover_filename !== $oldSerie->covers[0]->filename) {
          $covers = $this->covers_model->MoveCoverAndCreateThumbsUpdate($serie, 'series', 'id_series', $data->cover_filename);
          foreach ($oldSerie->covers as $key => $value) {
            $this->seriecovers->update($value->id, $covers[$key]);
          }
        // Si antes no tenía portada
        } else if ($oldSerie->covers === null) {
          $covers = $this->covers_model->MoveCoverAndCreateThumbs($serie, 'series', 'id_series', $data->cover_filename);
          $this->seriecovers->insertBatch($covers);
        }
      }

      // NAMES
      if (! empty($data->names)) {
        if (empty($oldSerie->names)) {
          $altNames = $this->seriesaltnames->generateNamesArray($data->names, $oldSerie->id);
          $this->seriesaltnames->insertBatch($altNames);
        } else if (!empty(array_diff($data->names, $oldSerie->names))) {
          foreach ($oldSerie->names as $key => $name) {
            $this->seriesaltnames->delete($name->id);
          }
          $altNames = $this->seriesaltnames->generateNamesArray($data->names, $oldSerie->id);
          $this->seriesaltnames->insertBatch($altNames);
        }
      // Se eliminaron todos los nombres
      } else if (empty($data->names) && !empty($oldSerie->names)) {
        foreach ($oldSerie->names as $key => $name) {
          $this->seriesaltnames->delete($name->id);
        }
      }

      // Géneros
      if (! empty($data->genres)) {
        if (empty($oldSerie->genres)) {
          $genres = $this->seriegenres->generateGenresArray($data->genres, $oldSerie->id);
          $this->seriegenres->insertBatch($genres);

        } else if (!empty(array_diff($data->genres, $oldSerie->genres))) {
          foreach ($oldSerie->genres as $key => $genre) {
            $this->seriegenres->delete($genre->id);
          }
          $genres = $this->seriegenres->generateGenresArray($data->genres, $oldSerie->id);
          $this->seriegenres->insertBatch($genres);
        }
      } else if (empty($data->genres) && !empty($oldSerie->genres)) {
        foreach ($oldSerie->genres as $key => $genre) {
          $this->seriegenres->delete($genre->id);
        }
      }

      // Staff
      if (! empty($data->staff)) {
        if (empty($oldSerie->staff)) {
          $staffs = $this->seriestaff->generateStaffArray($data->staff, $oldSerie->id);
          $this->seriestaff->insertBatch($staffs);

        } else if (!empty(array_diff($data->genres, $oldSerie->staff))) {
          foreach ($oldSerie->staff as $key => $staff) {
            $this->seriestaff->delete($staff->id);
          }
          $staffs = $this->seriestaff->generateStaffArray($data->staff, $oldSerie->id);
          $this->seriestaff->insertBatch($staffs);
        }
      } else if (empty($data->staff) && !empty($oldSerie->staff)) {
        foreach ($oldSerie->staff as $key => $staff) {
          $this->seriestaff->delete($staff->id);
        }
      }

      // Revistas
      if (! empty($data->magazines)) {
        if (empty($oldSerie->magazines)) {
          $magazines = $this->seriemagazines->generateMagazinesArray($data->magazines, $oldSerie->id);
          $this->seriemagazines->insertBatch($magazines);

        } else if (!empty(array_diff($data->magazines, $oldSerie->magazines))) {
          foreach ($oldSerie->magazines as $key => $magazine) {
            $this->seriemagazines->delete($magazine->id);
          }
          $magazines = $this->seriemagazines->generateMagazinesArray($data->magazines, $oldSerie->id);
          $this->seriemagazines->insertBatch($magazines);
        }
      } else if (empty($data->magazines) && !empty($oldSerie->magazines)) {
        foreach ($oldSerie->magazines as $key => $magazine) {
          $this->seriemagazines->delete($magazine->id);
        }
      }

      // Actualizar el estado de la serie de pending_serie a aprobado [1]
      $row = array('status_approval' => 1);
      $this->revisionSerie->update($data->id, $row);

      $this->serieChangelogDetail->insertBatch($changes);

      $response = [
        'status' => TRUE,
        'message' => 'Cambios para la serie realizados con éxito.',
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

  /**
	 * GET: History / changelog
	 *
	 * @param $id_serie
	 * @author DvaJi
	 */
  public function history_get() {

    if ($this->get('id') != NULL) {
      $this->load->model('series/serieChangelog', 'serieChangelog');
      $idSerie = $this->get('id');

      $where = array(
        'id_serie' => $idSerie
      );

      $changelogs = $this->serieChangelog->relate()->where($where)->paginate(10, 1);
      foreach ($changelogs as $key => $value) {
        $value->user = $value->user->{0};
        $value->details = json_decode(json_encode($value->details), true);
        unset($value->user->password);
        unset($value->user->ip_address);
        unset($value->user->salt);
        unset($value->user->activation_code);
        unset($value->user->forgotten_password);
        unset($value->user->forgotten_password_code);
        unset($value->user->forgotten_password_time);
        unset($value->user->remember_code);
        unset($value->user->created_on);
        unset($value->user->last_login);
        unset($value->user->active);
        unset($value->user->company);
        unset($value->user->phone);
        unset($value->serie);
      }

      if (!empty($changelogs)) {
        $this->response($changelogs, REST_Controller::HTTP_OK);
      } else {
        $this->response([
          'status' => FALSE,
          'message' => 'No hay cambios registrados para esta Serie.'
        ], REST_Controller::HTTP_NOT_FOUND);
      }
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'Parameter required'
      ], REST_Controller::HTTP_METHOD_NOT_ALLOWED);
    }

  }

}
