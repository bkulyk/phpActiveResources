<?php
abstract class phpActiveResourceBase{
  /**
   * The primary key should be the 'id' column, but occasionally this needs to change.
   */
  public $_primary_key = 'id';
  
  /**
   * boolean $_resource_found //will track if the resource was pulled from Rails 
   * or created in PHP.
   */
  protected $_resource_found = false;
  
  /**
   * String $default_site[optional] default host name and protocal for the Rails site, ie. http://localhost:3000
   */
  public static $default_site = null;
  public static $default_user = null;
  public static $default_password = null;
  public $_request_format = '.json';
  
  public $_has_one = array();
  public $_has_many = array();
  
  public $_find_uri   = ":resource_name/:id";
  
  /**
   * An array of non-standard pluralizations 
   * @author https://github.com/lux/phpactiveresource.git
   */
  public static $pleural_corrections = array(
    'persons' => 'people',
    'peoples' => 'people',
    'mans' => 'men',
    'mens' => 'men',
    'womans' => 'women',
    'womens' => 'women',
    'childs' => 'children',
    'childrens' => 'children',
    'sheeps' => 'sheep',
    'octopuses' => 'octopi',
    'quizs' => 'quizzes',
    'axises' => 'axes',
    'buffalos' => 'buffaloes',
    'tomatos' => 'tomatoes',
    'potatos' => 'potatoes',
    'oxes' => 'oxen',
    'mouses' => 'mice',
    'matrixes' => 'matrices',
    'vertexes' => 'vertices',
    'indexes' => 'indices',
  );
  
  /**
   * Pluralize a word, ie 'apple' becomes 'apples' 
   * @author https://github.com/lux/phpactiveresource.git
   */
  static public function pluralize( $word ) {
    $word .= 's';
    $word = preg_replace ('/(x|ch|sh|ss])s$/', '\1es', $word);
    $word = preg_replace ('/ss$/', 'ses', $word);
    $word = preg_replace ('/([ti])ums$/', '\1a', $word);
    $word = preg_replace ('/sises$/', 'ses', $word);
    $word = preg_replace ('/([^aeiouy]|qu)ys$/', '\1ies', $word);
    $word = preg_replace ('/(?:([^f])fe|([lr])f)s$/', '\1\2ves', $word);
    $word = preg_replace ('/ieses$/', 'ies', $word);
    if( isset( phpActiveResourceBase::$pleural_corrections[$word] ) )
      return phpActiveResourceBase::$pleural_corrections[$word];
    return $word;
  }
  
  public function __construct( $attributes=array() ) {
    $k = $this->_primary_key;
    
    // make sure the key is always set to something
    if( !isset( $this->$k ) )
      $this->$k = null;
    
    $this->bind_obj_to_class( $this, (object)$attributes );
  }
  
  /**
   * Get the url of the site to send web requests. 
   * If the _site property has been set, use that, otherwise use the 
   * phpActiveResourceBase::$default_site setting.
   */
  protected function get_site() {
    $site = $this->_site;
    if( is_null( $this->_site ) )
      $site = phpActiveResourceBase::$default_site;
    if( is_null( $site ) ) {
      throw new Exception( "Active Resource site must be defined as a property of the phpActiveResourceBase child class or static property of phpActiveResourceBase" );
      return null;
    }
    return $site;
  }
  
  /**
   * Build the web query parameters in the proper format.  
   * Delegates to the build_json_params method
   * @return String -- encoded parameters
   * @throws parFormatNotSupported
   */
  protected function build_params() {
    if( $this->_request_format == '.json' )
      return $this->build_json_params();
    else
      throw new parFormatNotSupported( "$this->_request_format not yet supported." );
  }
  
  /**
   * Build the json parameters to send.
   * Should be an json encoded object with a single element that has the name of this object 
   * and it's value should be an object with it's properties
   * ie { "user"=>{ "first_name"=>"some", "last_name"=>"body" } }
   * @return String -- json encoded parameters
   */
  protected function build_json_params() {
    $base = new stdClass;
    $key = strtolower( get_class( $this ) );
    $base->$key = new stdClass;
    $obj = &$base->$key;
    foreach( $this as $k=>$v )
      if( substr( $k, 0, 1 ) != '_' )
        if( $k != $this->_primary_key )
          $obj->$k = $v;
    return json_encode( $base );
  }
  
