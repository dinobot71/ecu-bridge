<?php 

/**
 * 
 * web \ http \ CSVParser - this adaptor can be used when
 * we are passing CSV based data between ourselves and 
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
 * class CSVParser - handle CSV format.
 *
 */

class littlemdesign_web_http_CSVParser
  extends littlemdesign_web_http_ContentParser {

  /**
   *
   * standard constructor
   * 
   */
  	
  public function __construct() {

  	parent::__construct('CSVParser', 'littlemdesign_web_http', true);

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
  	
    if(empty($data)) {
      return "";
    }
    
    $parsed = array();
    
    /*
     * Using an internal datastream is quick and easy, but only
     * supported in PHP 5.2 and later...we could do this the long
     * way...write to a file, open the file, process it, and then
     * delete the file.  FOr now we won't worry about PHP 5.1...
     * its a long way back, and we don't expect CSV interactions 
     * to happen a lot.
     * 
     */
    
    $h = fopen('data://text/plain;base64,'.base64_encode($data), 'r');
    
    if($h === false) {
      $this->error("Can't parse CSV, could not open data source.");
      return false;	
    }
    
    while(($row=fgetcsv($h)) !== false) {
      $parsed[] = $row;
    }

    fclose($h);

    /* all done */
        
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
  	
  	$h = fopen('php://temp/maxmemory:10485760', 'r+');
  	
    if($h === false) {
      $this->error("Can't serialize CSV, could not open data sink.");
      return false;	
    }
    
    foreach ($data as $row) {
      fputcsv($h, $row);
    }

    rewind($h);
    $data = stream_get_contents($h);
    fclose($h);
    
    return $data;
  }
}


?>