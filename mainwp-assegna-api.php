<?php
/**
 * Plugin Name: MainWp Assegna API
 * Plugin URI: https://assegna.server17localweb.com/
 * Description: This plugin makes possible the communication between Assegna and MainWp Dashboards.
 * Version: 1.0.1
 * Author: Sajmir Doko
 * Author URI: https://github.com/sajdoko
 * GitHub URI: https://github.com/sajdoko/mainwp-assegna-api
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
  'https://github.com/sajdoko/mainwp-assegna-api/',
  __FILE__,
  'mainwp-assegna-api'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

require_once plugin_dir_path(__FILE__) . 'mainwp-assegna-api-extension.php';
require_once plugin_dir_path(__FILE__) . 'mainwp-assegna-api-rest.php';

/*
 * Activator Class is used for extension activation and deactivation
 */

class MainWPAssegnaApiExtensionActivator {
  protected $mainwpMainActivated = false;
  protected $childEnabled = false;
  protected $childKey = false;
  protected $childFile;
  protected $plugin_handle = 'mainwp-assegna-api-extension';

  public function __construct() {
    $this->childFile = __FILE__;
    add_filter('mainwp_getextensions', array(&$this, 'get_this_extension'));

    // This filter will return true if the main plugin is activated
    $this->mainwpMainActivated = apply_filters('mainwp_activated_check', false);

    if ($this->mainwpMainActivated !== false) {
      $this->activate_this_plugin();
    } else {
      //Because sometimes our main plugin is activated after the extension plugin is activated we also have a second step,
      //listening to the 'mainwp_activated' action. This action is triggered by MainWP after initialisation.
      add_action('mainwp_activated', array(&$this, 'activate_this_plugin'));
    }
    add_action('admin_notices', array(&$this, 'mainwp_error_notice'));
  }

  public function get_this_extension($pArray) {
    $pArray[] = array('plugin' => __FILE__, 'api' => $this->plugin_handle, 'mainwp' => false, 'callback' => array(&$this, 'settings'));
    return $pArray;
  }

  public function settings() {
    //The "mainwp_pageheader_extensions" action is used to render the tabs on the Extensions screen.
    //It's used together with mainwp_pagefooter_extensions and mainwp_getextensions
    do_action('mainwp_pageheader_extensions', __FILE__);
    if ($this->childEnabled) {
      MainWPAssegnaApiExtension::renderPage();
    } else {
      echo '<div class="mainwp_info-box-yellow">' . __('The Extension has to be enabled to change the settings.') . '</div>';
    }
    do_action('mainwp_pagefooter_extensions', __FILE__);
  }

  //The function "activate_this_plugin" is called when the main is initialized.
  public function activate_this_plugin() {
    //Checking if the MainWP plugin is enabled. This filter will return true if the main plugin is activated.
    $this->mainwpMainActivated = apply_filters('mainwp_activated_check', $this->mainwpMainActivated);

    // The 'mainwp_extension_enabled_check' hook. If the plugin is not enabled this will return false,
    // if the plugin is enabled, an array will be returned containing a key.
    // This key is used for some data requests to our main
    $this->childEnabled = apply_filters('mainwp_extension_enabled_check', __FILE__);

    $this->childKey = $this->childEnabled['key'];

    new MainWPAssegnaApiExtension();
    MainWp_Assegna_Api_Rest::instance()->init();

  }

  public function mainwp_error_notice() {
    global $current_screen;
    if ($current_screen->parent_base == 'plugins' && $this->mainwpMainActivated == false) {
      echo '<div class="error"><p>MainWP Assegna Api Extension ' . __('requires ') . '<a href="http://mainwp.com/" target="_blank">MainWP</a>' . __(' Plugin to be activated in order to work. Please install and activate') . '<a href="http://mainwp.com/" target="_blank">MainWP</a> ' . __('first.') . '</p></div>';
    }
  }

}

global $mainWPAssegnaApiExtensionActivator;
$mainWPAssegnaApiExtensionActivator = new MainWPAssegnaApiExtensionActivator();