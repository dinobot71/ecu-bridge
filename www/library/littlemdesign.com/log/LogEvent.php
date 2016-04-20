<?php 

/**
 * 
 * log \ LogEvent - a single event to be logged by 
 * the logging sub-system.
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

/**
 * 
 * class LogEvent - the encapsulation of a single 
 * item to be logged.  LogEvent includes enough 
 * detail to work with the log level, message, time
 * and any filtering details.
 * 
 *
 */

class littlemdesign_log_LogEvent {
	
  /**
   * 
   * A reference to the original logger
   * 
   * @var object Logger
   * 
   */
	
  private $logger;
  
  /**
   * 
   * The level to log the event at.
   * 
   * @var object LogLevel - the level of this event.
   * 
   */
  
  private $level;
  
  /**
   * 
   * Unix style time stamp for this event.
   * 
   * @var integer
   *  
   */
  
  private $stamp;
  
  /**
   * 
   * the actual message
   * 
   * @var string
   * 
   */
  
  private $message;
  
  /**
   * 
   * The category for this event, allows additional filtering.
   * 
   * @var string
   * 
   */
  
  private $category;
  
  /**
   * 
   * standard constructor
   * 
   * @param object $logger - the Logger instance this event came from
   * 
   * @param object $level - the LogLevel of teh event
   * 
   * @param string $message - the actual message
   * 
   * @param string category - filtering category
   * 
   * @param integer timestamp - the microtime of this event (use null 
   * to auto-set)
   *  
   */
  
  public function __construct($logger, $message, $level, $category="", $stamp=null) {
  	
    if(!is_object($logger) && is_subclass_of($logger, "littlemdesign_log_Logger")) {
      $this->logger = $logger;
    } else {
      $this->logger = null;
    }
    
    $this->level    = littlemdesign_log_LogLevel::toLevel($level);
    
    $this->message  = $message;
    
    $this->category = $category;
    
    if($stamp != null && is_numeric($stamp)) {
      $this->stamp = $stamp;
    } else {
      $this->stamp = microtime(true);
    }

    /* all done */
  } 
 
  /**
   * getLogger() - fetch a reference to the logger that created 
   * this event.
   * 
   * @return object Logger
   */
  
  public function getLogger() {
    return $this->logger;
  }
  
  /**
   * getMessage() - fetch a copy of this event.
   * 
   * @return string
   * 
   */
  
  public function getMessage() {
    return $this->message;	
  }
  
  /**
   * 
   * getTime() - fetch the micro-time of this event.
   * 
   */
  
  public function getTime() {
    return $this->stamp;
  }

  /**
   * 
   * getLevel() - fetch the LogLevel of this event
   * 
   */
  
  public function getLevel() {
    return $this->level;
  }
  
  /**
   * 
   * getCategory() - fetch the category of this event.
   * 
   */
  
  public function getCategory() {
    return $this->category;
  }
  
}

/*
$e = new littlemdesign_log_LogEvent(null, "this is a message", 'DEBUG');
echo "X: ".print_r($e,true)."\n";
*/

?>