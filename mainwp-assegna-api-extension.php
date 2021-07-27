<?php

class MainWPAssegnaApiExtension {

  public function __construct() {
    add_filter('mainwp_getsubpages_sites', array(&$this, 'managesites_subpage'), 10, 1);
  }

  public function managesites_subpage($subPage) {

    $subPage[] = array(
      'title' => 'MainWP Assegna Api',
      'slug' => 'MainwpAssegnaApi',
      'sitetab' => true,
      'menu_hidden' => true,
      'callback' => array('MainWPAssegnaApiExtension', 'renderPage'),
    );

    return $subPage;
  }

  /*
   * Create your extension page
   */

  public static function renderPage() {
    ?>
      <div class="postbox">
          <div class="inside">
              <p><?php _e('MainWP Hello World! Extension is an example extension. This extension provides two examples for calling MainWP Actions and Hooks. Purpose of this extension is to give you a start point in developing your first custom extension for the MainWP Plugin.');?></p>
          </div>
      </div>
    <?php
}
}
