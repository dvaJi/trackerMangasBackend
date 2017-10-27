<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require_once APPPATH . '/libraries/JWT.php';
use \Firebase\JWT\JWT;

class Auth extends REST_Controller {

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->library(array('ion_auth','form_validation'));
		$this->load->helper(array('url','language'));

		$this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));

		$this->lang->load('auth');

        //$this->methods['get_get']['limit'] = 500; // 500 requests per hour per user/key
        //$this->methods['enviar_post']['limit'] = 100; // 100 requests per hour per user/key
        //$this->methods['delete_delete']['limit'] = 50; // 50 requests per hour per user/key
    }

    public function login_post() {
		$data = json_decode(file_get_contents('php://input'));

		if ($this->ion_auth->login($data->username, $data->password, $data->remember)) {
			$user = $this->ion_auth->user()->row();
			$tokenData = array();
            $tokenData['id'] = $data->username;
            $tokenData['groups'] = $this->ion_auth->get_users_groups($user->id)->result();
            $tokenData['remember'] = $data->remember;
            $tokenData['iat'] = time();
            $tokenData['exp'] = time() + 30*60*20*32;
            $response['username'] = $data->username;
            $response['token'] = Authorization::generateToken($tokenData);
            $this->set_response($response, REST_Controller::HTTP_OK);
            return;
		} else {
			$response = [
                'status' => REST_Controller::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized',
            ];
            $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
		}
    }

    public function logout_get() {

		$logout = $this->ion_auth->logout();
        $response['token'] = NULL;

		$this->set_response($response, REST_Controller::HTTP_OK);
	}

}
