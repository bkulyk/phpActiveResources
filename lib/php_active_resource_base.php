<?php
class phpActiveResourceBase{
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
  
  public $_has_one = array();
  public $_has_many = array();
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
  
  public function build_params() {
    $obj = new stdClass;
    foreach( $this as $k=>$v )
      if( substr( $k, 0, 1 ) != '_' )
        if( $k != $this->_primary_key )
          $obj->$k = $v;
    return json_encode( $obj );
  }
  
  public function save() {
    $url = $this->get_site().$this->prep_uri();
    $res = $this->fetch_object_from_url( $url, $this->build_params(), 'PUT' );
    return $this->bind_obj_to_class( $this, $res );
  }
  
  public function delete() {
    $url = $this->get_site().str_replace( ":id", $this->primary_key(), $this->_delete_uri );
  }
  
  public function find( $id=null ) {
    // build the url
    $url = $this->get_site().$this->prep_uri( $id ).".json";
    // get results from web service
    $res = $this->fetch_object_from_url( $url, 'GET' );
    // prep the final object
    if( is_array( $res ) )
      return $res;
    return $this->bind_obj_to_class( $this, $res );
  }
  
  protected function bind_obj_to_class( &$instance, &$obj ) {
    foreach( $obj as $k=>$v )
      $instance->$k = rtrim($v);
    return $instance;
  }
  
  /**
   * Build the uri for accessing this resource
   */
  protected function prep_uri( $id=null ) {
    // build the uri
    $url = str_replace( ":id", $id, $this->_find_uri );
    $url = str_replace( ":resource_name", $this->resource_name(), $url );
    return $url;
  }
  
  /**
   * Execute the curl http request
   * 
   * @note much of this was lifted from: https://github.com/lux/phpactiveresource.git
   * @throws parMoved for 3xx errors
   * @throws parUnprocessable for 401 errors
   * @throws parNotFound for 404 errors
   * @throws parUnprocessable for 422 errors
   * @throws parServerError for 5xx errors
   */
  protected function fetch_object_from_url( $url, $params=array(), $method='GET' ) {
    $method = is_null( $method ) ? "GET" : strtoupper( $method );
    
    echo "\n$method - $url\n";
    
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

  public function decode_response( $res ) {
    // separate the body from the header
    $x = explode( "\n\r\n", $res );
    list( $headers, $body ) = explode( "\n\r\n", $res, 2 );
    return json_decode( $body );
  }
  
  public function primary_key() {
    $field = $this->_primary_key;
    return $this->$field;
  }
  protected function has_one( $name, $options=array() ) {
    $this->_has_one[ $name ] = $options;
  }
  /**
   * Check to see if this class has nested resources
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
    }else{
      echo "Relationship not found $name\n";
    }
  }  
  
}

class parUnauthorized extends Exception{}
class parUnprocessable extends Exception{}
class parNotFound extends Exception{}
class parServerError extends Exception{}
class parMoved extends Exception{}
?>