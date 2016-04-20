<?php 

/**
 * 
 * web \ http \ ContentParser - this is the super-class for the
 * various formats that we support when serializing (outputting)
 * or parsing (reading) to/from a remote web server.  For now
 * we support obvious stuff like web forms and JSON, but later
 * we should add ContentParsers for things like XML and YAML.
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

/**
 * 
 * class WebWriter - these are methods common to all
 * ways that we want to send (write) a request to a 
 * remote web server. 
 *
 */

abstract class littlemdesign_web_http_ContentParser
  extends littlemdesign_util_Object {
 	
  /**
   * 
   * parse() - convert the given response from a remote
   * web server into native PHP variable(s) that is easy
   * to work with.
   * 
   * @param mixed $data
   * 
   * @return mixed will be variable/type that is easy to 
   * use in PHP. If there is a problem will return exactly
   * false.
   * 
   */
  	
  abstract public function parse($data);
  
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
  
  abstract public function serialize($data);
}


?>