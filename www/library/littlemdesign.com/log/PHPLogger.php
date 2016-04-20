<?php 

/**
 * 
 * log \ PHPLogger - log events to the default PHP web log.
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

autorequire('littlemdesign_log_LogLevel');
autorequire('littlemdesign_log_LogEvent');
autorequire('littlemdesign_log_LogWriter');

/**
 * 
 * PHPLogger - this writer sends log events to the PHP
 * web log.
 * 
 */

class littlemdesign_log_PHPLogger extends littlemdesign_log_LogWriter {

  /**
   * 
   * standard constructor
   * 
   * @param string $name - the name of this writer.
   * 
   * @param object LogLevel $level - the logging threshold
   * 
   */
	
  public function __construct($name="", $level=null) {
  	
  	/* base class initialization, and start logging right away. */
  	
    parent::__construct($name, $level, true);
  }
		
  /**
   * 
   * startLog() - the implementing class must
   * do any header or related beginning of logging
   * functions.
   * 
   */
  
  protected function startLog() {
    error_log("[".$this->getName()."] Logging starts...");
  }
  
  /**
   * 
   * endLog() - the implementing class must 
   * do any footer or related end of logging
   * functions.
   * 
   */
  
  protected function endLog() {
    error_log("[".$this->getName()."] Logging done.");
  }
  
  /**
   * 
   * logEventImp() - the implementing class
   * is responsible for actually logging the
   * given event.
   * 
   * @return boolean - returns true on success.
   * 
   */
  
  protected function writeEvent($e) {
  	
    $name = "";
    if($e->getLogger()!=null) {
      $name = $e->getLogger()->getName();	
    } else {
      $name = $this->getName();
    }
  	
    error_log(
      "[".$name."]".
      "[".$e->getTime()."]".
  	  "[".$e->getLevel()->toString()."] ". 
      $e->getMessage() 
    );
  }
  	
}

/*
$w = new littlemdesign_log_PHPLogger("mikelogger", 'NOTICE');
$w->logEvent(new littlemdesign_log_LogEvent(null, "this is a error message", 'ERROR'));
$w->logEvent(new littlemdesign_log_LogEvent(null, "this is a notice message", 'NOTICE'));
$w->logEvent(new littlemdesign_log_LogEvent(null, "this is a debug message", 'DEBUG'));
*/

?>