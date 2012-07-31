<?php
require_once( "php_active_resource_base.php" );

class phpActiveResources extends phpActiveResourceBase{
  public $format = 'json';
  public $site = null;
  
  public $_find_uri   = ":resource_name/:id";
  public $_save_uri   = ":resource_name/:id";
  public $_delete_uri = ":resource_name/:id";
  
  public function resource_name() {
    return phpActiveResourceBase::pluralize( strtolower( get_class( $this ) ) );
  }
}
?>