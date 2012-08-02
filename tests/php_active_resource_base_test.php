<?php
require_once( dirname( dirname( __FILE__ ) ) . "/lib/php_active_resource.php" );
require_once( dirname( dirname( __FILE__ ) ) . "/lib/php_active_resources.php" );

class Movie extends phpActiveResources{
  public $_site = 'http://localhost:3000';
  
  public function __construct() {
    $this->has_one( 'director' );
    parent::__construct();
  }
  
  public function public_get_site() { // work around for protected value
    return $this->get_site();
  }
  
  public function public_build_json_params() {
    return $this->build_json_params();
  }
  
  public function public_build_params() {
    return $this->build_params();
  }
  
  public function public_bind_obj_to_class( &$instance, $obj ) {
    return $this->bind_obj_to_class( $instance, $obj );
  }
  
  public function public_prep_uri( $id=null ) {
    return $this->prep_uri( $id );
  }
  
  public function public_resource_name() {
    return $this->resource_name();
  }
  
  public function public_decode_response( $res ) {
    return $this->decode_response( $res );
  }
  
}

class Director extends phpActiveResource{
  public function public_resource_name() {
    return $this->resource_name();
  }
  public function public_prep_uri( $id=null ) {
    return $this->prep_uri( $id );
  }
}

class php_active_resource_base_test extends PHPUnit_Framework_TestCase{
  
  public function test_find() {}
  
  public function test_save() {}
  
  public function test_destroy() {}
  
  public function test_delete() {
    # nothing to do here
  }
  
  public function test_new_child() {
    $m = new Movie;
    $m->id = 3;
    
    $d = $m->new_child( 'director' );
    
    $this->assertTrue( $d instanceof Director );
    $this->assertEquals( 'movies/3/director', $d->public_prep_uri() );
    
    // if the movie has no id, then you should not be able to create a director
    $m = new Movie;
    $exception = false;
    try{
      $d = $m->new_child( 'director' );
      $exception = false;
    }catch( Exception $e ) {
      $exception = true;
    }
  }
  
  public function test_resource_name() {
    $d = new Director;
    $this->assertEquals( 'director', $d->public_resource_name() );
    
    $m = new Movie;
    $this->assertEquals( 'movies', $m->public_resource_name() );
  }
  
  public function test_has_one() {
    $m = new Movie;
    $this->assertTrue( isset( $m->_has_one[ 'director' ] ) );
  }
  
  public function test_prep_uri() {
    $m = new Movie;
    $expected = "movies/3";
    $results = $m->public_prep_uri( 3 );
    $this->assertEquals( $expected, $results );
    
    $expected = "movies/user_id";
    $results = $m->public_prep_uri( 'user_id' );
    $this->assertEquals( $expected, $results );
  }
  
  public function test_set() {
    $m = new Movie;
    $return_value = $m->set( 'some', 'value' );
    
    $this->assertEquals( 'value', $m->some );
    // the return value should be the same instance of $m to allow for chaining.
    $this->assertTrue( $m === $return_value );
    
    // simple chaining example
    $m->set( 'x', 'y' )->set( 'a', 'b' );
    $this->assertEquals( 'y', $m->x );
    $this->assertEquals( 'b', $m->a );
    
    // allow passing of array for setting multiple params
    $return_value = $m->set( array( 'foo'=>'bar', 'bar'=>'baz' ) );
    $this->assertEquals( 'bar', $m->foo );
    $this->assertEquals( 'baz', $m->bar );
    $this->assertTrue( $m === $return_value );
    
    // one more time for an object
    $return_value = $m->set( (object)array( 'foo'=>'bar', 'bar'=>'baz' ) );
    $this->assertEquals( 'bar', $m->foo );
    $this->assertEquals( 'baz', $m->bar );
    $this->assertTrue( $m === $return_value );
  }
  
