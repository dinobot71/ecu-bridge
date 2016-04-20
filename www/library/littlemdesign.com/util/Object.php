<?php 

/**
 * 
 * util \ Object - This parent class is intended to take
 * care of all the basic services and house keeping objects 
 * should have.
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

autorequire('littlemdesign_log_LogFactory');
autorequire('littlemdesign_log_LogLevel');
autorequire('littlemdesign_util_Error');

/**
 * 
 * Object - this base class is intended to be common to
 * all classes and provide built in services and house
 * keeping. 
 * 
 * @api
 * 
 */

class littlemdesign_util_Object {

  /**
   * 
   * All objects can individually be made
   * verbose or not verbose. If $debub is true, then 
   * when the logger is set, its threshold will be 
   * overridden to be DEBUG, and all messages will be 
   * logged.
   * 
   * This option must be provided the Object
   * constructor, and not be changed during
   * the lifetime of the specific object.
   * 
   * @var boolean
   *  
   */
	
  private $debug = false;
  
  /**
   * 
   * When objects have a usable, unusable state,
   * which is differnet form being in an error state,
   * the ready variable indicates that usable status.
   *
   * Call isReady() to test readiness.
   * 
   * Call makeReady() to set to true.
   * 
   * Call unReady() to set to false.
   * 
   * @var boolean
   *  
   */
	
  private $ready = false;
  
  /**
   * 
   * How we are doing logging, normally 
   * a shared logger, one per class.
   * 
   * @var object Logger
   * 
   */
	
  private $logger = null;
  
  /**
   * 
   * the most recent error, we make no attempt
   * to save the error history.
   * 
   * @var object Error
   * 
   */
  
  private $error = null;

  /**
   * 
   * we use identity to allocate object ids.
   * 
   * @var integer
   * 
   */
  
  private static $identify = 10000000;
  private $id = 0;
  
  /**
   * 
   * standard constructor, extending classes must
   * invoke this aprent constructor in their own
   * constructor.
   * 
   * @param string $name - the name of this logger, 
   * normally the name of the class, of your object.
   * 
   * @param string $category - the filtering group of
   * your object's class. (optional)
   * 
   * @param boolean $debug - enable verbose logging 
   * for this object (or not).  By default the log
   * will show warnings and errors.  If you enable
   * $debug (true), then all messages will be shown.
   * 
   */
  
  public function __construct($name="", $category="", $debug=null) {
  	
    $this->logger = null;
    $this->error  = new littlemdesign_util_Error();
    $this->id     = self::$identify++; 
    $this->ready  = false;
    
    /*
     * META: TODO 
     * 
     * use the name and category to go to the .ini settings
     * and get the override value to use from there.
     * 
     */
    
    if($debug == null) {
      $debug = false;
    } else {
      $debug = true;
    }

    $this->debug  = $debug;
    
    if(!empty($name)) {
      $this->setLogger($name, $category);
    }
  }
  
  /**
   * 
   * standard destructor
   * 
   */
  
  public function __destruct() {
    unset($this->error);
  }
  
  /**
   * 
   * setLogger() - set the logger to use for this object.  Normally this
   * is called indirectly by construction of Object. If you need to 
   * create the logger yourself, you can do so here, by providing an
   * empty $name to the constructor (which will defer logger association).
   * Using this method to set the logger you can control exactly which 
   * kind of logger is used.
   * 
   * @param string $name - the name of your class of object. Normally 
   * you would not use individusal object names as this would generate
   * loggers for each and every object.
   * 
   * This method will call createManaged() on LogFactory
   * so normally your $name should be the name of the class of your 
   * object, and $category should be the package name or the group 
   * your class is in (for filtering).
   *
   * @param string $category the group or package that the class
   * of your object is in (used for filtering).  Any _ characters
   * in $category will be converted to a . characters.
   * 
   * @param string $type - the kind of logger to use, see LogFactory 
   * for details.  
   * 
   * @return boolean - return true on success, and false otherwise.
   * 
   */
  
  protected function setLogger($name, $category, $type=null) {

  	/* check the parameters */
  	
    $name     = trim($name);
    $category = preg_replace('/_+/', '.', trim($category));
  	
    $options  = array();
    
    if(!empty($category)) {
      $options  = array(
        'category' => $category
      );
    }
  	
    /* 
     * make the actual logger, we reuse loggers where possible to 
     * not get slowed down constructing loggers.
     * 
     */
    
    /* by default we only show warnings and errors. */
    
    $level = 'WARN';
    if($this->debug == true) {
      $level = 'DEBUG';  	
    }
    
    $this->logger = 
      littlemdesign_log_LogFactory::createManaged(
        $name, 
        $type, 
        $level, 
        $options
    );
  	
  	return true;
  }
  
