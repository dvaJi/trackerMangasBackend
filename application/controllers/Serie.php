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

    $this->headers = apache_request_headers();

    $this->methods['list_get']['limit'] = 500; // 500 requests per hour per user/key
    $this->methods['page_get']['limit'] = 500; // 100 requests per hour per user/key
  }

  /**
  * A simple list with pagination
  *
  * @author          DvaJi
  */
  public function list_get() {
    if(!Authorization::tokenIsExist($this->headers)) {
      $this->response('Token not found', REST_Controller::HTTP_BAD_REQUEST);
    } else {
      try {
        $token = Authorization::getBearerToken();
        $token = Authorization::validateToken($token);
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

  }

  /**
  * TODO: releases, reviews, stats
  *
  * @author          DvaJi
  */
  public function page_get() {
    if(!Authorization::tokenIsExist($this->headers)) {
      $this->response('Token not found', REST_Controller::HTTP_BAD_REQUEST);
    } else {
      try {

        $token = Authorization::getBearerToken();
        $token = Authorization::validateToken($token);
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
  }

  /**
  * TODO: Validaciones
  *
  * @author          DvaJi
  */
  public function page_post() {
    $this->load->model('seriegenres');
    $this->load->model('covers_model');

    $data = json_decode(file_get_contents('php://input'));

    try {
      $serie = new \stdClass;

      $serie->stub = url_title($data->name, 'underscore', TRUE);
      if ($serie->stub == NULL) {
        throw new RuntimeException("No se ha ingresado un nombre.");
      }
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

      $result = $this->series->insert($serie);
      if ($result->status !== true) {
        throw new RuntimeException($result);
      }
      $serie->id = $result->id;

      // Covers
      if (isset($data->cover) && $data->cover != NULL) {
        $covers = $this->covers_model->uploadCover($serie, 'series', 'id_series', $data->cover);
        $this->seriecovers->insertBatch($covers);
      }

      // Nombres Alt.
      $nombresAlt = array();
      $nombresAltObj = new \stdClass;
      $nombresAltObj->id_series = $serie->id;
      $nombresAltObj->name = $data->name;
      $nombresAltObj->def = 1;
      array_push($nombresAlt, $nombresAltObj);

      if (! empty($data->altNames)) {
        foreach ($data->altNames as $key => $altNames) {
          $nombresAltObj = new \stdClass;
          $nombresAltObj->id_series = $serie->id;
          $nombresAltObj->name = $altNames->value;
          $nombresAltObj->def = 0;
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
          $genreObj->id_typegenres = intval($genre);
          array_push($genres, $genreObj);
        }
        $this->seriegenres->insertBatch($genres);
      }

      // Staff
      $staff = array();
      if (! empty($data->author)) {
        foreach ($data->author as $key => $author) {
          $authorObj = new \stdClass;
          $authorObj->id_series = $serie->id;
          $authorObj->id_staff = intval($author->value);
          $authorObj->id_roles = 1;
          array_push($staff, $authorObj);
        }
      }
      if (! empty($data->artist)) {
        foreach ($data->artist as $key => $artist) {
          $artistObj = new \stdClass;
          $artistObj->id_series = $serie->id;
          $artistObj->id_staff = intval($artist->value);
          $artistObj->id_roles = 2;
          array_push($staff, $artistObj);
        }
      }
      if (! empty($staff)) {
        $this->seriestaff->insertBatch($staff);
      }

      // Revistas
      if (! empty($data->artist)) {
        $magazines = array();
        foreach ($data->magazine as $key => $magazine) {
          $magazineObj = new \stdClass;
          $magazineObj->id_series = $serie->id;
          $magazineObj->id_magazines = intval($magazine->value);
          array_push($magazines, $magazineObj);
        }

        $this->seriemagazines->insertBatch($magazines);
      }

      $response = [
        'status' => TRUE,
        'message' => 'Serie creada con éxito.',
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
