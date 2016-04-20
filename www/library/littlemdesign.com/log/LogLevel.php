<?php

/**
 * 
 * log \ LogLevel - helper class that defines the standard
 * logging levels.  More details here:
 * 
 *   http://tools.ietf.org/html/rfc5424
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

/**
 * 
 * class LogLevel - helper for standard log level definition.
 * 
 */
  
class littlemdesign_log_LogLevel {

  /* define the basic log levels */
	
  const EMERG  = 1;
  const ALERT  = 2;
  const CRIT   = 4;
  const WARN   = 8;
  const ERROR  = 16;
  const NOTICE = 32;
  const DEBUG  = 64;
  const NONE   = 0;
  const ALL    = 127;
  
  /**
   * 
   * the enabled log levels 
   * 
   * @var integer level 
   * 
   */
  
  private $level;
  
  /**
   * 
   * the name/id mapping for llog levels  
   * 
   * @var integer level 
   * 
   */
  
  private static $levels = array(
        "1"  => 'EMERG',
        "2"  => 'ALERT',
        "4"  => 'CRIT',
        "8"  => 'WARN',
        "16" => 'ERROR',
        "32" => 'NOTICE',
        "64" => 'DEBUG'
      );
  
  /**
   * 
   * standard construcor - by default enable all 
   * logging levels.
   * 
   */
  
  public function __construct($level=self::ALL) {
  	$this->setLevel($level);
  }
  
  /**
   * 
   * Set the logging level, an integer is a bit
   * mask of the log levels, and a comma separated
   * list of level names can be used to form a mask.
   * 
   * @param $level the level to use for logging
   * 
   * @return boolean - true on success.
   * 
   */
  
  public function setLevel($level=self::ALL) {

    if(is_numeric($level)) {
    
      /* set */
    	
      $this->level = (int)$level;
      
      return true;
    }
    
    $input = null;
    if(is_array($level)) {
      $input = $level;
    }
    if($input === null) {
      $input = explode(',', $level);
    }
    
    $mask = 0;
    
    foreach($input as $item) {
    	
      $item = trim($item);
      
      if(is_numeric($item)) {
      	$mask = $mask | (int)$item;
      	continue;
      }
      
      $key = array_search(strtoupper($item), self::$levels);
      if($key !== false) {
        $mask |= (int)$key;	
      }
    }
    
    /* set */
    
    $this->level = $mask;
    
    return true;
  }

  /**
   * toLevel() helper function to auto-construct
   * a LogLevel from a list of level names.
   * 
   * @param mixed $text - the list of log levels,
   * use a comman to separate names. You can use 
   * an array instead of a string if you like.
   * 
   * @return object - a LogLevel 
   * 
   */
  
  public static function toLevel($text) {
    
    if(is_object($text) && (get_class($text) == "littlemdesign_log_LogLevel")) {
      return $text;
    }
  	
    $l = new littlemdesign_log_LogLevel($text);
  	
    return $l;  	
  }
  
  /**
   * 
   * toString() - return an integer representation
   * 
   */
  
  public function toString() {
  
    $text = "";
  	
    foreach(self::$levels as $idx => $name) {
  	  if($this->level & ((int)$idx)) {
  	    $text .= $name.",";
  	  }	
    }
  	
    $text = trim($text, " ,");
  	
    /* pass back */
  	
    return $text;
  }
   
  /**
   * 
   * toInteger() - return an integer representation
   * 
   */
  
  public function toInteger() {
  	
    /* pass back */
  	
    return $this->level;
  }

  /**
   * 
   * compare() - compare self to the provided LogLevel,
   * or compare two othe LogLevel objects.  Comparison
   * means return 0 if they are equal.  Return 1 if 
   * the left side is great then the right side and
   * -1 otherwise.
   * 
   * Here "greater" means the one with the highest 
   * bit set, *not* the integer value.
   * 
   * @param object $a the LogLevel to compare to.
   * 
   * @param object $b (optional) compare $a and $b.
   * 
   * @return integer - -1, 0, 1
   * 
   */
  
  public function compare($a, $b=null) {
  
    /* do we have something to compare to? */
  	
    if(!is_object($a)||(get_class($a)!="littlemdesign_log_LogLevel")) {
      return 0;
    }
    
    /* did they want to compare two other things? */
    
    if(is_object($b)&&(get_class($b)=="littlemdesign_log_LogLevel")) {
      return $a->compare($b);
    }
    if($b != null) {
      error_log("[littlemdesign \ log \ LogLevel] ERROR - compare(a,b) but b is not a LogLevel.");
    }
    
    /* we are comparing self with $a */

    $maxA = 0;
    $maxB = 0;
    
    $la = $this->level;
    $lb = $a->level;
    
    /* figure out the MSB in each */
    
    foreach(array(1,2,4,8,16,32,64,128,256) as $idx) {
      if($la & $idx) {
        if($la >= $maxA) {
          $maxA = $idx;
        }
      } 
      if($lb & $idx) {
        if($lb >= $maxB) {
          $maxB = $idx;
        }
      }  
    }

    /* compare */
    
    if($maxA == $maxB) {
      return 0;
    }
    if($maxA > $maxB) {
      return 1;
    }
    
    return -1;
  }
  
}

/*
$l1 = new littlemdesign_log_LogLevel(LogLevel::NOTICE|LogLevel::CRIT);
echo "X1: ".$l1->toInteger().": ".$l1->toString()."\n";

$l2 = new littlemdesign_log_LogLevel(LogLevel::DEBUG|LogLevel::EMERG);
echo "X2: ".$l2->toInteger().": ".$l2->toString()."\n";

echo "X: cmp: ".$l2->compare($l1)."\n";

$l3 = littlemdesign_log_LogLevel::toLevel('WARN,NOTICE');
echo "X3: ".$l3->toInteger().": ".$l3->toString()."\n";
*/

?>