  /**
   * 
   * log() - send a message to the log (if we have one), if there is 
   * not logger present, fall through to the PHP web log.
   * 
   * @param string $message
   * 
   * @param object LogLevel $level
   * 
   */
  
  protected function log($message, $level='NOTICE') {
  	
  	/* do the logging */
  	
    if($this->logger != null) {
      $this->logger->log($message, $level);
    } else {
      $msg = "[".littlemdesign_log_LogLevel::toLevel($level).toString()."] $message";
      error_log($msg);
    }
    
    return true;
  }
  
  /**
   * 
   * clear the current error state.
   * 
   */
  
  protected function clearError() {
    $this->error->clear();
    return true;
  }
  
  /**
   * 
   * critical() - Log and track an error, updating
   * the most recent error.  Critical errors are 
   * logged with higher priority.
   * 
   * We only ever track the most recent error.
   * 
   * @param string $message
   * @param integer $code
   * 
   */
  
  protected function critical($message, $code=0) {
  	$this->trackError($message, $code, 'CRIT');
  }
  
  /**
   * 
   * error() - Log and track an error, updating
   * the most recent error.
   * 
   * We only ever track the most recent error.
   * 
   * @param string $message
   * @param integer $code
   * 
   */
  
  protected function error($message, $code=0) {
  	$this->trackError($message, $code, 'ERROR');
  }
  
  /**
   * 
   * info() - Log an information/note message.
   * 
   * @param string $message
   * @param integer $code
   * 
   */
  
  protected function info($message, $code=0) {
  	$this->log($message, 'NOTICE');
  }
  
  /**
   * 
   * warning() - Log a warning message.
   * 
   * @param string $message
   * @param integer $code
   * 
   */
  
  protected function warning($message, $code=0) {
  	$this->log($message, 'WARN');
  }
  
  /**
   * 
   * debug() - Log a debug message.
   * 
   * @param string $message
   * @param integer $code
   * 
   */
  
  protected function debug($message, $code=0) {
  	$this->log($message, 'DEBUG');
  }
  
  /**
   * 
   * trackError() - set (and overwrite) the most recent
   * error.
   * 
   * @param string $message - the actual error message.
   * 
   * @param integer $code - the error code (optional)
   * 
   * @param object LogLevel $level - a LogLevel or something to construct 
   * one with.  This is the level at which to log the error. (optional)
   * 
   * @param object Error $causedBy - error chaining (optional)
   * 
   * @return object Error - returns the current error.
   * 
   */
  
  protected function trackError($message, $code=0, $level=null, $causedBy=null) {
  
  	$this->error->setMessage($message, $code, $causedBy);
    $this->log($message, $level);
    return $this->error; 	
  }
  
  /**
   * 
   * getErrorObject() - in cases where you need a reference of the 
   * the detailed error (the caused by) use this method to peek at
   * the actual error of this object.
   * 
   * @return object Error - the most recent error.
   * 
   */
  
  public function getErrorObject() {
    return $this->error;
  }
  
  /**
   * 
   * getError() - fetch the text representation of the current 
   * error.
   * 
   * @return string - the erroor traceback, or empty string if
   * no error currently.  We always return the most recent 
   * error traceback.
   * 
   */
  
  public function getError() {
    if($this->error->hasError()) {
      return $this->error->toString();
  	}
  	return "";
  }
  
  /**
   * 
   * getId() - return the unique id for this object (within the 
   * current PHP script thread). These ids are not persistent 
   * accross web pages for example.
   * 
   * @return integer - the id of this object.
   * 
   */
  
  public function getId() {
    return $this->id;
  }
  
  /**
   * 
   * isReady() - test to see if this object is 
   * in its usable state.
   * 
   */
  
  public function isReady() {
    return $this->ready;
  }
  
  /**
   * 
   * makeReady() - set this object to be in 
   * its usable state.
   * 
   */
  
  public function makeReady() {
    $this->ready = true;
  }
  
  /**
   * 
   * unReady() - set this object to be in its
   * un-usable state.
   * 
   */
  
  public function unReady() {
    $this->ready = false;	
  }
}

?>