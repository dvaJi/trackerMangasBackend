<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class News extends REST_Controller {

  protected $headers;

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('news_model');

    $this->headers = apache_request_headers();

    $this->methods['index_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function index_get() {

    try {
      
      if ($this->get('id') != NULL) {
        // DETALLE
        $id = $this->get('id');
        $stub = $this->get('stub');
        $conditions = array('id' => $id, 'stub' => $stub);

        $news = $this->news_model->relate()->find($conditions);
        $news->image = "/api/content/news/" . $news->image;

        if ($news != null) {
          $this->set_response($news, REST_Controller::HTTP_OK);

        } else {
          $this->set_response([
            'status' => FALSE,
            'message' => 'News could not be found'
          ], REST_Controller::HTTP_NOT_FOUND);
        }

      } else {

        $news = $this->news_model->relate()->where('active', 1)->order_by('created', 'DESC')->paginate(5);
        foreach ($news as $key => $value) {
          $value->image = "/api/content/news/" . $value->image;
        }

        if ($news) {
          header('X-TOTAL-ROWS: ' . $this->news_model->countAll());
          $this->response($news, REST_Controller::HTTP_OK);
        } else {
          $this->response([
            'status' => FALSE,
            'message' => 'No News were found'
          ], REST_Controller::HTTP_NOT_FOUND);
        }
      }

    } catch (Exception $e) {
      $response = [
        'status' => FALSE,
        'message' => $e->getMessage(),
      ];
      $this->response($response, REST_Controller::HTTP_BAD_REQUEST);

    }
  }

  public function index_post() {
    if(!Authorization::tokenIsExist($this->headers)) {
      $this->response('Token not found', REST_Controller::HTTP_BAD_REQUEST);
    } else {
      try {
        $token = Authorization::getBearerToken();
        $token = Authorization::validateToken($token);
        $data = json_decode(file_get_contents('php://input'));

        if ($token->username != $data->user) {
          $this->response('User error', REST_Controller::HTTP_BAD_REQUEST);
        }

        $answer = new \stdClass;
        $answer->id_answer = $data->answer;
        $answer->id_user = $token->id;

        $result = $this->pollUserAnswers->insert($answer);
        if ($result === true) {
          $this->response([
            'status' => TRUE,
            'message' => "Success"
          ], REST_Controller::HTTP_OK);

        } else {
          $this->response([
            'status' => FALSE,
            'message' => $result
          ], REST_Controller::HTTP_NOT_FOUND);

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

}
