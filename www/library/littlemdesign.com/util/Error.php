<?php 

/**
 * 
 * util \ Error - the error object we use to represent
 * the error state and details for the most recent error
 * of an object.
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
 * Error - this class is used to handle the details
 * of tracking the most recent error. This model of
 * error handling is advisory; during a method call
 * objects set (and overwrite) their current error on 
 * any error condition, and then return false (or other  
 * negative response). 
 * 
 * Callers of the object can then check for the most
 * recent error (if they so choose).  In this way 
 * objects can report errors without requiring 
 * callers to use errors, or add detailed exception
 * hanlding.
 * 
 * This is a lightweight approach to error handling,
 * but in most situations it is sufficient without
 * being heavy handed about error handling.
 * 
 * @api
 * 
 */

class littlemdesign_util_Error {
 
  /**
   * 
   * The status code (if there is one)
   * 
   * @var integer
   * 
   */
	
  private $code = 0;	
  
  /**
   * 
   * The error message
   * 
   * @var string
   * 
   */
  
  private $msg = "";

  /**
   * 
   * The traceback
   * 
   */
  
  private $traceback = array();
  
  /**
   * 
   * Support for error chains, oiptional.
   * 
   * @var object Error
   * 
   */
  
  private $causedBy = array();
  
  /**
   * 
   * standard constructor, Error objects have
   * no error set on construction.
   * 
   */
  
  public function __construct() {
    $this->clear();
  }
  
  /**
   * 
   * setCode() - override the current error
   * code. Unix style, 0 means no error.
   * 
   * @param integer $code
   * 
   */
  
  public function setCode($code) {
  	
  	if(is_numeric($code)) {
  	  $code = (int)$code;
  	} else {
  	  $code = 0;
  	}
    $this->code = $code;
    
    return true;
  }
  
  /**
   * 
   * getCode() - fetch just the error code, unix
   * style, 0 means no error.
   * 
   * @return integer the current error code.
   * 
   */
  
  public function getCode() {
    return $this->code;
  }
  
  /**
   * 
   * clear() - set to no error.
   * 
   */
  
  public function clear() {
  	
  	$this->code      = 0;
  	$this->msg       = "";
  	$this->traceback = array();
  	$this->causedBy  = array();
  	
  	return true;
  }
  
  /**
   * 
   * setMessage() - actually set an error.
   * 
   * @param string $message - the error message.
   * 
   * @param integer $code - teh status code, unix style, 0
   * means no error.
   * 
   * @param object Error $causedBy - the chained error.
   * 
   */
  
  public function setMessage($message, $code=0, $causedBy=null) {
  	
  	/* the error message */
  	
    $this->msg = $message;
  	
    /* status code */
    
    $this->setCode($code);
  	
    /* set the reason (a chained error)*/
    
    $this->causedBy = null;
    
    if(is_object($causedBy) && (get_class($causedBy)=="littlemdesign_util_Error")) {
      $this->causedBy = clone($causedBy);
    }
  	
    /* setup the traceback */
  	
    $details = debug_backtrace();
  	
    $this->traceback = array();
    
    foreach($details as $frame) {
    	
      if(!isset($frame['file'])) {
    	$frameMsg = $frame['function']."(".implode(',',$frame['args']).")";	
      } else {
        $frameMsg = "[".$frame['file']."] in ".$frame['function']." @ ".$frame['line'];
      }
      $this->traceback[] = $frameMsg;
  	}
  	
  	return true;
  }

  /**
   * 
   * getMessage() - fetch just the error message.
   * 
   * @return string - the error message (not the full
   * traceback etc.)
   * 
   */
  
  public function getMessage() {
    return $this->msg;
  }
  
  /** 
   *
   * hasError() - test to see if an error has been set.
   * 
   * @return boolean - true if there is an error.
   * 
   */
  
  public function hasError() {
    if(($this->code != 0)||($this->msg != "")) {
      return true; 
    }
    return false;
  }
  
  /**
   * 
   * toString() - geneate a human readable message.
   * 
   * @return string - the human readable error message.
   * 
   */
  
  public function toString() {
  	
  	/* build up the error message */
  	
    $text = ""; 

    $text = "[".$this->code."] ".$this->msg." in: \n";
    foreach($this->traceback as $line) {
      $text .= $line." in\n";
    }
    $text .= "\n";
    
    if($this->causedBy != null) {
      $text .= "was caused by:\n\n";
      $text .= $this->casuedBy->toString();
      $text .= "\n";
    }
    
    /* pass it back */
    
    return $text;
  }
}

?>