  /**
   * Send the webrequest necessacrary to Update or Create the object.  
   * This is based off of if the resource was retreived from the webserver or not.
   * NOTE: I cannot base this entirely off of the primary key because we have systems that use primary keys 
   * that are not generated integers, instead they are unique strings, etc.
   */
  public function save() {
    $url = $this->get_site().$this->prep_uri().$this->_request_format;
    $method = $this->_resource_found ? "PUT" : "POST"; // updates are PUT creates are POST
    try{
      $res = $this->fetch_object_from_url( $url, $this->build_params(), $method );
      $this->bind_obj_to_class( $this, $res );
    }catch( Exception $e ) {
      return false;
    }
    return true;
  }
  
  /**
   * Alias for the destroy method
   */
  public function delete() {
    return $this->destroy();
  }
  
  /**
   * Send the webrequest necessacary to Destroy the object
   */
  public function destroy() {
    // build url and send request
    $url = $this->get_site().$this->prep_uri().$this->_request_format;
    $this->fetch_object_from_url( $url, $this->build_params(), 'DELETE' );
    
    // blank out properties
    foreach( $this as $k=>$v )
      $this->$k = null;
    return;
  }
  
  /**
   * 
   */
  public function find( $id=null ) {
    // build the url
    $url = $this->get_site().$this->prep_uri( $id ).$this->_request_format;
    // get results from web service
    $res = $this->fetch_object_from_url( $url, 'GET' );
    
    if( is_null( $res ) ) // it doesn't exist
      throw new parNotFound( 'object not found' );
      
    // prep the final object
    /*if( is_array( $res ) ) {
      $klass = get_class( $this );
      foreach( $res as $k=>$v ) {
        $res[$k] = new $klass;
        $this->bind_obj_to_class( $res[$k], $v );
      }
      return $res;
    }*/
    
    $this->_resource_found = true;
    $this->bind_obj_to_class( $this, $res );
    
    return $this;
  }
  
  public function set( $key_or_array, $value=null ) {
    if( is_object($key_or_array) || is_array($key_or_array) ) {
      $this->bind_obj_to_class( $this, (object)$key_or_array );
      return $this;
    } 
   
    $key = "$key_or_array";
    $this->$key = $value;
    return $this;
  }
  
  /**
   * Copy the properties from $obj to $instance
   * @return $instance -- with new properties
   */
  protected function bind_obj_to_class( &$instance, $obj ) {
    $obj = (object)$obj;
    foreach( $obj as $k=>$v )
      if( is_numeric( $v ) )
        $instance->$k = $v;
      else
        $instance->$k = rtrim($v);
    return $instance;
  }
  
  /**
   * Build the uri for accessing this resource
   * @param Mixed $id -- id to be used in the uri
   * @return String -- uri
   */
  protected function prep_uri( $id=null ) {
    // build the uri
    $uri = str_replace( ":id", $id, $this->_find_uri );
    $uri = str_replace( ":resource_name", $this->resource_name(), $uri );
    return $uri;
  }
  
  /**
   * Execute the curl http request
   * @param String $url -- url to access via cURL request
   * @param Strng $params [optional] -- parameters to send to the webservice NOTE: these should already be encoded
   * @param String $method [optional] -- type of request to send, should be one of GET, POST, PUT, DELETE 
   * @note much of this was lifted from: https://github.com/lux/phpactiveresource.git
   * @throws parMoved for 3xx errors
   * @throws parUnprocessable for 401 errors
   * @throws parNotFound for 404 errors
   * @throws parUnprocessable for 422 errors
   * @throws parServerError for 5xx errors
   */
  protected function fetch_object_from_url( $url, $params='', $method='GET' ) {
    $method = strtoupper( "$method" );
    if( !in_array( $method, array( 'GET', "POST", "PUT", 'DELETE' ) ) )
      $method = 'GET';
    
    // echo "\n$method - $url\n";
    // echo "$params\n";
    
    $c = curl_init ();
    curl_setopt($c, CURLOPT_URL, $url);
    curl_setopt($c, CURLOPT_MAXREDIRS, 3);
    curl_setopt($c, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_VERBOSE, 0);
    curl_setopt($c, CURLOPT_HEADER, 1);
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);


    /* HTTP Basic Authentication */
    if ($this->_user && $this->_password) {
      curl_setopt( $c, CURLOPT_USERPWD, $this->_user . ":" . $this->_password ); 
    }elseif( phpActiveResourceBase::$default_user && phpActiveResourceBase::$default_password ) {
      curl_setopt( $c, CURLOPT_USERPWD, phpActiveResourceBase::$default_user . ":" . phpActiveResourceBase::$default_password );
    }

    curl_setopt($c, CURLOPT_HTTPHEADER, array( "Expect:", "Content-Type: application/json", "Length: " . strlen( $params ) ) );
    
