<?php
require_once( "php_active_resource_base.php" );

class phpActiveResource extends phpActiveResourceBase{
  public $_format = 'json';
  public $_site = null;
  
  public $_find_uri   = ":resource_name";
  
  public function __construct( $attributes=array() ) {
    parent::__construct( $attributes );
  }
  
  protected function resource_name() {
    return strtolower( get_class( $this ) );
  }
}
?>