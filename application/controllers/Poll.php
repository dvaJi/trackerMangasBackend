<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Poll extends REST_Controller {

  protected $headers;

  function __construct() {
    // Construct the parent class
    parent::__construct();
    $this->load->model('polls');
    $this->load->model('pollUserAnswers');

    $this->headers = apache_request_headers();

    $this->methods['index_get']['limit'] = 500; // 500 requests per hour per user/key
    //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
    //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
  }

  public function index_get() {

    $active = 1;
    if ($this->get('active') != null) {
      $active = ($this->get('active') == 'true') ? 1 : 0;
    }

    if ($this->get('latest') != NULL) {
      // DETALLE
      $latest = ($this->get('latest') == 'true') ? true : false;
      $poll = null;
      $limit = ($latest) ? 1 : 5;

      $poll = $this->polls->relate()->where('active', $active)->order_by('id', 'DESC')->paginate($limit);

      if (empty($poll)) {
        throw new RuntimeException("Error, no active poll.", 1);
      }

      $token = null;
      if(Authorization::tokenIsExist($this->headers)) {
        $token = Authorization::getBearerToken();
        $token = Authorization::validateToken($token);
      }

      if ($latest) {
        $poll = $poll[0];
        $poll->answers = $this->polls->getAnswers($poll->answers);
        $poll->totalVotes = 0;

        foreach ($poll->answers as $key => $value) {
          $poll->totalVotes += $value->votes;
        }

        if ($token != null) {
          $poll->answered = $this->polls->getUser($poll->id, $token->id);
        } else {
          $poll->answered = false;
        }

      } else {

        foreach ($poll as $key => $value) {
          $value->answers = $this->polls->getAnswers($value->answers);
          if ($token != null) {
            $value->answered = $this->polls->getUser($value->id, $token->id);
          } else {
            $value->answered = false;
          }
          $value->totalVotes = 0;
          foreach ($value->answers as $key => $answer) {
            $value->totalVotes += $answer->votes;
          }
        }
      }


      if ($poll != null) {
        $this->set_response($poll, REST_Controller::HTTP_OK);

      } else {
        $this->set_response([
          'status' => FALSE,
          'message' => 'Poll could not be found'
        ], REST_Controller::HTTP_NOT_FOUND);
      }

    } else {

      $polls = $this->polls->relate()->where('active', $active)->order_by('id', 'DESC')->paginate(5);
      foreach ($polls as $key => $value) {
        $value->answers = $this->polls->getAnswers($value->answers);
      }

      if ($polls) {
        header('X-TOTAL-ROWS: ' . $this->polls->countAll());
        $this->response($polls, REST_Controller::HTTP_OK);
      } else {
        $this->response([
          'status' => FALSE,
          'message' => 'No Poll were found'
        ], REST_Controller::HTTP_NOT_FOUND);
      }
    }
  }

  public function index_post() {
    if(!Authorization::tokenIsExist($this->headers)) {
      $this->response('Token not found', REST_Controller::HTTP_UNAUTHORIZED);
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
        if ($result->status === true) {
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
