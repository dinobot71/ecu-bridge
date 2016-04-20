<?php 

/**
 * 
 * web \ http \ FormParser - this adaptor can be used when
 * we are passing web form based data between ourselves and 
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
 * class FormParser - handle web form format.
 *
 */

class littlemdesign_web_http_FormParser
  extends littlemdesign_web_http_ContentParser {

  /**
   *
   * standard constructor
   * 
   */
  	
  public function __construct() {

  	parent::__construct('FormParser', 'littlemdesign_web_http', true);

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
  	
  	/* use PHP built in methods */
  	
    $parsed = array();
    parse_str($data, $parsed);
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
  	
  	/* use PHP built in methods */
  	
    return http_build_query($data, null, '&');
  }
}

?>