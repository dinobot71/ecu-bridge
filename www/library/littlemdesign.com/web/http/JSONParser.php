<?php 

/**
 * 
 * web \ http \ JSONParser - this adaptor can be used when
 * we are passing JSON based data between ourselves and 
 * the remote web server.
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
autorequire('littlemdesign_web_http_ContentParser');

/**
 * 
 * class JSONParser - handle JSON format.
 *
 */

class littlemdesign_web_http_JSONParser
  extends littlemdesign_web_http_ContentParser {

  /**
   *
   * standard constructor
   * 
   */
  	
  public function __construct() {

  	parent::__construct('JSONParser', 'littlemdesign_web_http', true);

  	$this->makeReady();
  }
  	
  /**
   * 
   * parse() - convert the given response from a remote
   * web server into native PHP variable(s) that is easy
   * to work with.
   * 
   * @param mixed $data
   * 
   * @return mixed will be variable/type that is easy to 
   * use in PHP.  If there is a problem will return exactly
   * false.
   * 
   */
  	
  public function parse($data) {
  	
    if(!function_exists("json_decode")) {
  	  $this->error("can no do JSON parsing; json_decode() doesn't exist.");
      return false;
  	}
  	
  	if(empty($data)) {
      return "";
  	}
  	
    $parsed = json_decode($data);
    if(is_null($parsed)) {
      $this->error("can no do JSON parsing; could not interpret data ($data)");
      return false;
    }
        
  	return $parsed;
  }
  
  /**
   * 
   * serialize() - convert native PHP variable(s) into 
   * a format that can be sent out to the remote web 
   * server and consumed by it in an expected MIME type 
   * (format).
   * 
   * @param mixed $data
   * 
   * @return mixed will be a MIME type formatted string.
   * (usually).
   * 
   */
  
  public function serialize($data) {
  	
  	if(!function_exists("json_encode")) {
  	  $this->error("can no do JSON serialization; json_encode() doesn't exist.");
      return false;
  	}
  	
  	if(($data === false)||($data === null)) {
      return "";
  	}
  	
  	return json_encode($data);
  }
}


?>