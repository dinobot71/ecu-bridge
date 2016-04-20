<?php

/**
 * 
 * SetFilter - set an input or output filter on the ECU Bridge
 * 
 * @package chumpcar
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2016-, Little m Design
 * 
 */

{ /* make sure we can auto-load */
  
  if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
  { $DS = "\\"; } else { $DS = "/";}

  $path = dirname(__FILE__); while(!empty($path)) {
    if(is_readable($path.$DS."configure.php")) {
      require_once($path.$DS."configure.php"); break;
    }
    $path = dirname($path);
  }
}

autorequire('littlemdesign\web\http\Resource');
autorequire('chumpcar\ecubridge\ECUBridge');

/**
 * 
 * SetFilter - let the GUI configure ECU Bridge filters
 * 
 */

class SetFilter extends Resource {

  public function __construct() {
    parent::__construct("SetFilter");
  }
  
  /**
   *
   * fetchParam() - given the name of a parameter to the form,
   * fetch it and do any necessary filtering for injection
   * attacks etc.
   *
   * @param string $param - the name of the form input
   *
   * @return string the form input value (filtered).
   *
   */
 
  public function fetchParam($param) {

    $arg = $this->arg($param);
    if(($arg == "*")||(empty($arg))) {
      return false;
    }

    return $arg;
  }
  
  public function post() {
    
    /* fetch the data */
    
    $result  = "";
   
    $jsonObj = (object)array(
      "data"   => "",
      "status" => "OK",
      "error"  => ""
    );
    
    $fail = (object)array(
      "data"   => "",
      "status" => "ERROR",
      "error"  => ""
    );
    
    /* figure out the parameters */

    $fields = array(
      "channel",
      "side",
      "kind",
      "value"
    );
    
    $this->info("gathering parameters...");
    
    $values = array();
    
    /* try to fetch each required field... */
    
    foreach($fields as $idx => $key) {

      if(!isset($_POST[$key])) {
        continue;
      }
      
      $value = $this->fetchParam($key);

      /* check for spammy input */

      $spamPatterns = array(
        '/MIME\-Version/i',
        '/Content\-Type/i',
        '/cc\:/i',
        '/to\:/i',
        '/bcc\:/i',
        '/@rollermarathon\.com/i',
        '/<a\s+href=/i',
        '/\[url=/i'
      );
      
      foreach($spamPatterns as $jdx => $pattern) {

        if(preg_match($pattern, $value)) {
          $msg = "field value looks like spam ($key)";
          $this->error($msg);
          $fail->error = $msg;
          return json_encode($fail);
        }

      }
      
      /* keep it! */
          
      $this->info(" . $key => $value");
      
      $values[$key] = $value;
    }
    
    if(count($values) < 4) {
      $msg = "missing parameters";
      $this->error($msg);
      $fail->error = $msg;
      return json_encode($fail);
    }
    
    /* ok, we have all the parameters, try to set the filter */
    
    $cmdr = new ECUBridge;

    if(!$cmdr->isReady()) {
      $msg =  "Can't make ECUBridge: ".$cmdr->getError();
      $this->error($msg);
      $fail->error  = $msg;
      return json_encode($fail);
    }
    
    if(!$cmdr->setFilter($values['side'], $values['channel'], $values['kind'], $values['value'])) {
      $msg =  "Can't set filter: ".$cmdr->getError();
      $this->error($msg);
      $fail->error  = $msg;
      return json_encode($fail);
    }
      
    /* all done, pass it back */
    
    return json_encode($jsonObj);
    
  }

}

?>
