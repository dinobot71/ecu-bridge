<?php 

/**
 * 
 * log \ Logger - this is the user visible class 
 * used for logging.  Normally users won't use 
 * the other logging classes directly (except for
 * the factory)
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
 * Logger - this is the user visible class for logging,
 * the following methods can be used for easy logging 
 * of log messages:
 * 
 *   emergency()
 *   critical()
 *   error()
 *   warning()
 *   notice()
 *   debug()
 *
 * If the logging level is set in the writers used for  
 * logging, then messages will only actually be written
 * if they are at or above the log level of the writer(s).
 * 
 * For example if the log writers are set to log level 
 * ERROR, then only emergency, critical, and error messages
 * will make it to the log.  By default log level is not 
 * set in writers, so by default all messages get logged.
 * 
 * You should not create this class directly; you should
 * use the LogFactory to generate Logger objects when 
 * you need them.  This allows for pooling and easy 
 * creation of both the logger and its writers in one
 * step.  Use LogFactory::create() to make entirely 
 * new loggers, and use createManaged() to draw from 
 * a pool of reusable loggers.
 * 
 * @api
 * 
 */

class littlemdesign_log_Logger {

  /**
   * 
   * The name of this logger.
   * 
   * @var string
   * 
   */
	
  private $name;
  
  /**
   * the category of this logger, used to group
   * and filter loggers by group.  For example:
   * 
   *   system.component.module.class
   * 
   * @var string
   * 
   */
  
  private $category;
  
  /**
   * 
   * The list of log writers to write to.
   * 
   * @var array LogWriter[]
   * 
   */
	
  private $writers = array();
  
  /**
   * 
   * we use identity to allocate object ids.
   * 
   * @var integer
   * 
   */
  
  private static $identify = 200000;
  private $id = 0;
  
  /**
   * 
   * standard constructor...
   * 
   * @param string $name - the name of this logger
   * 
   */
  
  public function __construct($name, $category="") {
    $this->name     = $name;
    $this->category = $category;
    $this->id   = self::$identify++; 
  }
  
  /**
   * 
   * standard destructor
   * 
   */
  
  public function __destruct() {
    unset($this->writers);
  }
  
  /**
   * 
   * getId() - fetch the unique object id
   * 
   */
  
  public function getId() {
    return $this->id;	
  }
  
  /**
   * 
   * writerExists() - check to see if this writer is 
   * already associted with this logger.
   * 
   * @param object LogWriter $writer
   * 
   * @return boolean - true if already associated.
   * 
   */
  
  public function writerExists($writer) {

    foreach($this->writers as $obj) {
      if($obj->getId() == $writer->getId()) {
        return true;
      }
    }
    
    /* nope */
    
    return false;
  }
  
  /**
   * 
   * addWriter() - a logger can not actually write 
   * to a log, this is done by specific kinds of log
   * writers which know how to record to a file log,
   * the PHP web log, to email, etc, etc. Use this 
   * method to add writers to the logger.
   * 
   * Writers are added even if a writer of that name
   * or type is already associated with this Logger.
   * We only avoid adding a writer if *exactly* that
   * writer is already associated with this logger.
   * 
   * @param object LogWriter - the writer to add
   * 
   * @return boolean true on success.
   * 
   */
  
  public function addWriter($writer) {
  	if($this->writerExists($writer)) {
  	  return true;	
  	}
  	
  	/* add it */
  	
  	$this->writers[] = $writer;
  	return true;
  }
  
  public function removeWriter($writer) {
  	
    $keepers = array();
  	
    foreach($this->writers as $obj) {
      if($obj->getId() != $writer->getId()) {
        $keepers[] = $obj;
      }
    }
    
    $this->writers = $keepers;
  }
  
  /**
   * 
   * getName() - fetch the name of this logger.
   * 
   * @return string - the name of this logger.
   * 
   */
  
  public function getName() {
    return $this->name;
  }
  
  /**
   * 
   * emergency() - enter a log message at this level.
   * 
   * @param string $msg - the log message
   * 
   * @api
   * 
   */
  
  public function emergency($msg) {
  	$level = littlemdesign_log_LogLevel::toLevel('EMERG');
    $this->log($msg, $level);	
  }
  
  /**
   * 
   * alert() - enter a log message at this level.
   * 
   * @param string $msg - the log message
   * 
   * @api
   * 
   */
  
  public function alert($msg) {
  	$level = littlemdesign_log_LogLevel::toLevel('ALERT');
    $this->log($msg, $level);	
  }

  /**
   * 
   * critical() - enter a log message at this level.
   * 
   * @param string $msg - the log message
   * 
   */
  
  public function critical($msg) {
  	$level = littlemdesign_log_LogLevel::toLevel('CRIT');
    $this->log($msg, $level);	
  }  
  
  /**
   * 
   * warning() - enter a log message at this level.
   * 
   * @param string $msg - the log message
   *
   * @api
   * 
   */
  
  public function warning($msg) {
  	$level = littlemdesign_log_LogLevel::toLevel('WARN');
    $this->log($msg, $level);	
  }

  /**
   * 
   * error() - enter a log message at this level.
   * 
   * @param string $msg - the log message
   * 
   * @api
   * 
   */
  
  public function error($msg) {
  	$level = littlemdesign_log_LogLevel::toLevel('ERROR');
    $this->log($msg, $level);	
  }

  /**
   * 
   * notice() - enter a log message at this level.
   * 
   * @param string $msg - the log message
   * 
   * @api
   * 
   */
  
  public function notice($msg) {
  	$level = littlemdesign_log_LogLevel::toLevel('NOTICE');
    $this->log($msg, $level);	
  }  
  
  /**
   * 
   * debug() - enter a log message at this level.
   * 
   * @param string $msg - the log message
   * 
   * @api
   * 
   */
  
  public function debug($msg) {
  	$level = littlemdesign_log_LogLevel::toLevel('DEBUG');
    $this->log($msg, $level);	
  } 

  /**
   * 
   * log() - given a log message and logging level to log it at,
   * log it!
   * 
   * @param msg string - the log  message
   * 
   * @param level mixed - the log level, can be specified
   * in any way that is used construct a LogLevel, and can 
   * be a LogLevel object.
   * 
   * @api
   * 
   */
  
  public function log($msg, $level) {
  	
    /* make sure we have a text message and LogLevel */
  	
    $level = littlemdesign_log_LogLevel::toLevel($level);

    if(!is_string($msg)) {
      $msg = "";		
    }
    
    if(($level == false)||($level == null)) {
      $level = littlemdesign_log_LogLevel::toLevel('NOTICE');
    }
    
    /* make the logging event */
    
    $e = new littlemdesign_log_LogEvent($this, $msg, $level, $this->category);
  	
    /* log a prepared event */
  	
    $this->logEvent($e);
  }
  
  /**
   * 
   * logEvent() given a detailed log event, log it by sending
   * the log writers associated with this logger.
   * 
   * @param object LogEvent $e
   * 
   * @return boolean - true on success.
   * 
   */

  public function logEvent($e) {
  	
    if(!is_object($e) || (get_class($e) != "littlemdesign_log_LogEvent")) {
      return false;  	
    }
    
    /* do any filtering of events */
    
    /* META: TODO */
    
    /* actually write the log ... */
    
    foreach($this->writers as $writer) {
      $writer->logEvent($e);	
    }
    
    /* all done */
    
    return true;
  }

}

?>