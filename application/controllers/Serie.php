<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Serie extends REST_Controller {

    protected $headers;

    function __construct() {
        // Construct the parent class
        parent::__construct();
        $this->load->model('series');
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
        if(Authorization::tokenIsExist($this->headers)) {
            $this->response('Token not found', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            try {
                $token = $this->headers['authorization'];
                $token = Authorization::validateToken($token);
                $id = $this->get('id');
                // If the id parameter doesn't exist return all the series
                if ($id === NULL) {
                    $page = ($this->get('page') === NULL) ? 1 : $this->get('page');
                    $series = $this->series->relate()->paginate(10, (int) $page);
                    if ($series) {
                        // Set the response and exit
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
        $this->load->model('genres');
        $this->load->model('staff');
        $this->load->model('staffRol');
        $this->load->model('magazines');

        $id = $this->get('id');

        if ($id !== NULL) {
            $id = (int) $id;

            if ($id <= 0) {
                $this->response(NULL, REST_Controller::HTTP_BAD_REQUEST);
            }

            $serie = $this->series->relate()->find($id);

            if (!empty($serie)) {
                $serie->genres = $this->series->getGenres($serie->genres);
                $serie->staff = $this->series->getStaff($serie->staff);
                $serie->magazines = $this->series->getMagazines($serie->magazines);

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
    }

}
