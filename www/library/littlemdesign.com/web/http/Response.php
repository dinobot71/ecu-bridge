<?php 

/**
 * 
 * web \ http \ Response - a general Response helper, mainly 
 * intended as a base class for REST clients.  Normally a 
 * user would not create a Response directly, Request objects
 * will create these in association with data returned from
 * a remote web server.
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

/* make sure we auto-load required stuff */

autorequire('littlemdesign_util_Object');
autorequire('littlemdesign_util_Error');
autorequire('littlemdesign_web_http_Request');

class littlemdesign_web_http_Response
  extends littlemdesign_util_Object {
  	
  /**
   * 
   * The response content, already decoded into native
   * PHP variable(s)
   * 
   * @var mixed
   * 
   */
  	
  public $body;
  
  /**
   * 
   * The MIME type of the response
   * 
   * @var string
   * 
   */
  
  public $responseType;
  
  /**
   * 
   * An associative array of the response headers.
   * 
   * @var array
   * 
   */
  
  public $headers;
  
  /**
   * 
   * The HTTP status code
   * 
   * @var string
   * 
   */
  
  public $status;
  
  /**
   * 
   * the original Request object.
   * 
   * @var Request
   */
  
  public $request;
  
  /**
   *
   * standard constructor, users should never create Response
   * objects directly, these should always be created automatically
   * by Request as a result of Request::send().
   * 
   * @param unknown_type $headers the respones headers (associative array)
   * @param unknown_type $body the (already decoded) response content
   * @param unknown_type $mime the Content-Type of the response
   * @param unknown_type $status the HTTP status code
   * @param unknown_type $request the original Request object
   * 
   */
  	
  public function __construct(
    $headers=array(), 
    $body='', 
    $mime='plain', 
    $status=200, 
    $request=null) {

  	parent::__construct('Response', 'littlemdesign_web_http', true);

  	$this->unReady();
  	
    $this->body         = $body;
    $this->responseType = $mime;
    $this->headers      = $headers;
    $this->status       = $status;
    $this->request      = $request;  	
  	
  	$this->makeReady();
  }
  
  /**
   * 
   * toString() - cast response as a string
   * 
   */
  
  public function toString() {
  	return strval($this->body);
  }
}

?>