<?php 

/**
 * 
 * log \ LogFactory - this is the user visible class 
 * for creating new loggers. Normally users should not
 * create loggers directly, they should use this factory
 * to take advantage of pooling etc.
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
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
autorequire('littlemdesign_log_Logger');

/**
 * 
 * LogFactory - use this class to create a logger
 * instance to use in your script or in your classes.
 *
 * create() - new and unmanaged logger.
 * 
 * createManaged() - create a logger if necessary, but 
 * reuse where possibe.
 * 
 * @api
 * 
 */

class littlemdesign_log_LogFactory {
	
  /* the kinds of loggers we support */
	
  const NO_LOGGER      = 0;
  const DEFAULT_LOGGER = 1;
  const PHP_LOGGER     = 2;

  private static $typeNames = array(
    "0" => "NO_LOGGER",
    "1" => "DEFAULT_LOGGER",
    "2" => "PHP_LOGGER"
  );
  
  /**
   * 
   * When creating managed instances of loggers we 
   * store them here, and reuse them when we get a 
   * request for a kind that we've already seen.
   * 
   * This is a "class" variable, not specific to any
   * instance of LogFactory.  The other approach to doing
   * class variable would be to use $GLOBALS[], but 
   * if we can keep things encapsulated within the
   * class, then its cleaner.
   * 
   * @var array Logger[]
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
   * createManaged() - this method is the same as create() but 
   * it caches loggers by signature in a pool and when you request
   * a logger an attempt will be made to reuse an existing logger.
   * 
   * Logger signatures are calculated base don name, type and level
   * (options are not included).  If you plan to use loggers with
   * different options, you should create and manage them on your 
   * own (use the create() method instead).
   * 
   * Exception: the 'category' option (if present) will be used 
   * as part of the cache signature...since this option is common
   * to all loggers and is used to help with filtering of logs,
   * and would very likely be set differently in your various 
   * classes.  Therefor, if you include the category option, you
   * will get one logger object per class. Still better than 
   * one logger per object, and you get full filtering ability.
   * 
   * The logger pool is intended to allow you have logger objects
   * within your own objects, without have a significant delay, 
   * waiting for logger creation (so you can use logger objects
   * liberally).
   * 
   * For details on the parameters see the create() method below.
   * 
   * 
   * @return object Logger return the requested (but maybe reused)
   * logger object or exactly false on failure.
   * 
   * @api
   * 
   */
  	
  public static function createManaged($name, $type=null, $level=null, $options=null) {
  	
  	/* check the args */
  	
  	$name = trim($name);
  	
    if(empty($name)) {
      error_log("[littlemdesign \ log \ LogFactory] ERROR createManaged() not given name.");
      return false;
    }
    
    /* do we have a category? */
    
    $category = "";
    if(isset($options) && is_array($options)) {
      if(isset($options['category'])) {
      	$category = $options['category'];
      }
    }
  	
    /*
     * META: TODO
     * 
     * if $type was not set explicitly, go to the .ini
     * file and use the $name and $category to try and
     * figure out the type to use.
     * 
     */
    
    if($type===null) {
      $type = 'PHP_LOGGER';
    }
    
    /* make sure we have a type */
    
    $type = strtoupper(trim($type));
  	
    $key = array_search($type, self::$typeNames);
    if($key === false) {
      error_log("[littlemdesign \ log \ LogFactory] ERROR createManaged() unrecognized log type: $type.");
      return false;
    }
  	
    /* figure out the signature */
    
    $levelText = "";
    if($level != null) {
    	
      if(is_string($level)||is_array($level)) {
      	$level = new littlemdesign_log_LogLevel($level);
      }
      
      $levelText = $level->toString();
    }
    $signature = "[$name][$type][$levelText][$category]";
    
    /* do we already have one of these? */
    
    if(isset(self::$pool[$signature])) {
    
      /* yes, return an existing logger */
    	
      return self::$pool[$signature];  
    }
    
    /* we have to create a logger */
    
    $logger = self::create($name, $type, $level, $options);
    if($logger === false) {
      error_log("[littlemdesign \ log \ LogFactory] ERROR createManaged($name,$type) could not instantiate a logger.");
      return false;
    }
    
    /* ok, save it */
    
    self::$pool[$signature] = $logger;
    
    /* pass it back */
    
    return self::$pool[$signature];
  }
   	
