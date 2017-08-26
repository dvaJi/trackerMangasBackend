<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';

class Release extends REST_Controller {

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('releases');

        $this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
        //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
        //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
    }

    public function list_get() {
        $id = $this->get('id');
        // If the id parameter doesn't exist return all the releases
        if ($id === NULL) {
            $page = ($this->get('page') === NULL) ? 1 : $this->get('page');
            $releases = $this->releases->relate()->paginate(10, (int) $page);
            if ($releases) {
                // Set the response and exit
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
}