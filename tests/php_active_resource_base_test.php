<?php
require_once( dirname( dirname( __FILE__ ) ) . "/lib/php_active_resource_base.php" );

class m extends phpActiveResourceBase{
  public $site = 'http://localhost:3000';
  
  public function public_get_site() { // work around for protected value
    return $this->get_site();
  }
}

class php_active_resource_base_test extends PHPUnit_Framework_TestCase{
  
  public function test_pluralize() {
    $this->assertEquals( 'beneficiaries', phpActiveResourceBase::pluralize('beneficiary') );
    $this->assertEquals( 'partners', phpActiveResourceBase::pluralize('partner') );
    $this->assertEquals( 'people', phpActiveResourceBase::pluralize('person') );
    $this->assertEquals( 'men', phpActiveResourceBase::pluralize('man') );
    $this->assertEquals( 'apples', phpActiveResourceBase::pluralize('apple') );
  }
  
  public function test_get_site() {
    $m = new m;
    $this->assertEquals( 'http://localhost:3000', $m->public_get_site() );
    
    $m->site = null;
    phpActiveResourceBase::$default_site = "http://example.com";
    $this->assertEquals( 'http://example.com', $m->public_get_site() );
  }
}
?>