<?php 

/**
 * 
 * log \ LogWriter - this is the super-class for the various
 * kinds of specific log writers we might be using.
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

/**
 * 
 * class LogWriter - methods common for all logogers 
 * we might use.  We do not implement as an interface
 * because there is also state  that is common to
 * all LogWriter objects.
 * 
 * LogWritters generally only write when active and 
 * when not suspended.  Logging starts oafter the start()
 * method is called, and stops whenteh stop() method 
 * is called.
 * 
 * You can pause logging with disable() and resume
 * logging with enable().
 * 
 *
 */

abstract class littlemdesign_log_LogWriter {

  /**
   * 
   * Set to true to enable this writer.
   * 
   * @var boolean
   *  
   */
	
  private $enabled = false;
  
  /*
   * once we have stopped logging, we can't 
   * re-enable until we start() again.
   * 
   */
  
  private $stopped = false;
  
  /**
   * 
   * The name of this LogWriter.
   * 
   * @var string
   * 
   */
  
  private $name = "";
  
  /**
   * 
   * If a LogLevel has been provided for the
   * threshold, then we don't show log messages
   * unless they are more critical then the 
   * given LogLevel.
   * 
   * @var object LogLevel
   * 
   */
	
  private $threshold = null;
  
  /**
   * 
   * we use identity to allocate object ids.
   * 
   * @var integer
   * 
   */
  
  private static $identify = 100000;
  private $id = 0;
  
  /**
   * 
   * standard constructor
   * 
   * @param $name string - the name of this LogWriter
   * @param $level - (optional) the LogLevel threadhold
   * @param $autostart - imediately start loggging?
   * 
   */
  
  public function __construct($name="", $level=null, $autostart=false) {
  	
  	$this->enabled   = false;
  	$this->stopped   = true;
  	$this->name      = $name;
    $this->threshold = null;
    $this->id        = self::$identify++; 
    
    if($level != null) {
      $this->setThreshold($level);
    }

    /* auto-start? */
    
    if($autostart) {
      $this->start();
    }
  }
  
  /**
   * 
   * standard destructor
   * 
   */
  
  public function __destruct() {
    $this->stop();
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
   * start() - start llogging, normally logging won't
   * happen until you call the start() method.
   * 
   */
  
  public function start() {
  	
    if(!$this->stopped) {
      return ;
    }
    
    $this->stopped = false;
    $this->enable();
    $this->startLog();
  }
  
  /**
   * 
   * stop() - stop logging, ensure that logging is disabled
   * and wrap up the loggin.
   * 
   */
  
  public function stop() {
  	
    if($this->stopped) {
      return ;
    }
    
  	$this->disable();
  	$this->endLog();
  	
    $this->stopped = true;
  }
  
  /**
   * 
   * enable() - resume logging.
   * 
   */
  
  public function enable() {
  	
    if($this->stopped) {
      return ;
    }
    
    $this->enabled = true;
  }
  
  /**
   * 
   * suspend logging.
   * 
   */
  
  public function disable() {
    $this->enabled = false;	
  }
  
  /**
   * isEnabled() - check to see if this LogWriter is 
   * actually logging...
   * 
   */
  
  public function isEnabled() {
    if($this->stopped) {
      return false;
    }
    return $this->enabled;
  }
  
  /**
   * 
   * isStopped() - check to see if this logger has
   * been stopped.
   * 
   */
  
  public function isStopped() {
  	return $this->stopped;
  }
  
  /**
   * 
   * getName() - fetch the name of this LogWriter
   * 
   * @return string - the name of this LogWriter.
   * 
   */
  
  public function getName() {
    return $this->name;
  }
  
  /**
   * 
   * setName() - set the name of this LogWriter
   * 
   * @param string $name - the name to set.
   * 
   * @return the name we just set.
   * 
   */
  
  public function setName($name="") {
    $this->name = $name;
    return $this->name;
  }
  
  /**
   * 
   * getThreshold() - fetch the current threshold 
   * for logging.
   * 
   * @return object - the LogLevel threshold.
   * 
   */
   
  public function getThreshold() {
  	return $this->threshold;
  }
  
  /**
   * 
   * setThreshold() - set the threshold at which to 
   * log messages.  Can be a LogLevel or a valid LogLevel
   * mask or string.
   * 
   * @param mixed $level - the LogLevel object or constructor
   * argumetns.
   * 
   * @return LogLevel the new threshold that was just set.
   * 
   */
  
  public function setThreshold($level) {
  	
    if(is_object($level) && (get_class($level)=="littlemdesign_log_LogLevel")) {
    	
      /* set */
    	
      $this->threshold = $level;
      return $this->threshold;	
    }
  	
    /* set */
    
    $this->threshold = littlemdesign_log_LogLevel::toLevel($level);
    if($this->threshold === false) {
      $this->threshold = null;
    }
    
    return $this->threshold;
  }
  
  /**
   *
   * logEvent() - given a LogEvent object, try to actually
   * log it.
   * 
   * @param object LogEvent - the event to log.
   * 
   * @return boolean - true on success.
   * 
   */
  
  public function logEvent($e) {
  	
  	if(!is_object($e) || (get_class($e)!="littlemdesign_log_LogEvent")) {
      error_log("[littlemdesign \ log \ LogWriter] ERROR - was given a non-event to log.");
  	  return false;
  	}
    
  	/* are we logging */
  	
    if(!$this->isEnabled()) {
      return true;
    }
    
    if($this->isStopped()) {
      return true;
    }
    
    /* is it loggable */
    
    $loggable = true;
    
    if(is_object($this->threshold)) {

      $cmp = $this->threshold->compare($e->getLevel());

      if($cmp < 0) {
      	
      	/* level isn't high enough */
      	
      	$loggable = false;
      }
    }
    
    if(!$loggable) {
      return true;
    }
    
    /* 
     * ok, pass the event through to the implementing
     * writer.
     * 
     */
    
    return $this->writeEvent($e);
  }
  
  /**
   * startLog() - the implementing class must
   * do any header or related beginning of logging
   * functions.
   * 
   */
  
  abstract protected function startLog();
  
  /**
   * 
   * endLog() - the implementing class must 
   * do any footer or related end of logging
   * functions.
   * 
   */
  
  abstract protected function endLog();
  
  /**
   * 
   * logEventImp() - the implementing class
   * is responsible for actually logging the
   * given event.
   * 
   * @return boolean - returns true on success.
   * 
   */
  
  abstract protected function writeEvent($e);
  
}

?>