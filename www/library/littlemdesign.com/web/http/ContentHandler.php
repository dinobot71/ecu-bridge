<?php 

/**
 * 
 * web \ http \ ContentHandler - this factory generates 
 * appropriate implementations of ContentParser on demand
 * for given MIME types.  This allows us to automatically
 * serialize or parser content, based on the MIME type. 
 * 
 * This allows the application to work with native data 
 * types and let the web layer handle convertion to/from
 * whatever format types are used in the actual transactions
 * with remote web servers.
 * 
 * Use the register() method to add new ContentParser 
 * objects...which do the actual parsing or serialiation.
 * 
 * Currently supported content parsers:
 * 
 *   plain (use as a passthrough - no parsing/serializing) 
 *   json
 *   csv
 *   form
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

autorequire('littlemdesign_web_http_MIME');
autorequire('littlemdesign_web_http_ContentParser');
autorequire('littlemdesign_web_http_PlainParser');
autorequire('littlemdesign_web_http_JSONParser');
autorequire('littlemdesign_web_http_CSVParser');
autorequire('littlemdesign_web_http_FormParser');

/**
 * 
 * ContentHandler - manage the ContentParser objects that are
 * associated with the various MIME types (content formatting).
 * 
 */

class littlemdesign_web_http_ContentHandler {

  /**
   * 
   * $pool is the registry of MIME type => ContentParser 
   * objects.  Each object knows how to either serialize (output)
   * or parse (input) the content for that MIME type.
   * 
   * @var ContentParser
   * 
   */
	
  private static $pool = array();
  
  /**
   * 
   * standard constructor is disallowed, usage should
   * only be via static methods (its a factory)
   * 
   */
  
  private function __construct() {}
  
  /**
   * 
   * register() - register the given ContentParser 
   * for the gtiven MIME type.  If one or the other
   * isn't valid, then return exactly false.
   * 
   * @param string $mime - the mime type to register 
   * for.
   * 
   * @param ContentParser $parser the parser/handler 
   * to register.
   * 
   * @return boolean will return exactly false if there
   * is a problem registering.
   * 
   */
  
  static public function register($mime, $parser) {
  	
  	/* check parameters */
  	
  	$type = littlemdesign_web_MIME::extToType($mime);
  	if($type === false) {
      if(littlemdesign_web_MIME::isTYpe($mime)) {
        $type = strtolower(trim($mime));
      }
  	}
  	
  	if($type === false) {
      error_log("[littlemdesign \ web \ http \ ContentHandler] ERROR bad registration MIME: $mime");
      return false;
  	}
  	
  	if(($parser === false)||($parser === null)|| !($parser instanceof littlemdesign_web_http_ContentParser)) {
      error_log("[littlemdesign \ web \ http \ ContentHandler] ERROR parser is not a parser.");
  	  return false;  		
  	}
  	
  	/* ok, register it */
  	
  	self::$pool[$type] = $parser;
  }
  
  /**
   * 
   * findParser() - fetch the parser/handler for the
   * given MIME type.
   * 
   * @param string $mime the MIME type we want a 
   * parser for.
   * 
   * @return ContentParser will return an appropriate content
   * parser, and will automatically register the parser 
   * for the given content type.
   * 
   */

  static public function findParser($mime='plain', $autoRegister=true) {
  	
    /* check parameters */
  	
  	$type = littlemdesign_web_http_MIME::extToType($mime);
  	if($type === false) {
      if(littlemdesign_web_http_MIME::isTYpe($mime)) {
        $type = strtolower(trim($mime));
        if(preg_match('/^([^;]+)/', $type, $matches)) {
  	      $type = $matches[1];
  	    }
      }
  	}
  	
  	if($type === false) {
      error_log("[littlemdesign \ web \ http \ ContentHandler] ERROR can not find parser, bad MIME: $mime");
      return false;
  	}
  	
  	$parser = null;
  	
  	/* already have one? */
  	
  	if(isset(self::$pool[$type])) {
  	  return self::$pool[$type];	
  	}
  	
  	/* look one up */
  	
  	switch($type) {
      case 'text/plain':
      case 'text/rtf':
      case 'text/html':
      	$parser = new littlemdesign_web_http_PlainParser();
        break;
      case 'application/json':
      	$parser = new littlemdesign_web_http_JSONParser();
      	break;
      case 'text/csv':
      	$parser = new littlemdesign_web_http_CSVParser();
      	break;
      case 'application/x-www-form-urlencoded':
      	$parser = new littlemdesign_web_http_FormParser();
      	break;
  	}
  	
  	if($parser === null) {
      error_log("[littlemdesign \ web \ http \ ContentHandler] ERROR no such parser: $mime");
      return false;
  	}
  	
  	/* cache it? */
  	
  	if($autoRegister) {
      self::$pool[$type] = $parser;
  	}
  	
  	/* all done */
  	
  	return $parser;
  }
}

?>