  /**
   * 
   * create() - create a completely new logger 
   * of the given type and pass the given arguments
   * to  the log writer, when creating the specific
   * writer, for the logger. 
   * 
   * The arguments take by log writers are arrays
   * with named arguments (options).  The details of
   * which log writers take which arguments are 
   * presented below.
   * 
   * Because returned loggers are shared, you should
   * not use this method where you need logging to 
   * start and stop independantly, in different loggers.
   * These shared loggers will start logging when they
   * are first cached, and will stop logging when 
   * the cache is destroyed (end of script)
   * 
   * @param string $name - the name of the logger
   * 
   * @param string $type - the type of logger to create;
   * this means  the kind of log writer to associate
   * with this logger.  You can add/remove writers later
   * if you like.  This should be one of NO_LOGGER
   * PHP_LOGGER etc.  If no explicit setting is provided
   * ($type=null) by the caller or the .ini file, then 
   * PHP_LOGGER will be used by default.
   * 
   * @param mixed $level - the threshold at which to 
   * do logging.  Can be anything used to construct a 
   * LogLevel,  including a LogLevel.  By deafault there
   * is no threshold, all message kinds are logged.
   * 
   * @param array $options - this is the option mapping for the
   * log writer that will be created to go with this
   * logger.
   * 
   *   NO_LOGGER  - no options.
   *   
   *   PHP_LOGGER - can be used with the 'category' option, to 
   *   allow for filtering.  options ar explained more below.
   *   
   *   DEFAULT_LOGGER - this type means use whatever was 
   *   configured in the configuration files.  This kind of
   *   logger is implied by setting $type to null.
   * 
   * @return object Logger - on success return the new
   * Logger object. If there is a problem, return 
   * exactly false.
   * 
   * 
   * Options for loggers:
   * 
   *   category string - provide a '.' separated namespace to use
   *   for filtering by namespace.  This allows for filtering a group
   *   of differently named loggers...within the same namespace. A
   *   category name might be something like:
   *   
   *      system.component.module.class
   *      
   *   But the $name, might be MyClass (for example.
   *   
   * @api
   * 
   */

  public static function create($name, $type=null, $level=null, $options=null) {
  	
    /* make sure we have a name */
  	
    $name = trim($name);
  	
    if(empty($name)) {
      error_log("[littlemdesign \ log \ LogFactory] ERROR create() not given name.");
      return false;
    }
    
    /* do we have a category? */
    
    $category = "";
    if(isset($options) && is_array($options)) {
      if(isset($options['category'])) {
      	$category = $options['category'];
      }
    }
    
    /*
     * META: TODO
     * 
     * if $type was not set explicitly, go to the .ini
     * file and use the $name and $category to try and
     * figure out the type to use.
     * 
     */
    
    if($type === null) {
      $type = 'PHP_LOGGER';
    }
  	
    /* make sure we have a type */
    
    $type = strtoupper(trim($type));
  	
    $key = array_search($type, self::$typeNames);
    if($key === false) {
      error_log("[littlemdesign \ log \ LogFactory] ERROR create() unrecognized log type: $type.");
      return false;
    }
  	
    $logger = false;
    
    switch($key) {
    	
      case self::NO_LOGGER:
        {
          /* No writer */
        
          /* create the logger, but do not associate a writer */
        	
          $logger = new littlemdesign_Logger($name, $category);
        }
        break;
        
      case self::PHP_LOGGER:
        {
          /* PHPLogger */
        	
          /* create the logger */
          
          $logger = new littlemdesign_log_Logger($name, $category);
          
          /* now the writer */

          $writer = new littlemdesign_log_PHPLogger($name, $level);
          
          /* start logging */
          
          $writer->start();
          
          /* associate */
          
          $logger->addWriter($writer); 
          
        }
        break;

      case DEFAULT_LOGGER:
      	{

           /* META: TODO */
      		
      	}
        break;
        
      default:
      	error_log("[littlemdesign \ log \ LogFactory] ERROR create() unrecognized log type: $type.");
        return false;
      	break;
    }
  	
    if(!is_object($logger)) {	
      error_log("[littlemdesign \ log \ LogFactory] ERROR create() could not make logger ($name,$type).");
      return false;	
    }
    
    /* if we get this far we have a logger to pass back */
    
  	return $logger;
  }	
}

/*
$l1 = littlemdesign_log_LogFactory::create('l1_myname');
$l1->notice('this is a notice');
$l1->error('this is a notice');

echo "X: l1: ".print_r($l1,true)."\n";

$l2 = littlemdesign_log_LogFactory::create('l2_myname');
$l2->notice('this is a notice');
$l2->alert('this is a notice');

echo "X: l2: ".print_r($l2,true)."\n";
*/

/*
$l1 = littlemdesign_log_LogFactory::createManaged('myname');
$l1->notice('this is a notice');
$l1->error('this is a notice');

echo "X: l1: ".print_r($l1,true)."\n";

$l2 = littlemdesign_log_LogFactory::createManaged('myname');
$l2->notice('this is a notice');
$l2->alert('this is a notice');

echo "X: l2: ".print_r($l2,true)."\n";
*/

?>