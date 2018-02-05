<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Genre extends REST_Controller {

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('genres');

    //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function list_get() {
    $idSerie = $this->get('id_serie');
    $serieGenres = $this->genres->getGenresBySerie($idSerie);
    $genres = $this->genres->order_by('name', 'ASC')->getAll();
    foreach ($genres as $key => $genre) {
      $genre->checked = FALSE;
      foreach ($serieGenres as $key => $serieGenre) {
        if ($genre->id === $serieGenre->id) {
          $genre->checked = TRUE;
        }
      }
    }
    if ($genres) {
      header('X-TOTAL-ROWS: ' . $this->genres->countAll());
      $this->response($genres, REST_Controller::HTTP_OK);
    } else {
      $this->response([
        'status' => FALSE,
        'message' => 'No releases were found'
      ], REST_Controller::HTTP_NOT_FOUND);
    }
  }
}
