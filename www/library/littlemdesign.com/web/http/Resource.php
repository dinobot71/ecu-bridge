<?php 

/**
 * 
 * web \ http \ Resource - when a request is made for a REST
 * resoruce, this base class provides the mechanics for 
 * interacting with the REST dispatcher, receiving the input
 * request, the formatting style etc, and sending back  the 
 * response.
 * 
 * General Usage:
 * 
 * To implement a Resource, sub-class this base class and 
 * then define one or more of these methods:
 * 
 *   get()
 *   post()
 *   head()
 *   put()
 *   options()
 *   delete()
 *   trace()
 *   connect()
 *
 * each method should take no arguments and return the 
 * response text exactly formated as you wish it to be
 * sent back to the web client or browser.
 *
 * When constructing your class, you *MUST* call the
 * parent (Resource) constructor with a non-empty name
 * for your resource, and your constructor should not
 * take any arguments.  For example your constructor 
 * should look like:
 * 
 *   class MyClass {
 *     ...
 *     public function __construct() {
 * 
 *       parent::__construct("MyClass");
 *       ...
 *     }
 *     ...
 *   }
 *   
 * The name of your resource can be whatever you like,
 * just not empty.
 * 
 * To find out what format the client is requesting, 
 * JSON, XML or just TEXT, use the getFormat() method.
 * 
 * To find out input parameters, use methdos like args()
 * or arg() to fetch a specific argument.  If you need
 * detailed information on the original request, just
 * use getRequest() to fetch the origianl WebRequest 
 * object.
 * 
 * When your method is done, you should return exactly
 * false if there is a problem (and call error() to set
 * the error message), otherwise you should return a 
 * text string which will be sent back as is to the 
 * client.  Your text string should be formatted 
 * as request by the client via getFormat().
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 */

/* make sure we can auto-load */

{   
  $DS = "\\";   
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $DS = "\\";
  } else {
    $DS = "/";
  }
    
  $path = dirname(__FILE__);
  while(!empty($path)) {
    if(is_readable($path.$DS."autoloader.php")) {
      require_once($path.$DS."autoloader.php");
      break;
    }
    $path = dirname($path);
  }
}

autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');
autorequire('littlemdesign_web_http_ContentHandler');
autorequire('littlemdesign_web_http_URL');
autorequire('littlemdesign_web_http_MIME');
autorequire('littlemdesign_web_http_WebResponse');
autorequire('littlemdesign_web_http_WebRequest');

/**
 * 
 * Response - the base class for REST resources
 *
 */

class littlemdesign_web_http_Resource
  extends littlemdesign_util_Object {

  /**
   * 
   * The basic formats we expect resources
   * to be able to support for their output.
   * 
   */
    
  const TEXT = 1;
  const JSON = 2;
  const XML  = 3;
  
  private $format = self::TEXT;
  
  /**
   * 
   * The name of this resource.
   * 
   * @var string
   * 
   */
    
  private $name = "";
  
  /**
   * 
   * The linked request.
   * 
   * @var WebRequest
   * 
   */
  
  private $request = null;
  
  /**  
   * 
   * The methods that users can implement. A resource
   * can implement any number of methods at the same 
   * time.
   * 
   */
  
  protected static $httpMethods = array(
    "GET", 
    "POST", 
    "HEAD",
    "PUT", 
    "OPTIONS", 
    "DELETE", 
    "TRACE", 
    "CONNECT"
  ); 
  
  /**
   * 
   * The methods this instance has actually 
   * implemented.
   * 
   * @var array
   * 
   */
  
  private $supportedMethods = array();
  
  /**
   * 
   * Standard constructor, your resources must be named,
   * it doesn't have to match the actual resource name,
   * but it must be non-empty. The name is used to track
   * this resource in the logs.
   * 
   */
    
  public function __construct($name) {
    
    $name = trim($name);
    if(empty($name)) {
      return ;
    }
    
    $this->name = $name;
    
    parent::__construct("Resource[$name]", 'littlemdesign_web_http', true);

    $this->info("Constructing...");
    
    /* configure to accept rest calls */
    
    $this->format = self::TEXT;
    
    /* figure out what methods they actually implemented */
    
    $this->supportedMethods = array();
    
    $reflector = new ReflectionClass($this);
    
    foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      $name = strtoupper($method->name);
      if(in_array($name, self::$httpMethods)) {
        $this->supportedMethods[] = $name;     
      }
    }
    $this->info("supported methods: ".implode(',', $this->supportedMethods));
        
    /* good to  go */
    
    $this->makeReady();
    
    $this->info("ready.");
    
  }
  
  /**
   * 
   * supports() - check to see if this resource actually
   * supports a given HTTP method.
   * 
   * @param string $method - one of GET, POST, etc.
   * 
   * @return boolena return exactly true if the method is
   * supported.
   * 
   */
  
  public function supports($method) {

    $method = strtoupper(trim($method));
    if(in_array($method, $this->supportedMethods)) {
      return true;
    }
    
    return false;
  }
  
  /**
   * 
   * getName() - fetch the resource name.
   * 
   * @return string - the name of the resource.
   * 
   */
  
  public function getName() {
    return $this->name;
  }
  
  /**
   * 
   * getRequest() - fetch the linked incoming request.
   * 
   * @return WebRequest
   * 
   */
  
  public function getRequest() {
    return $this->request;
  }
  
  /**
   * 
   * setRequest() - link this resource to the incoming
   * request.
   * 
   * @param WebReqeust $request - the original request
   * we are servicing.
   * 
   * @return boolean return exactly false if there is a
   * problem.
   * 
   */
  
  public function setRequest($request) {
    
    if(!($request instanceof littlemdesign_web_http_WebRequest)) {
      $this->error("setRequest() - expecting WebRequest.");
      return false;
    }
    
    $this->request = $request;
   
    return true;
  }
  
  /**
   * 
   * setFormt() - set the output style this resource
   * is expected to follow.
   * 
   * @param constant $format - one of TEXT, JSON or 
   * XML.
   * 
   */
  
  public function setFormat($format = self::TEXT) {
    
    switch($format) {
      case self::TEXT:
      case self::JSON:
      case self::XML:
        {
          $this->format = $format;
          return true;
        }
        break;
        
      default:
        {
          return false;
        }
        break;
    }
    return false;
  }
  
  /**
   * 
   * getFormat() - fetch what output style
   * is expected from this resource (TEXT,
   * JSON or XML).
   * 
   * @return constant one of TEXT, JSON or XML.
   * 
   */
  
  public function getFormat() {
    return $this->format;
  }
  
  /**
   * 
   * args() - helper method to fetch the
   * merged array of all parameters.
   * 
   * @return array - all possible parameters.
   * 
   */
  
  public function args() {
    
    if($this->request === null) {
      return array();
    }
    
    return $this->request->parameters();
  }
  
  /**
   * 
   * arg() - fetch a specific input parameter, 
   * can come from any of get/post/form (searching
   * in that order).
   * 
   * @param string $name - the input parameter 
   * to fetch.
   * 
   * @return string the parameter value.  If there
   * not such parameter then exactly false is 
   * returned.
   * 
   */
  
  public function arg($name) {
    
    if($this->request === null) {
      return false;
    }
    
    return $this->request->get($name);
  }
  
}

?>