    switch( $method ) {
      case 'POST':
        curl_setopt($c, CURLOPT_POST, 1);
        curl_setopt($c, CURLOPT_POSTFIELDS, $params);
        break;
      case 'DELETE':
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;
      case 'PUT':
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($c, CURLOPT_POSTFIELDS, $params);
        break;
      case 'GET':
      default:
        break;
    }
    
    $res = "".curl_exec($c);
    
    $http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    switch( substr( $http_code, 0, 1 ) ) {
      // case 3:
        // throw new parMoved( "http $http_code" );
        // return;
      case 4:
        switch( $http_code ) {
          case 401:
            throw new parUnauthorized( "http $http_code" );
            break;
          case 404:
            throw new parNotFound( "http $http_code" );
            break;
          case 422:
            $this->_errors = $this->decode_response( $res );
            throw new parUnprocessable( "http $http_code" ); 
            break;
        }
        break;
      case 5:
        throw new parServerError( "http $http_code" );
        break;
      default:
        return $this->decode_response( $res );
    }
  }

  /**
   * Separate the header from the body of the response and parse the body to the correct format (if supported)
   * @param String $res -- raw response to be parsed 
   * @return Mixed[] 
   * @throws parFormatNotSupported
   */
  protected function decode_response( $res ) {
    // separate the body from the header
    $x = explode( "\n\r\n", $res );
    list( $headers, $body ) = explode( "\r\n\r\n", $res, 2 );
    
    // might be useful
    $this->_response = array( 'raw'=>$res, 'headers'=>$headers, 'body'=>$body );
    
    if( $this->_request_format == '.json')
      return json_decode( $body );
    else
      throw new parFormatNotSupported( "$this->_request_format not yet supported." );
  }
  
  /**
   * Get the value of the primary key field
   * @return Mixed
   */
  public function primary_key() {
    $field = $this->_primary_key;
    return $this->$field;
  }
  
  public function set_primary_key( $val ) {
    $field = $this->_primary_key;
    $this->$field = $val;
    return $this;
  }
  
  /**
   * Set a has_one relationship
   * @param Sting $name -- name of the relationship
   * @param Mixed[] $options -- options for the relationship NOTE: not yet implemented
   * @todo implement options
   */
  protected function has_one( $name, $options=array() ) {
    $this->_has_one[ $name ] = $options;
  }
  
  /**
   * Check to see if this class has nested resources
   * @return Mixed -- object if has_one was found -- array if has_many was found -- null if nothing was found
   */
  public function __get( $name ) {
    if( strpos( $name, '_' ) === 0 )
      return;
      
    if( isset( $this->_has_one[$name] ) ) {
      // guess the class name
      $klass = ucwords( $name );
      $o = new $klass; // create a new instance of the sub object
      // set the url to be a nested resource
      $o->_find_uri = $this->prep_uri( $this->primary_key() ) . "/" . $o->_find_uri;
      try{
        return $o->find();
      }catch( parNotFound $e ) {
        // return null if the resource does not exist
        return null;
      }
    }
    
  }
  
  /**
   * Create an instance of a nested resource
   * Will update the _find_uri to be nested.
   * @param String $relationship -- name of the relationship
   * @param Object/Associative Array -- parameters to bind to the new object 
   * @return Object -- object of the type specified in the relationship
   * @return Null -- if relationship does was not defined
   */
  public function new_child( $relationship, $params=array() ) {
    if( strpos( $relationship, '_' ) === 0 )
      return;
    
    $pk = $this->primary_key();
    if( empty( $pk ) )
      throw new Exception( "Cannot create a child if the parent ".get_class( $this ). " has not been saved." );
    
    $o = null;
    
    if( isset( $this->_has_one[ $relationship ] ) ) {
      $klass = ucwords( $relationship );
      $o = new $klass; // create a new instance of the sub object
      // set the url to be a nested resource
      $o->_find_uri = $this->prep_uri( $this->primary_key() ) . "/" . $o->_find_uri;
      $params = (object)$params;
      if( count( $params ) )
        $this->bind_obj_to_class( $o, $params );
    }
    
    return $o;
  }
  
  /**
   * Guess the name of the resource by taking the name of the class, making it lowercase then pluralizing.
   * @return String -- the name of the resource to be used in the url  ie Book becaomes books
   */
  protected function resource_name() {
    return phpActiveResourceBase::pluralize( strtolower( get_class( $this ) ) );
  }
  
}

class parUnauthorized extends Exception{}
class parUnprocessable extends Exception{}
class parNotFound extends Exception{}
class parServerError extends Exception{}
class parMoved extends Exception{}
class parFormatNotSupported extends Exception{}
?>