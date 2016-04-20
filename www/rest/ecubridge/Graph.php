<?php

/**
 * 
 * Graph - generate a graph of ECU Data Logger data and return URI
 * of the image to show in the browser.
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
 * Graph - generate a graph image of the ECU Data Logger data.
 * 
 */

class Graph extends Resource {

  public function __construct() {
    parent::__construct("Graph");
  }
  
  public function fetchParam($param) {

    $arg = $this->arg($param);
    if(($arg == "*")||(empty($arg))) {
      return false;
    }

    return $arg;
  }
  
  public function get() {
    
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

    /* get the parameters */
    
    $this->info("gathering parameters...");
    
    $fields = array(
      "kind",
      "ago",
      "series"
    );
    
    $values = array();
    
    /* try to fetch each required field... */
    
    foreach($fields as $idx => $key) {

      if(!isset($_GET[$key])) {
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
    
    if(count($values) < 3) {
      $msg = "missing parameters";
      $this->error($msg);
      $fail->error = $msg;
      return json_encode($fail);
    }
    
    /* build the status message */
    
    $cmdr = new ECUBridge;

    if(!$cmdr->isReady()) {
      $jsonObj->status = "ERROR";
      $jsonObj->error  = "Can't make ECUBridge: ".$cmdr->getError();
      return json_encode($jsonObj);
    }
    
    $jsonObj->data = $cmdr->graph($values['kind'], $values['ago'], $values['series']);
    
    if(!$jsonObj->data) {
      $jsonObj->status = "ERROR";
      $jsonObj->error  = "Can't get image URI: ".$cmdr->getError();
      return json_encode($jsonObj);
    }
    
    /* all done, pass it back */
    
    return json_encode($jsonObj);
    
  }

}

?>
