<?php

require_once APPPATH . 'libraries/JWT.php';

use \Firebase\JWT\JWT;

class Authorization
{
  public static function validateToken($token)
  {
    $CI =& get_instance();
    $key = $CI->config->item('jwt_key');
    $algorithm = $CI->config->item('jwt_algorithm');
    return JWT::decode($token, $key, array($algorithm));
  }

  public static function generateToken($data)
  {
    $CI =& get_instance();
    $key = $CI->config->item('jwt_key');
    $algorithm = $CI->config->item('jwt_algorithm');
    return JWT::encode($data, $key);
  }

  public static function tokenIsExist($headers)
  {
    return (Authorization::extractHeader() != NULL);
  }

  public static function extractHeader() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
      $headers = trim($_SERVER["Authorization"]);
    }
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
      $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
      $requestHeaders = apache_request_headers();
      // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
      $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
      //print_r($requestHeaders);
      if (isset($requestHeaders['Authorization'])) {
        $headers = trim($requestHeaders['Authorization']);
      }
    }
    return $headers;
  }

  /**
  * get access token from header
  * */
  public static function getBearerToken() {
    $headers = Authorization::extractHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
      if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
      }
    }
    return null;
  }
}
