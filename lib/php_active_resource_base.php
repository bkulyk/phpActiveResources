<?php
class phpActiveResourceBase{
  /**
   * The primary key should be the 'id' column, but occasionally this needs to change.
   */
  public $primary_key = 'id';
  
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
    $site = $this->site;
    if( is_null( $this->site ) )
      $site = phpActiveResourceBase::$default_site;
    if( is_null( $site ) ) {
      throw new Exception( "Active Resource site must be defined as a property of the phpActiveResourceBase child class or static property of phpActiveResourceBase" );
      return null;
    }
    return $site;
  }
  
  public function save() {
    $url = $this->get_site().str_replace( ":id", $this->primary_key(), $this->_save_uri );
  }
  
  public function delete() {
    $url = $this->get_site().str_replace( ":id", $this->primary_key(), $this->_delete_uri );
  }
  
  public function find( $id=null ) {
    // build the url
    $url = $this->get_site().$this->prep_uri( $id ).".json";
    // get results from web service
    $obj = $this->fetch_object_from_url( $url, 'GET' );
    // prep the final object
    $res = json_decode( $obj );
    if( is_array( $res ) )
      return $res;
    return $this->bind_obj_to_class( get_class( $this ), $res );
  }
  
  protected function prep_uri( $id=null ) {
    // build the url
    $url = str_replace( ":id", $id, $this->_find_uri );
    // var_dump( $url );
    $url = str_replace( ":resource_name", $this->resource_name(), $url );
    return $url;
  }
  
  /**
   * @note much of this was lifted from: https://github.com/lux/phpactiveresource.git
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
      curl_setopt( $c, CURLOPT_USERPWD, $this->user . ":" . $this->password ); 
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
      case 3:
        throw new parMoved( "http $http_code" );
        return;
      case 4:
        throw new parNotFound( "http $http_code" );
        return;
      case 5;
        throw new parServerError( "http $http_code" );
        return;
    }
    
    // separate the body from the header
    $x = explode( "\n\r\n", $res );
    list( $headers, $body ) = explode( "\n\r\n", $res, 2 );
    
    return $body;
  }
  
  protected function bind_obj_to_class( $klass, $obj ) {
    $instance = new $klass();
    foreach( $obj as $k=>$v )
      $instance->$k = $v;
    return $instance;
  }
  
  public function primary_key() {
    $field = $this->primary_key;
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
      $o->_find_uri = $this->prep_uri( $this->pk() ) . "/" . $o->_find_uri;
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
  
  public function pk() {
    $pk = $this->primary_key;
    return $this->$pk;
  }
}

class parNotFound extends Exception{}
class parServerError extends Exception{}
class parMoved extends Exception{}
?>