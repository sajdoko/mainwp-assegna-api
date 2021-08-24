<?php

class MainWp_Assegna_Api_Rest {

  /**
   * Protected static variable to hold the single instance of the class.
   *
   * @var mixed Default null
   */
  private static $instance = null;

  /**
   * Method instance()
   *
   * Create public static instance.
   *
   * @static
   * @return self::$instance
   */
  public static function instance() {
    if (null == self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Method init()
   *
   * Adds an action to generate API credentials.
   * Adds an action to create the rest API endpoints if activated in the plugin settings.
   */
  public function init() {
    // only activate the api if enabled in the plugin settings.
    if (get_option('mainwp_enable_rest_api')) {
      // check to see whether activated or not.
      $activated = get_option('mainwp_enable_rest_api');

      if ($activated) {
        // run API.
        add_action('rest_api_init', array(&$this, 'mainwp_assegna_api_register_routes'));
      } else {
        wp_die('run API');
      }
    }
  }

  /**
   * Protected variable to hold the API version.
   *
   * @var string API version
   */
  protected $api_version = '1';

  public function mainwp_assegna_api_register_routes() {
    // Create an array which holds all the endpoints. Method can be GET, POST, PUT, DELETE.
    $endpoints = array(
      array(
        'route' => 'site',
        'method' => 'GET',
        'callback' => 'site-apply-branding',
      ),
      array(
        'route' => 'sites',
        'method' => 'POST',
        'callback' => 'sites-execute-snippet',
      ),
    );

    // loop through the endpoints.
    foreach ($endpoints as $endpoint) {

      $function_name = str_replace('-', '_', $endpoint['callback']);

      register_rest_route(
        'mainwp/v' . $this->api_version,
        '/' . $endpoint['route'] . '/' . $endpoint['callback'],
        array(
          'methods' => $endpoint['method'],
          'callback' => array(&$this, 'mainwp_rest_assegna_api_' . $function_name . '_callback'),
          'permission_callback' => '__return_true',
        )
      );
    }

  }

  /**
   * Method mainwp_rest_api_init()
   *
   * Makes sure the correct consumer key and secret are entered.
   *
   * @param array $request The request made in the API call which includes all parameters.
   *
   * @return bool Whether the api credentials are valid.
   */
  public function mainwp_validate_request($request) {

    // users entered consumer key and secret.
    $consumer_key = $request['consumer_key'];
    $consumer_secret = $request['consumer_secret'];

    // data stored in database.
    $consumer_key_option = get_option('mainwp_rest_api_consumer_key');
    $consumer_secret_option = get_option('mainwp_rest_api_consumer_secret');

    if (wp_check_password($consumer_key, $consumer_key_option) && wp_check_password($consumer_secret, $consumer_secret_option)) {
      if (!defined('MAINWP_REST_API')) {
        define('MAINWP_REST_API', true);
      }
      return true;
    } else {
      return false;
    }
  }

  /**
   * Method mainwp_authentication_error()
   *
   * Common error message when consumer key and secret are wrong.
   *
   * @return array $response Array with an error message explaining that the credentials are wrong.
   */
  public function mainwp_authentication_error() {

    $resp_data = array('ERROR' => __('Incorrect or missing consumer key and/or secret. If the issue persists please reset your authentication details from the MainWP > Settings > REST API page, on your MainWP Dashboard site.', 'mainwp'));

    $response = new \WP_REST_Response($resp_data);
    $response->set_status(401);

    return $response;
  }

  /**
   * retrieves the childkey as a function that we can call global $childEnabled;
   *
   */
  public function get_childkey() {
    $childEnabled = apply_filters('mainwp-extension-enabled-check', __FILE__);
    if (!$childEnabled) {
      return false;
    }

    $childKey = $childEnabled['key'];
    return $childKey;
  }

  /**
   * Method mainwp_rest_assegna_api_apply_branding_callback()
   *
   * Callback function for managing the response to API requests made for the endpoint: site-apply-branding
   * Can be accessed via a request like: https://yourdomain.com/wp-json/mainwp/v1/site/site-apply-branding
   * API Method: GET
   *
   * @param array $request The request made in the API call which includes all parameters.
   *
   * @return object $response An object that contains the return data and status of the API request.
   */
  public function mainwp_rest_assegna_api_site_apply_branding_callback($request) {

    // first validate the request.
    if ($this->mainwp_validate_request($request)) {

      $site_id = filter_var($request['site_id'], FILTER_VALIDATE_INT);

      if ($site_id) {
        do_action('mainwp_applypluginsettings_mainwp-branding-extension', $site_id);
      } else {
        $resp_data = array('ERROR' => __('Site id is not valid!', 'mainwp'));

        $response = new \WP_REST_Response($resp_data);
        $response->set_status(400);

        return $response;
      }

    } else {
      // throw common error.
      $response = $this->mainwp_authentication_error();
    }

    return $response;
  }

  /**
   * Method mainwp_rest_assegna_api_sites_execute_snippet_callback()
   *
   * Callback function for managing the response to API requests made for the endpoint: sites-check-cf7-emails
   * Can be accessed via a request like: https://yourdomain.com/wp-json/mainwp/v1/sites/sites-execute-snippet
   * API Method: POST
   *
   * @param array $request The request made in the API call which includes all parameters.
   *
   * @return object $response An object that contains the return data and status of the API request.
   */
  public function mainwp_rest_assegna_api_sites_execute_snippet_callback($request) {

    // first validate the request.
    if ($this->mainwp_validate_request($request)) {

      $childKey = $this->get_childkey();

      if ($childKey) {

        $sites_ids = (isset($request['sites_ids']) && is_array($request['sites_ids'])) ? (array) $request['sites_ids'] : array();
        $sites_action = (isset($request['sites_action']) && !empty($request['sites_action'])) ? $request['sites_action'] : false;
        $sites_snippet = (isset($request['sites_snippet'])) ? $request['sites_snippet'] : '';

        $allowed_actions = array('save_snippet', 'run_snippet', 'delete_snippet');
        $resp_messages = array();

        if (empty($sites_ids)) {
          $resp_data = array('ERROR' => __('Empty site ids!', 'mainwp'));
          $response = new \WP_REST_Response($resp_data);
          $response->set_status(400);
          return $response;
        }

        if (!in_array($sites_action , $allowed_actions)) {
          $resp_data = array('ERROR' => __('Not valid action!', 'mainwp'));
          $response = new \WP_REST_Response($resp_data);
          $response->set_status(400);
          return $response;
        }
        // $types = [
        //   "S" => "Execute on Child Sites",
        //   "R" => "Return info from Child Sites",
        //   "C" => "Save to wp-config.php",
        // ];
        $code_snippet = array(
          'action' => $sites_action,
          'type' => 'R',
          'slug' => 'executeSnippet',
          'code' => $sites_snippet
        );

        foreach ($sites_ids as $site_id) {
          $site_id = filter_var($site_id, FILTER_VALIDATE_INT);

          $information = apply_filters('mainwp_fetchurlauthed', __FILE__, $childKey, $site_id, 'code_snippet', $code_snippet);
          if ($information['status'] == "SUCCESS") {
            $result = ($information['result']) ?? $information['result'];
            array_push($resp_messages, $result);
          } else {
            array_push($resp_messages,"The action: $sites_action did not run on child site with id: $site_id");
          }
          array_push($resp_messages, $information);

        }

        $resp_data = array('SUCCESS' => $resp_messages);
        $response = new \WP_REST_Response($resp_data);
        $response->set_status(200);
        return $response;


      } else {

        $resp_data = array('ERROR' => __('Could not get child key!', 'mainwp'));
        $response = new \WP_REST_Response($resp_data);
        $response->set_status(400);

        return $response;
      }

    } else {
      // throw common error.
      $response = $this->mainwp_authentication_error();
    }

    return $response;
  }

}
