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

    $this->form_validation->set_error_delimiters(
      $this->config->item('error_start_delimiter', 'ion_auth'),
      $this->config->item('error_end_delimiter', 'ion_auth')
    );

    $this->lang->load('auth');
    $this->ion_auth->set_message_delimiters('', '');
    $this->ion_auth->set_error_delimiters('', '');


    $this->methods['forgot_post']['limit'] = 5;
    $this->methods['register_post']['limit'] = 5;
    $this->methods['activate_get']['limit'] = 5;
  }

  /**
	 * POST: login
	 *
	 * @param $username
	 * @param $password
	 * @param $remember
	 * @author DvaJi
	 */
  public function login_post() {
    $this->config->set_item('csrf_protection', FALSE);
    $data = json_decode(file_get_contents('php://input'));

    if ($this->ion_auth->login($data->username, $data->password, $data->remember)) {
      $user = $this->ion_auth->user()->row();
      $tokenData = array();
      $tokenData['id'] = $user->id;
      $tokenData['username'] = $data->username;
      $tokenData['groups'] = $this->ion_auth->get_users_groups($user->id)->result();
      $tokenData['remember'] = $data->remember;
      $tokenData['iat'] = time();
      $tokenData['exp'] = time() + 30*60*20*32;
      $response['username'] = $data->username;
      $response['groups'] = $this->ion_auth->get_users_groups($user->id)->result();
      $response['token'] = Authorization::generateToken($tokenData);
      $response['message'] = $this->ion_auth->messages();
      $this->set_response($response, REST_Controller::HTTP_OK);
      return;
    } else {
      $response = [
        'status' => REST_Controller::HTTP_UNAUTHORIZED,
        'message' => $this->ion_auth->errors(),
      ];
      $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
    }
    $this->config->set_item('csrf_protection', TRUE);
  }

  /**
	 * POST: register
	 *
	 * @param $username
	 * @param $password
	 * @param $email
	 * @author DvaJi
	 */
  public function register_post() {
    $this->config->set_item('csrf_protection', FALSE);
    $data = json_decode(file_get_contents('php://input'));

    $additional_data = array(
      'first_name' => NULL,
      'last_name'  => NULL,
      'company'    => NULL,
      'phone'      => NULL,
    );

    $result = $this->ion_auth->register($data->username, $data->password, $data->email, $additional_data);

    if ($result) {
      $response = [
        'status' => REST_Controller::HTTP_OK,
        'message' => $this->ion_auth->messages(),
      ];
      $this->set_response($response, REST_Controller::HTTP_OK);
    } else {
      $response = [
        'status' => REST_Controller::HTTP_UNAUTHORIZED,
        'message' => $this->ion_auth->errors(),
      ];
      $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
    }

    $this->config->set_item('csrf_protection', TRUE);
  }

  /**
	 * GET: activate
   * Verifica el código recibido para
	 * activar una cuenta recien registrada.
   *
	 * @param $code
	 * @param $id
	 * @author DvaJi
	 */
  public function activate_get() {
    $code = $this->get('code');
    $id = $this->get('id');
    $activation = null;

    if ($code !== false) {
			$activation = $this->ion_auth->activate($id, $code);
		}

		if ($activation) {
      $response = [
        'status' => REST_Controller::HTTP_OK,
        'message' => $this->ion_auth->messages(),
      ];
      $this->set_response($response, REST_Controller::HTTP_OK);
    } else {
      $response = [
        'status' => REST_Controller::HTTP_UNAUTHORIZED,
        'message' => $this->ion_auth->errors(),
      ];
      $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
    }
  }

  /**
	 * POST: forgot
   * Verifica correo y envía un
	 * código (token) para reestablecer contraseña
   *
	 * @param $email
	 * @author DvaJi
	 */
  public function forgot_post() {
    $this->config->set_item('csrf_protection', FALSE);
    $data = json_decode(file_get_contents('php://input'));
    $identity = $this->ion_auth->where('email', $data->email)->users()->row();

    if(empty($identity)) {
      if($this->config->item('identity', 'ion_auth') != 'email') {
        $this->ion_auth->set_error('forgot_password_identity_not_found');
      } else {
         $this->ion_auth->set_error('forgot_password_email_not_found');
      }

      $response = [
        'status' => REST_Controller::HTTP_UNAUTHORIZED,
        'message' => $this->ion_auth->errors(),
      ];
      $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
    } else {
      $forgotten = $this->ion_auth->forgotten_password($identity->{$this->config->item('identity', 'ion_auth')});

      if ($forgotten) {
        $response = [
          'status' => REST_Controller::HTTP_OK,
          'message' => $this->ion_auth->messages(),
        ];
        $this->set_response($response, REST_Controller::HTTP_OK);
      } else {
        $response = [
          'status' => REST_Controller::HTTP_UNAUTHORIZED,
          'message' => $this->ion_auth->errors(),
        ];
        $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
      }
    }
  }

  /**
	 * POST: reset_password
   * Verifica el código recibido y
	 * cambia la contrasela actual por la recibida
   *
	 * @param $code
   * @param $password
	 * @author DvaJi
	 */
  public function reset_password_post() {
    $this->config->set_item('csrf_protection', FALSE);
    $data = json_decode(file_get_contents('php://input'));
    $code = $data->code;

    if (!isset($code)) {
      $response = [
        'status' => REST_Controller::HTTP_NOT_FOUND,
        'message' => "Se necesita de un código para reestablecer la contraseña.",
      ];
      $this->set_response($response, REST_Controller::HTTP_NOT_FOUND);
    }

    $user = $this->ion_auth->forgotten_password_check($code);

    if ($user) {
      $identity = $user->{$this->config->item('identity', 'ion_auth')};
      $change = $this->ion_auth->reset_password($identity, $data->password);

      if ($change) {
        $response = [
          'status' => REST_Controller::HTTP_OK,
          'message' => $this->ion_auth->messages(),
        ];
        $this->set_response($response, REST_Controller::HTTP_OK);

      } else {
        $response = [
          'status' => REST_Controller::HTTP_UNAUTHORIZED,
          'message' => $this->ion_auth->errors(),
        ];
        $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
      }
    } else {
      $response = [
        'status' => REST_Controller::HTTP_UNAUTHORIZED,
        'message' => $this->ion_auth->errors(),
      ];
      $this->set_response($response, REST_Controller::HTTP_UNAUTHORIZED);
    }
  }

  /**
	 * GET: logout
   * Llama a la funciona logout() de ion_auth
   *
	 * @author DvaJi
   * @return ['token'] = null
	 */
  public function logout_get() {

    $logout = $this->ion_auth->logout();
    $response['token'] = NULL;

    $this->set_response($response, REST_Controller::HTTP_OK);
  }

}
