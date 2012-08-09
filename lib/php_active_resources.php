<?php
require_once( "php_active_resource_base.php" );

class phpActiveResources extends phpActiveResourceBase{
  public $_format = 'json';
  public $_site = null;
  
  public function __construct( $attributes=array() ) {
    parent::__construct( $attributes );
  }
}
?>