  public function test_primary_key() {
    $m = new Movie;
    
    // it's assumed to use the id field as the primary key
    $m->id = 24234;
    $this->assertEquals( 24234, $m->primary_key() );
    
    // you can override the primary key field
    $m->_primary_key = "user_id";
    $m->user_id = 'some_user';
    $this->assertEquals( 'some_user', $m->primary_key() );
  }
  
  public function test_bind_obj_to_class() {
    $obj = array( 'first_name'=>'Some', 'last_name'=>'Body' );
    
    $m = new Movie;
    
    // try binding an array
    $i = new stdClass;
    $m->public_bind_obj_to_class( $i, $obj );
    
    $this->assertEquals( 'Some', $i->first_name );
    $this->assertEquals( 'Body', $i->last_name );
    
    // try binding an object
    $i = new stdClass;
    $m->public_bind_obj_to_class( $i, (object)$obj );
    
    $this->assertEquals( 'Some', $i->first_name );
    $this->assertEquals( 'Body', $i->last_name );
    
    // try with $m instance
    $m->public_bind_obj_to_class( $m, (object)$obj );
    
    $this->assertEquals( 'Some', $m->first_name );
    $this->assertEquals( 'Body', $m->last_name ); 
  }
  
  public function test_build_json_params() {
    $m = new Movie;
    $m->title = 'Skyfall';
    $m->year = 2012;
    $m->languate = 'en';
    
    // all properties with a leading underscore are ignored
    $m->_should_not_be_in_json = 'ignore_me';
    
    $expected = '{"movie":{"title":"Skyfall","year":2012,"languate":"en"}}';
    $results = $m->public_build_json_params();
    $this->assertEquals( $expected, $results );
    
    // should just call the public_build_json_params method 
    $results = $m->public_build_params();
    $this->assertEquals( $expected, $results );
  }
  
  public function test_pluralize() {
    $this->assertEquals( 'beneficiaries', phpActiveResourceBase::pluralize('beneficiary') );
    $this->assertEquals( 'partners', phpActiveResourceBase::pluralize('partner') );
    $this->assertEquals( 'people', phpActiveResourceBase::pluralize('person') );
    $this->assertEquals( 'men', phpActiveResourceBase::pluralize('man') );
    $this->assertEquals( 'apples', phpActiveResourceBase::pluralize('apple') );
  }
  
  public function test_get_site() {
    $m = new Movie;
    $this->assertEquals( 'http://localhost:3000', $m->public_get_site() );
    
    $m->_site = null;
    phpActiveResourceBase::$default_site = "http://example.com";
    $this->assertEquals( 'http://example.com', $m->public_get_site() );
    
    phpActiveResourceBase::$default_site = null;
    $m->_site = null;
    
    $exception = false;
    try{
      $m->public_get_site();
      $exception = false;
    }catch( Exception $e ) {
      $exception = true;
    }
    $this->assertTrue( $exception );
  }
  
  public function test_decode_response() {
    global $raw_response;
    $m = new Movie;
    $res = $m->public_decode_response( $raw_response );
    
    $this->assertEquals( '{"title":"Home Alone","year":1990}', $m->_response['body'] );
    $this->assertTrue( is_object( $res ), 'the response should be parsed into a stdClass' ); //associative array
    $this->assertEquals( 'stdClass', get_class( $res ) );
    $this->assertEquals( 'Home Alone', $res->title );
  }
  
}
global $raw_response;
$raw_response = <<<EOD
HTTP/1.1 200 OK \r\nContent-Type: application/json; charset=utf-8\r\nX-Ua-Compatible: IE=Edge\r\nEtag: "524b10b04ec17e7f14cc01da203c4066"\r\nCache-Control: max-age=0, private, must-revalidate\r\nX-Runtime: 1.544000\r\nContent-Length: 253\r\nServer: WEBrick/1.3.1 (Ruby/1.9.2/2012-01-30)\r\nDate: Thu, 02 Aug 2012 20:25:01 GMT\r\nConnection: Keep-Alive\r\n\r\n{"title":"Home Alone","year":1990}
EOD;
?>