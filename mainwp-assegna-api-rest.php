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
   * Check to see whether activated or not.
   *
   */
  public function is_api_key_enabled() {
    $all_keys = get_option('mainwp_rest_api_keys', false);

    if (!is_array($all_keys)) {
      return false;
    }

    foreach ($all_keys as $item) {
      if (!empty($item['cs']) && !empty($item['enabled'])) {
        return true; // one key enabled, enabled the REST API.
      }
    }

    return false; // all keys disabled.
  }

  /**
   * Method init()
   *
   * Adds an action to generate API credentials.
   * Adds an action to create the rest API endpoints if activated in the plugin settings.
   */
  public function init() {
    // only activate the api if enabled in the plugin settings.
    if ($this->is_api_key_enabled()) {
      // run API.
      add_action('rest_api_init', array(&$this, 'mainwp_assegna_api_register_routes'));
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
        'method' => 'POST',
        'callback' => 'site-apply-branding',
      ),
      array(
        'route' => 'site',
        'method' => 'GET',
        'callback' => 'site-fix-security-issues',
      ),
      array(
        'route' => 'sites',
        'method' => 'POST',
        'callback' => 'sites-execute-snippet',
      ),
      array(
        'route' => 'sites',
        'method' => 'POST',
        'callback' => 'sites-apply-branding',
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

    $consumer_key    = null;
    $consumer_secret = null;

    if (!empty($request['consumer_key']) && !empty($request['consumer_secret'])) {
      // users entered consumer key and secret.
      $consumer_key    = $request['consumer_key'];
      $consumer_secret = $request['consumer_secret'];
    } else {
      $headers = apache_request_headers();

      if (isset($headers['x-api-key'])) {
        $header_keys = $headers['x-api-key'];
        $api_keys    = json_decode($header_keys, true);
        if (is_array($api_keys) && isset($api_keys['consumer_key'])) {
          // users entered consumer key and secret.
          $consumer_key    = $api_keys['consumer_key'];
          $consumer_secret = $api_keys['consumer_secret'];
        }
      }
    }

    // data stored in database.
    $all_keys = get_option('mainwp_rest_api_keys', false);
    if (!is_array($all_keys)) {
      $all_keys = array();
    }

    if (isset($all_keys[$consumer_key])) {
      $existed_key = $all_keys[$consumer_key];
      if (is_array($existed_key) && isset($existed_key['cs'])) {
        $consumer_secret_key = $existed_key['cs'];
        $enabled             = isset($existed_key['enabled']) && !empty($existed_key['enabled']) ? true : false;
        if ($enabled && wp_check_password($consumer_secret, $consumer_secret_key)) {
          if (!defined('MAINWP_REST_API')) {
            define('MAINWP_REST_API', true);
          }
          return true;
        }
      }
    }
    return false;
  }

  /**
   * Method mainwp_authentication_error()
   *
   * Common error message when consumer key and secret are wrong.
   *
   * @return array $response Array with an error message explaining that the credentials are wrong.
   */
  public function mainwp_authentication_error() {

    $resp_data = array(
      'ERROR' => __('Incorrect or missing consumer key and/or secret. If the issue persists please reset your authentication details from the MainWP > Settings > REST API page, on your MainWP Dashboard site.', 'mainwp')
    );

    $response = new \WP_REST_Response($resp_data);
    $response->set_status(401);

    return $response;
  }

  /**
   * retrieves the childkey as a function that we can call global $childEnabled;
   *
   */
  public function get_childkey() {
    $childEnabled = apply_filters('mainwp_extension_enabled_check', __FILE__);
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
   * API Method: POST
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
        $resp_data = array('ERROR' => 'Site id is not valid!');

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
          $resp_data = array('ERROR' => 'Empty site ids!');
          $response = new \WP_REST_Response($resp_data);
          $response->set_status(400);
          return $response;
        }

        if (!in_array($sites_action, $allowed_actions)) {
          $resp_data = array('ERROR' => 'Not valid action!');
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
          'code' => $sites_snippet,
        );

        foreach ($sites_ids as $site_id) {
          $site_id = filter_var($site_id, FILTER_VALIDATE_INT);

          $information = apply_filters('mainwp_fetchurlauthed', __FILE__, $childKey, $site_id, 'code_snippet', $code_snippet);
          if ($information['status'] == "SUCCESS") {
            $result = ($information['result']) ?? $information['result'];
            array_push($resp_messages, $result);
          } else {
            array_push($resp_messages, "The action: $sites_action did not run on child site with id: $site_id");
          }
          array_push($resp_messages, $information);
        }

        $resp_data = array('SUCCESS' => $resp_messages);
        $response = new \WP_REST_Response($resp_data);
        $response->set_status(200);
        return $response;
      } else {

        $resp_data = array('ERROR' => 'Could not get child key!');
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
   * Method mainwp_rest_assegna_api_site_fix_security_issues_callback()
   *
   * Callback function for managing the response to API requests made for the endpoint: site-fix-security-issues
   * Can be accessed via a request like: https://yourdomain.com/wp-json/mainwp/v1/site/site-fix-security-issues
   * API Method: GET
   *
   * @param array $request The request made in the API call which includes all parameters.
   *
   * @return object $response An object that contains the return data and status of the API request.
   */
  public function mainwp_rest_assegna_api_site_fix_security_issues_callback($request) {

    // first validate the request.
    if ($this->mainwp_validate_request($request)) {

      $site_id = filter_var($request['site_id'], FILTER_VALIDATE_INT);

      if (is_numeric($site_id)) {

        $childKey = $this->get_childkey();

        if ($childKey) {

          $data = array(
            'id' => $site_id,
            'feature' => 'all',
          );
          $information = apply_filters('mainwp_fetchurlauthed', __FILE__, $childKey, $site_id, 'securityFix', $data);

          $response = new \WP_REST_Response(array('SUCCESS' => $information));
          $response->set_status(200);
        } else {

          $resp_data = array('ERROR' => 'Could not get child key!');
          $response = new \WP_REST_Response($resp_data);
          $response->set_status(400);

          return $response;
        }
      } else {
        $resp_data = array('ERROR' => 'Site id is not valid!');

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
   * Method mainwp_rest_assegna_api_sites_apply_branding_callback()
   *
   * Callback function for managing the response to API requests made for the endpoint: sites-apply-branding
   * Can be accessed via a request like: https://yourdomain.com/wp-json/mainwp/v1/sites/sites-apply-branding
   * API Method: POST
   *
   * @param array $request The request made in the API call which includes all parameters.
   *
   * @return object $response An object that contains the return data and status of the API request.
   */
  public function mainwp_rest_assegna_api_sites_apply_branding_callback($request) {

    // first validate the request.
    if ($this->mainwp_validate_request($request)) {

      $sites_ids = (isset($request['sites_ids']) && is_array($request['sites_ids'])) ? (array) $request['sites_ids'] : array();
      if (empty($sites_ids)) {
        $resp_data = array('ERROR' => 'Empty sites ids!');
        $response = new \WP_REST_Response($resp_data);
        $response->set_status(400);
        return $response;
      }

      $resp_messages = array();

      foreach ($sites_ids as $site_id) {
        $site_id = filter_var($site_id, FILTER_VALIDATE_INT);
        try {
          do_action('mainwp_applypluginsettings_mainwp-branding-extension', $site_id);
        } catch (\Throwable $th) {
          array_push($resp_messages, $th->getMessage());
          continue;
        }
      }

      $resp_data = array('SUCCESS' => 'Branding applyed sussesfully on sites!', 'MESSAGES' => $resp_messages);
      $response = new \WP_REST_Response($resp_data);
      $response->set_status(200);
      return $response;
    } else {
      // throw common error.
      $response = $this->mainwp_authentication_error();
    }

    return $response;
  }
}
